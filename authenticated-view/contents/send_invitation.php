<?php
// send_invitation.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authenticated-view/core/login.php");
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invitation'])) {
    $board_id = isset($_POST['board_id']) ? (int)$_POST['board_id'] : 0;
    $email = trim($_POST['email']);
    $permission_level = $_POST['permission_level'];
    $custom_message = trim($_POST['custom_message']);
    
    // Validate inputs
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Lūdzu, ievadiet derīgu e-pasta adresi.";
    } elseif ($board_id <= 0) {
        $error = "Nederīgs projekta ID.";
    } else {
        // Check if the board exists and belongs to the user
        $sql_check_board = "SELECT board_name FROM Planotajs_Boards WHERE board_id = ? AND user_id = ?";
        $stmt_check_board = $connection->prepare($sql_check_board);
        $stmt_check_board->bind_param("ii", $board_id, $user_id);
        $stmt_check_board->execute();
        $result_check_board = $stmt_check_board->get_result();
        
        if ($result_check_board->num_rows === 0) {
            $error = "Jums nav tiesību piekļūt šim projektam vai projekts neeksistē.";
        } else {
            $board = $result_check_board->fetch_assoc();
            $board_name = $board['board_name'];
            
            // Check if user already exists
            $sql_check_user = "SELECT user_id, username FROM Planotajs_Users WHERE email = ?";
            $stmt_check_user = $connection->prepare($sql_check_user);
            $stmt_check_user->bind_param("s", $email);
            $stmt_check_user->execute();
            $result_check_user = $stmt_check_user->get_result();
            
            if ($result_check_user->num_rows > 0) {
                // User exists, add as collaborator
                $invited_user = $result_check_user->fetch_assoc();
                $invited_user_id = $invited_user['user_id'];
                
                // Check if already a collaborator
                $sql_check_collab = "SELECT collaboration_id FROM Planotajs_Collaborators WHERE board_id = ? AND user_id = ?";
                $stmt_check_collab = $connection->prepare($sql_check_collab);
                $stmt_check_collab->bind_param("ii", $board_id, $invited_user_id);
                $stmt_check_collab->execute();
                $result_check_collab = $stmt_check_collab->get_result();
                
                if ($result_check_collab->num_rows > 0) {
                    $error = "Šis lietotājs jau ir projekta sadarbības partneris.";
                } else {
                    // Add as collaborator
                    $sql_add_collab = "INSERT INTO Planotajs_Collaborators (board_id, user_id, permission_level) VALUES (?, ?, ?)";
                    $stmt_add_collab = $connection->prepare($sql_add_collab);
                    $stmt_add_collab->bind_param("iis", $board_id, $invited_user_id, $permission_level);
                    
                    if ($stmt_add_collab->execute()) {
                        // Send invitation email (requires configured mail server)
                        $subject = "Ielūgums uz projektu \"$board_name\"";
                        $headers = "From: noreply@planotajs.lv\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        
                        // Get inviter name
                        $sql_inviter = "SELECT username FROM Planotajs_Users WHERE user_id = ?";
                        $stmt_inviter = $connection->prepare($sql_inviter);
                        $stmt_inviter->bind_param("i", $user_id);
                        $stmt_inviter->execute();
                        $inviter = $stmt_inviter->get_result()->fetch_assoc();
                        $inviter_name = $inviter['username'];
                        
                        // Create email message
                        $email_body = "
                        <html>
                        <head>
                            <title>Ielūgums uz projektu</title>
                        </head>
                        <body>
                            <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                                <div style='background-color: #e63946; color: white; padding: 15px; text-align: center;'>
                                    <h1>Plānotājs+</h1>
                                </div>
                                <div style='padding: 20px; border: 1px solid #ddd; border-top: none;'>
                                    <h2>Sveiki, " . htmlspecialchars($invited_user['username']) . "!</h2>
                                    <p><strong>" . htmlspecialchars($inviter_name) . "</strong> ir uzaicinājis Jūs pievienoties projektam <strong>\"" . htmlspecialchars($board_name) . "\"</strong> kā sadarbības partneri.</p>";
                        
                        if (!empty($custom_message)) {
                            $email_body .= "<p>Ziņojums no " . htmlspecialchars($inviter_name) . ":</p>
                            <blockquote style='border-left: 4px solid #e63946; padding-left: 15px; color: #666;'>" . nl2br(htmlspecialchars($custom_message)) . "</blockquote>";
                        }
                        
                        $email_body .= "
                                    <p>Lai piekļūtu projektam, lūdzu, piesakieties savā Plānotājs+ kontā.</p>
                                    <div style='text-align: center; margin-top: 30px;'>
                                        <a href='https://yoursite.com/login.php' style='background-color: #e63946; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block;'>Pieslēgties kontam</a>
                                    </div>
                                    <p style='margin-top: 30px; font-size: 12px; color: #888;'>Ja Jūs neesat reģistrējies Plānotājs+ platformā, šo e-pastu varat ignorēt vai sazināties ar mums.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        // Send email (uncomment when mail server is configured)
                        // mail($email, $subject, $email_body, $headers);
                        
                        $message = "Lietotājs veiksmīgi pievienots kā sadarbības partneris un ielūgums nosūtīts!";
                    } else {
                        $error = "Kļūda pievienojot lietotāju: " . $connection->error;
                    }
                }
            } else {
                // User doesn't exist, create invitation link
                // Generate unique invitation token
                $token = bin2hex(random_bytes(16));
                $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
                
                // Create invitation record in database
                // First, create the invitations table if it doesn't exist
                $sql_create_table = "CREATE TABLE IF NOT EXISTS Planotajs_Invitations (
                    invitation_id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    board_id INT NOT NULL,
                    inviter_id INT NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    permission_level VARCHAR(20) NOT NULL DEFAULT 'view',
                    custom_message TEXT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (board_id) REFERENCES Planotajs_Boards(board_id),
                    FOREIGN KEY (inviter_id) REFERENCES Planotajs_Users(user_id),
                    UNIQUE KEY (email, board_id)
                )";
                $connection->query($sql_create_table);
                
                // Insert invitation
                $sql_invitation = "INSERT INTO Planotajs_Invitations (email, board_id, inviter_id, token, permission_level, custom_message, expires_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_invitation = $connection->prepare($sql_invitation);
                $stmt_invitation->bind_param("siissss", $email, $board_id, $user_id, $token, $permission_level, $custom_message, $expiry);
                
                if ($stmt_invitation->execute()) {
                    // Send invitation email (requires configured mail server)
                    $subject = "Ielūgums pievienoties Plānotājs+ projektam \"$board_name\"";
                    $headers = "From: noreply@planotajs.lv\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    
                    // Get inviter name
                    $sql_inviter = "SELECT username FROM Planotajs_Users WHERE user_id = ?";
                    $stmt_inviter = $connection->prepare($sql_inviter);
                    $stmt_inviter->bind_param("i", $user_id);
                    $stmt_inviter->execute();
                    $inviter = $stmt_inviter->get_result()->fetch_assoc();
                    $inviter_name = $inviter['username'];
                    
                    // Create email message
                    $invitation_link = "https://yoursite.com/register.php?token=" . urlencode($token);
                    
                    $email_body = "
                    <html>
                    <head>
                        <title>Ielūgums uz projektu</title>
                    </head>
                    <body>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                            <div style='background-color: #e63946; color: white; padding: 15px; text-align: center;'>
                                <h1>Plānotājs+</h1>
                            </div>
                            <div style='padding: 20px; border: 1px solid #ddd; border-top: none;'>
                                <h2>Sveiki!</h2>
                                <p><strong>" . htmlspecialchars($inviter_name) . "</strong> ir uzaicinājis Jūs pievienoties projektam <strong>\"" . htmlspecialchars($board_name) . "\"</strong> Plānotājs+ platformā.</p>";
                    
                    if (!empty($custom_message)) {
                        $email_body .= "<p>Ziņojums no " . htmlspecialchars($inviter_name) . ":</p>
                        <blockquote style='border-left: 4px solid #e63946; padding-left: 15px; color: #666;'>" . nl2br(htmlspecialchars($custom_message)) . "</blockquote>";
                    }
                    
                    $email_body .= "
                                <p>Lai pievienotos projektam, lūdzu, reģistrējiet savu kontu, izmantojot zemāk esošo pogu:</p>
                                <div style='text-align: center; margin-top: 30px;'>
                                    <a href='" . $invitation_link . "' style='background-color: #e63946; color: white; padding: 12px 25px; text-decoration: none; border-radius: 4px; display: inline-block;'>Reģistrēties un pievienoties</a>
                                </div>
                                <p style='margin-top: 20px;'>Šī saite ir derīga 7 dienas.</p>
                                <p style='margin-top: 30px; font-size: 12px; color: #888;'>Ja Jūs neesat pieprasījis šo ielūgumu, šo e-pastu varat ignorēt.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Send email (uncomment when mail server is configured)
                    // mail($email, $subject, $email_body, $headers);
                    
                    $message = "Ielūgums veiksmīgi nosūtīts uz e-pastu $email!";
                } else {
                    $error = "Kļūda izveidojot ielūgumu: " . $connection->error;
                }
            }
        }
    }
}

// Redirect back to project settings page
header("Location: project_settings.php?board_id=$board_id&message=" . urlencode($message) . "&error=" . urlencode($error));
exit();
?>