<?php
// project_settings_content.php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authenticated-view/core/login.php");
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
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar with projects list -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold mb-4">Mani projekti</h2>
        
        <?php if (empty($boards)): ?>
            <p class="text-gray-500">Nav atrastu projektu.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($boards as $board): ?>
                    <a href="project_settings.php?board_id=<?= $board['board_id'] ?>" 
                       class="block p-3 rounded-lg hover:bg-gray-100 <?= ($board['board_id'] == $active_board_id) ? 'bg-gray-100 border-l-4 border-[#e63946]' : '' ?>">
                        <?= htmlspecialchars($board['board_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="mt-6">  
            <a href="kanban.php" class="text-blue-600 hover:underline flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Atpakaļ uz kanban
            </a>
        </div>
    </div>
    
    <!-- Main settings content -->
    <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-3">
        <?php if (empty($board_details)): ?>
            <div class="text-center py-8">
                <i class="fas fa-project-diagram text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">Izvēlieties projektu kreisajā izvēlnē</h3>
                <p class="text-gray-500 mt-2">vai izveidojiet jaunu projektu kanban skatā</p>
            </div>
        <?php else: ?>
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <!-- Settings tabs -->
            <div class="mb-6 border-b">
                <ul class="flex flex-wrap -mb-px">
                    <li class="mr-2">
                        <a href="#general" class="inline-block py-2 px-4 border-b-2 border-[#e63946] text-[#e63946] font-medium" 
                           onclick="showTab('general'); return false;">
                            Vispārīgie iestatījumi
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="#collaborators" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium"
                           onclick="showTab('collaborators'); return false;">
                            Sadarbības partneri
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="#notifications" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium"
                           onclick="showTab('notifications'); return false;">
                            Paziņojumi
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="#advanced" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium"
                           onclick="showTab('advanced'); return false;">
                            Paplašinātie iestatījumi
                        </a>
                    </li>
                    <li class="mr-2">
                        <a href="#activity" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium"
                            onclick="showTab('activity'); return false;">
                             Aktivitātes
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- General Settings Tab -->
            <div id="general-tab" class="settings-tab">
                <h2 class="text-xl font-bold mb-4">Projekta iestatījumi</h2>
                
                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                    <div class="mb-4">
                        <label for="board_name" class="block text-gray-700 font-medium mb-2">Projekta nosaukums</label>
                        <input type="text" id="board_name" name="board_name" 
                               value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                    </div>
                    
                    <div class="mb-4">
                        <label for="board_description" class="block text-gray-700 font-medium mb-2">Apraksts</label>
                        <textarea id="board_description" name="board_description" rows="4"
                                  class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"
                        ><?= htmlspecialchars($board_details['board_description'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="project_tags" class="block text-gray-700 font-medium mb-2">Tagi (atdalīti ar komatu)</label>
                        <input type="text" id="project_tags" name="project_tags" 
                               value="<?= htmlspecialchars($board_details['tags'] ?? '') ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_project" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">
                            Saglabāt izmaiņas
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Collaborators Tab -->
            <div id="collaborators-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Sadarbības partneri</h2>
                
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-3">Pievienot sadarbības partneri</h3>
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" class="flex flex-wrap gap-3 items-end">
                        <div class="flex-grow min-w-[200px]">
                            <label for="collaborator_email" class="block text-gray-700 font-medium mb-2">E-pasta adrese</label>
                            <input type="email" id="collaborator_email" name="collaborator_email" 
                                   placeholder="lietotajs@epasts.lv" required
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                        </div>
                        
                        <div class="min-w-[150px]">
                            <label for="permission_level" class="block text-gray-700 font-medium mb-2">Piekļuves līmenis</label>
                            <select id="permission_level" name="permission_level" 
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                                <option value="view">Skatīt</option>
                                <option value="edit">Rediģēt</option>
                                <option value="admin">Administrators</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" name="add_collaborator" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">
                                Pievienot
                            </button>
                        </div>
                    </form>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Esošie sadarbības partneri</h3>
                        <button onclick="openInvitationModal()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2">
                            <i class="fas fa-user-plus"></i> Uzaicināt
                        </button>
                    </div>
                    
                    <?php if (empty($collaborators)): ?>
                        <p class="text-gray-500">Nav pievienotu sadarbības partneru.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="py-3 px-4 text-left">Lietotājvārds</th>
                                        <th class="py-3 px-4 text-left">E-pasts</th>
                                        <th class="py-3 px-4 text-left">Piekļuve</th>
                                        <th class="py-3 px-4 text-right">Darbības</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($collaborators as $collab): ?>
                                        <tr class="border-t hover:bg-gray-50">
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['username']) ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['email']) ?></td>
                                            <td class="py-3 px-4">
                                                <?php 
                                                    switch ($collab['permission_level']) {
                                                        case 'view':
                                                            echo 'Skatīt';
                                                            break;
                                                        case 'edit':
                                                            echo 'Rediģēt';
                                                            break;
                                                        case 'admin':
                                                            echo 'Administrators';
                                                            break;
                                                    }
                                                ?>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" 
                                                      onsubmit="return confirm('Vai tiešām vēlaties noņemt šo sadarbības partneri?');">
                                                    <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                    <button type="submit" name="remove_collaborator" class="text-red-600 hover:underline">
                                                        Noņemt
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Invitation Modal -->
                <div id="invitationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                        <div class="flex justify-between items-center border-b px-6 py-4">
                            <h3 class="text-xl font-bold">Uzaicināt sadarbības partneri</h3>
                            <button onclick="closeInvitationModal()" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form method="post" action="send_invitation.php" class="p-6">
                            <input type="hidden" name="board_id" value="<?= $active_board_id ?>">
                            
                            <div class="mb-4">
                                <label for="email" class="block text-gray-700 font-medium mb-2">E-pasta adrese</label>
                                <input type="email" id="email" name="email" required
                                    placeholder="lietotajs@epasts.lv"
                                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                            </div>
                            
                            <div class="mb-4">
                                <label for="invitation_permission_level" class="block text-gray-700 font-medium mb-2">Piekļuves līmenis</label>
                                <select id="invitation_permission_level" name="permission_level"
                                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                                    <option value="view">Skatīt (lietotājs var tikai apskatīt projektu)</option>
                                    <option value="edit">Rediģēt (lietotājs var pievienot un rediģēt uzdevumus)</option>
                                    <option value="admin">Administrators (lietotājs var pārvaldīt projekta iestatījumus)</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="custom_message" class="block text-gray-700 font-medium mb-2">Personīgais ziņojums (neobligāts)</label>
                                <textarea id="custom_message" name="custom_message" rows="4" 
                                        placeholder="Pievienojiet personīgu ziņojumu uzaicinātajam lietotājam..."
                                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></textarea>
                            </div>
                            
                            <div class="mt-6 flex justify-end">
                                <button type="button" onclick="closeInvitationModal()" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg mr-3">
                                    Atcelt
                                </button>
                                <button type="submit" name="send_invitation" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">
                                    Nosūtīt ielūgumu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div id="notifications-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Paziņojumu iestatījumi</h2>
                
                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="notify_task_assignment" name="notify_task_assignment" class="mr-2 h-5 w-5" checked>
                            <label for="notify_task_assignment" class="text-gray-700">Jauns uzdevums piešķirts</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="notify_task_status" name="notify_task_status" class="mr-2 h-5 w-5" checked>
                            <label for="notify_task_status" class="text-gray-700">Uzdevuma statuss mainīts</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="notify_comments" name="notify_comments" class="mr-2 h-5 w-5" checked>
                            <label for="notify_comments" class="text-gray-700">Jauni komentāri</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="notify_deadline" name="notify_deadline" class="mr-2 h-5 w-5" checked>
                            <label for="notify_deadline" class="text-gray-700">Tuvojas termiņš</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="notify_collaborator" name="notify_collaborator" class="mr-2 h-5 w-5" checked>
                            <label for="notify_collaborator" class="text-gray-700">Jauns sadarbības partneris pievienots</label>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <h3 class="font-semibold mb-3">Paziņojumu kanāli</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="channel_email" name="channel_email" class="mr-2 h-5 w-5" checked>
                                <label for="channel_email" class="text-gray-700">E-pasts</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" id="channel_app" name="channel_app" class="mr-2 h-5 w-5" checked>
                                <label for="channel_app" class="text-gray-700">Lietotnes paziņojumi</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button type="submit" name="update_notifications" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">
                            Saglabāt paziņojumu iestatījumus
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Advanced Settings Tab -->
            <div id="advanced-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Paplašinātie iestatījumi</h2>
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Projekta eksportēšana</h3>
                    <div class="flex flex-wrap gap-3">
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-file-excel"></i> Eksportēt Excel
                        </button>
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-file-csv"></i> Eksportēt CSV
                        </button>
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-file-pdf"></i> Eksportēt PDF
                        </button>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Integrācijas</h3>
                    <div class="flex flex-wrap gap-3">
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fab fa-google"></i> Google kalendārs
                        </button>
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fab fa-slack"></i> Slack
                        </button>
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fab fa-github"></i> GitHub
                        </button>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-red-600">Bīstamā zona</h3>
                    <p class="text-gray-600 mb-4">Šīs darbības nevar atcelt. Lūdzu, rīkojieties uzmanīgi.</p>
                    
                    <div class="flex flex-wrap gap-3">
                        <button class="bg-yellow-100 text-yellow-800 hover:bg-yellow-200 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-archive"></i> Arhivēt projektu
                        </button>
                        <button class="bg-red-100 text-red-800 hover:bg-red-200 px-4 py-2 rounded-lg flex items-center gap-2"
                                onclick="return confirm('Vai tiešām vēlaties dzēst šo projektu? Šo darbību nevar atsaukt.')">
                            <i class="fas fa-trash-alt"></i> Dzēst projektu
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Log Tab -->
            <div id="activity-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Projekta aktivitātes</h2>
                
                <div class="mb-4 flex justify-between items-center">
                    <div>
                        <select id="activity-filter" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                            <option value="all">Visas aktivitātes</option>
                            <option value="tasks">Uzdevumi</option>
                            <option value="collaborators">Sadarbības partneri</option>
                            <option value="settings">Iestatījumi</option>
                        </select>
                    </div>
                    <div>
                        <button class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg flex items-center gap-2">
                            <i class="fas fa-calendar-alt"></i> Filtrēt pēc datuma
                        </button>
                    </div>
                </div>
                
                <?php
                // Create activity log table if it doesn't exist
                $sql_create_activity = "CREATE TABLE IF NOT EXISTS Planotajs_ActivityLog (
                    activity_id INT AUTO_INCREMENT PRIMARY KEY,
                    board_id INT NOT NULL,
                    user_id INT NOT NULL,
                    activity_type VARCHAR(50) NOT NULL,
                    activity_description TEXT NOT NULL,
                    related_entity_id INT NULL,
                    related_entity_type VARCHAR(50) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (board_id) REFERENCES Planotajs_Boards(board_id),
                    FOREIGN KEY (user_id) REFERENCES Planotajs_Users(user_id)
                )";
                $connection->query($sql_create_activity);
                
                // Get activity logs for this board
                $activities = [];
                if ($active_board_id > 0) {
                    $sql_activities = "SELECT a.*, u.username 
                                    FROM Planotajs_ActivityLog a
                                    JOIN Planotajs_Users u ON a.user_id = u.user_id
                                    WHERE a.board_id = ?
                                    ORDER BY a.created_at DESC
                                    LIMIT 50";
                    $stmt_activities = $connection->prepare($sql_activities);
                    $stmt_activities->bind_param("i", $active_board_id);
                    $stmt_activities->execute();
                    $result_activities = $stmt_activities->get_result();
                    while ($activity = $result_activities->fetch_assoc()) {
                        $activities[] = $activity;
                    }
                    $stmt_activities->close();
                }
                ?>
                
                <div class="bg-white border rounded-lg overflow-hidden">
                    <?php if (empty($activities)): ?>
                        <div class="p-8 text-center">
                            <i class="far fa-clock text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Nav aktivitāšu šajā projektā.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y">
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                // Determine icon based on activity type
                                $icon_class = 'fas fa-info-circle text-blue-500';
                                switch ($activity['activity_type']) {
                                    case 'task_created':
                                        $icon_class = 'fas fa-plus-circle text-green-500';
                                        break;
                                    case 'task_updated':
                                        $icon_class = 'fas fa-edit text-blue-500';
                                        break;
                                    case 'task_deleted':
                                        $icon_class = 'fas fa-trash-alt text-red-500';
                                        break;
                                    case 'task_completed':
                                        $icon_class = 'fas fa-check-circle text-green-500';
                                        break;
                                    case 'collaborator_added':
                                        $icon_class = 'fas fa-user-plus text-purple-500';
                                        break;
                                    case 'collaborator_removed':
                                        $icon_class = 'fas fa-user-minus text-orange-500';
                                        break;
                                    case 'settings_updated':
                                        $icon_class = 'fas fa-cog text-gray-500';
                                        break;
                                }
                                
                                // Format date
                                $activity_date = new DateTime($activity['created_at']);
                                $formatted_date = $activity_date->format('d.m.Y H:i');
                                ?>
                                
                                <div class="p-4 hover:bg-gray-50 flex items-start data-activity-type="<?= $activity['activity_type'] ?>">
                                    <div class="mr-4">
                                        <i class="<?= $icon_class ?> text-xl"></i>
                                    </div>
                                    <div class="flex-grow">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="font-semibold"><?= htmlspecialchars($activity['username']) ?></span>
                                                <span class="text-gray-600"><?= htmlspecialchars($activity['activity_description']) ?></span>
                                            </div>
                                            <div class="text-sm text-gray-500"><?= $formatted_date ?></div>
                                        </div>
                                        
                                        <?php if ($activity['related_entity_type'] == 'task' && $activity['related_entity_id']): ?>
                                            <div class="mt-2">
                                                <a href="view_task.php?task_id=<?= $activity['related_entity_id'] ?>" class="text-blue-500 hover:underline text-sm">
                                                    Skatīt uzdevumu
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($activities)): ?>
                    <div class="mt-4 text-center">
                        <button class="text-blue-600 hover:underline">Ielādēt vairāk</button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>



