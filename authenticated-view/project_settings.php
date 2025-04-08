<?php
// project_settings.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get list of boards/projects for the current user
$sql_boards = "SELECT board_id, board_name 
               FROM Planotajs_Boards 
               WHERE user_id = ? AND is_deleted = 0
               ORDER BY board_name ASC";
$stmt_boards = $connection->prepare($sql_boards);
$stmt_boards->bind_param("i", $user_id);
$stmt_boards->execute();
$result_boards = $stmt_boards->get_result();
$boards = [];
while ($board = $result_boards->fetch_assoc()) {
    $boards[] = $board;
}
$stmt_boards->close();

// Handle active board/project selection
$active_board_id = isset($_GET['board_id']) ? (int)$_GET['board_id'] : (isset($boards[0]['board_id']) ? $boards[0]['board_id'] : 0);

// Get board details
$board_details = [];
if ($active_board_id > 0) {
    $sql_details = "SELECT * FROM Planotajs_Boards WHERE board_id = ? AND user_id = ?";
    $stmt_details = $connection->prepare($sql_details);
    $stmt_details->bind_param("ii", $active_board_id, $user_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    $board_details = $result_details->fetch_assoc();
    $stmt_details->close();
}

// Get collaborators for the active board
$collaborators = [];
if ($active_board_id > 0) {
    // Create collaborators table if it doesn't exist
    $sql_create_table = "CREATE TABLE IF NOT EXISTS Planotajs_Collaborators (
        collaboration_id INT AUTO_INCREMENT PRIMARY KEY,
        board_id INT NOT NULL,
        user_id INT NOT NULL,
        permission_level ENUM('view', 'edit', 'admin') NOT NULL DEFAULT 'view',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (board_id) REFERENCES Planotajs_Boards(board_id),
        FOREIGN KEY (user_id) REFERENCES Planotajs_Users(user_id),
        UNIQUE KEY (board_id, user_id)
    )";
    $connection->query($sql_create_table);
    
    $sql_collaborators = "SELECT c.collaboration_id, c.permission_level, u.user_id, u.username, u.email
                         FROM Planotajs_Collaborators c
                         JOIN Planotajs_Users u ON c.user_id = u.user_id
                         WHERE c.board_id = ?";
    $stmt_collaborators = $connection->prepare($sql_collaborators);
    $stmt_collaborators->bind_param("i", $active_board_id);
    $stmt_collaborators->execute();
    $result_collaborators = $stmt_collaborators->get_result();
    while ($collab = $result_collaborators->fetch_assoc()) {
        $collaborators[] = $collab;
    }
    $stmt_collaborators->close();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update project settings
    if (isset($_POST['update_project'])) {
        $board_name = trim($_POST['board_name']);
        $board_description = trim($_POST['board_description']);
        
        if (!empty($board_name)) {
            $sql_update = "UPDATE Planotajs_Boards SET 
                           board_name = ?,
                           board_description = ?,
                           updated_at = CURRENT_TIMESTAMP
                           WHERE board_id = ? AND user_id = ?";
            $stmt_update = $connection->prepare($sql_update);
            $stmt_update->bind_param("ssii", $board_name, $board_description, $active_board_id, $user_id);
            
            if ($stmt_update->execute()) {
                $message = "Projekta iestatījumi atjaunināti veiksmīgi!";
                // Refresh board details
                $stmt_details = $connection->prepare($sql_details);
                $stmt_details->bind_param("ii", $active_board_id, $user_id);
                $stmt_details->execute();
                $result_details = $stmt_details->get_result();
                $board_details = $result_details->fetch_assoc();
                $stmt_details->close();
            } else {
                $error = "Kļūda atjauninot projekta iestatījumus: " . $connection->error;
            }
            $stmt_update->close();
        } else {
            $error = "Projekta nosaukums nevar būt tukšs!";
        }
    }
    
    // Add collaborator
    if (isset($_POST['add_collaborator'])) {
        $email = trim($_POST['collaborator_email']);
        $permission = $_POST['permission_level'];
        
        if (!empty($email)) {
            // Check if user exists
            $sql_check_user = "SELECT user_id FROM Planotajs_Users WHERE email = ?";
            $stmt_check_user = $connection->prepare($sql_check_user);
            $stmt_check_user->bind_param("s", $email);
            $stmt_check_user->execute();
            $result_check_user = $stmt_check_user->get_result();
            
            if ($result_check_user->num_rows > 0) {
                $collab_user = $result_check_user->fetch_assoc();
                $collab_user_id = $collab_user['user_id'];
                
                // Check if user is already a collaborator
                $sql_check_collab = "SELECT collaboration_id FROM Planotajs_Collaborators WHERE board_id = ? AND user_id = ?";
                $stmt_check_collab = $connection->prepare($sql_check_collab);
                $stmt_check_collab->bind_param("ii", $active_board_id, $collab_user_id);
                $stmt_check_collab->execute();
                $result_check_collab = $stmt_check_collab->get_result();
                
                if ($result_check_collab->num_rows === 0) {
                    // Add collaborator
                    $sql_add_collab = "INSERT INTO Planotajs_Collaborators (board_id, user_id, permission_level) VALUES (?, ?, ?)";
                    $stmt_add_collab = $connection->prepare($sql_add_collab);
                    $stmt_add_collab->bind_param("iis", $active_board_id, $collab_user_id, $permission);
                    
                    if ($stmt_add_collab->execute()) {
                        $message = "Lietotājs pievienots kā sadarbības partneris!";
                        // Refresh collaborators list
                        $stmt_collaborators = $connection->prepare($sql_collaborators);
                        $stmt_collaborators->bind_param("i", $active_board_id);
                        $stmt_collaborators->execute();
                        $result_collaborators = $stmt_collaborators->get_result();
                        $collaborators = [];
                        while ($collab = $result_collaborators->fetch_assoc()) {
                            $collaborators[] = $collab;
                        }
                        $stmt_collaborators->close();
                    } else {
                        $error = "Kļūda pievienojot sadarbības partneri: " . $connection->error;
                    }
                    $stmt_add_collab->close();
                } else {
                    $error = "Šis lietotājs jau ir pievienots kā sadarbības partneris.";
                }
                $stmt_check_collab->close();
            } else {
                $error = "Lietotājs ar šādu e-pastu netika atrasts.";
            }
            $stmt_check_user->close();
        } else {
            $error = "Lūdzu, ievadiet e-pasta adresi!";
        }
    }
    
    // Remove collaborator
    if (isset($_POST['remove_collaborator'])) {
        $collaboration_id = (int)$_POST['collaboration_id'];
        
        $sql_remove = "DELETE FROM Planotajs_Collaborators WHERE collaboration_id = ? AND board_id = ?";
        $stmt_remove = $connection->prepare($sql_remove);
        $stmt_remove->bind_param("ii", $collaboration_id, $active_board_id);
        
        if ($stmt_remove->execute()) {
            $message = "Sadarbības partneris noņemts veiksmīgi!";
            // Refresh collaborators list
            $stmt_collaborators = $connection->prepare($sql_collaborators);
            $stmt_collaborators->bind_param("i", $active_board_id);
            $stmt_collaborators->execute();
            $result_collaborators = $stmt_collaborators->get_result();
            $collaborators = [];
            while ($collab = $result_collaborators->fetch_assoc()) {
                $collaborators[] = $collab;
            }
            $stmt_collaborators->close();
        } else {
            $error = "Kļūda noņemot sadarbības partneri: " . $connection->error;
        }
        $stmt_remove->close();
    }
}

// Set page title and content for the layout
$title = "Projektu iestatījumi - Plānotājs+";
$content = 'project_settings_content.php';

// Include the layout
include 'layout.php';
?>