<script>
    // Tab switching functionality
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.remove('hidden');
        
        // Update active tab styling
        document.querySelectorAll('ul.flex a').forEach(link => {
            link.classList.remove('border-b-2', 'border-[#e63946]', 'text-[#e63946]');
            link.classList.add('text-gray-600', 'hover:text-[#e63946]');
        });
        
        // Set active tab
        const activeLink = document.querySelector(`a[href="#${tabName}"]`);
        activeLink.classList.remove('text-gray-600', 'hover:text-[#e63946]');
        activeLink.classList.add('border-b-2', 'border-[#e63946]', 'text-[#e63946]');
    }
    
    // Make sure Font Awesome is loaded
    if (!document.querySelector('link[href*="fontawesome"]')) {
        const fontAwesome = document.createElement('link');
        fontAwesome.rel = 'stylesheet';
        fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(fontAwesome);
    }

    function openInvitationModal() {
        document.getElementById('invitationModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
    
    function closeInvitationModal() {
        document.getElementById('invitationModal').classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
    }
    
    // Close modal when clicking outside
    document.getElementById('invitationModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeInvitationModal();
        }
    });

    // Activity filtering functionality
    document.getElementById('activity-filter').addEventListener('change', function() {
        const filter = this.value;
        const activities = document.querySelectorAll('[data-activity-type]');
        
        if (filter === 'all') {
            activities.forEach(activity => {
                activity.style.display = '';
            });
        } else {
            activities.forEach(activity => {
                if (activity.dataset.activityType.includes(filter)) {
                    activity.style.display = '';
                } else {
                    activity.style.display = 'none';
                }
            });
        }
    });
</script>