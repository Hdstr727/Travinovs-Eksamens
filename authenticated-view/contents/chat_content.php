<?php
// File: contents/chat_content.php
// This file contains all the logic and interface for the chat feature

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Create a debugging function
function debug_chat_issues() {
    global $connection, $user_id, $board_id;
    
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">';
    echo '<h3 class="font-bold">Debug Information:</h3>';
    
    // Check if user is logged in
    echo '<p>User ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in') . '</p>';
    
    // Check board_id parameter
    echo '<p>Board ID from URL: ' . (isset($_GET['board_id']) ? $_GET['board_id'] : 'Not provided') . '</p>';
    echo '<p>Parsed Board ID: ' . (isset($board_id) ? $board_id : 'Not set') . '</p>';
    
    // If board_id exists, check board existence
    if (isset($board_id) && $board_id > 0) {
        $board_sql = "SELECT board_id, board_name, user_id, is_deleted FROM Planotajs_Boards WHERE board_id = ?";
        $board_stmt = $connection->prepare($board_sql);
        $board_stmt->bind_param("i", $board_id);
        $board_stmt->execute();
        $board_result = $board_stmt->get_result();
        
        if ($board_result->num_rows > 0) {
            $board = $board_result->fetch_assoc();
            echo '<p>Board exists: Yes</p>';
            echo '<p>Board name: ' . htmlspecialchars($board['board_name']) . '</p>';
            echo '<p>Board owner ID: ' . $board['user_id'] . '</p>';
            echo '<p>Board is_deleted: ' . ($board['is_deleted'] ? 'Yes (Problem!)' : 'No') . '</p>';
            
            // Check if current user is owner
            $is_owner = ($board['user_id'] == $user_id);
            echo '<p>Current user is owner: ' . ($is_owner ? 'Yes' : 'No') . '</p>';
            
            // If not owner, check if collaborator
            if (!$is_owner) {
                $collab_sql = "SELECT permission_level FROM Planotajs_Collaborators 
                              WHERE board_id = ? AND user_id = ?";
                $collab_stmt = $connection->prepare($collab_sql);
                $collab_stmt->bind_param("ii", $board_id, $user_id);
                $collab_stmt->execute();
                $collab_result = $collab_stmt->get_result();
                
                if ($collab_result->num_rows > 0) {
                    $collab = $collab_result->fetch_assoc();
                    echo '<p>User is collaborator: Yes (Permission: ' . $collab['permission_level'] . ')</p>';
                } else {
                    echo '<p>User is collaborator: No (Problem!)</p>';
                }
                $collab_stmt->close();
            }
        } else {
            echo '<p><strong>Board does not exist in database!</strong></p>';
        }
        $board_stmt->close();
    }
    
    echo '</div>';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: core/login.php");
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

$user_id = $_SESSION['user_id'];
$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;

// Call the debug function to display diagnostic information
debug_chat_issues();

// Update page title if board_id is valid
if ($board_id > 0) {
    $board_sql = "SELECT board_name FROM Planotajs_Boards WHERE board_id = ? AND is_deleted = 0";
    $board_stmt = $connection->prepare($board_sql);
    $board_stmt->bind_param("i", $board_id);
    $board_stmt->execute();
    $board_result = $board_stmt->get_result();
    
    if ($board_result->num_rows > 0) {
        $board = $board_result->fetch_assoc();
        $title = "Chat - " . htmlspecialchars($board['board_name']);
    } else {
        header("Location: index.php");
        exit();
    }
    $board_stmt->close();
}

// Define variables for navigation
$kanban_url = $board_id > 0 ? "kanban.php?board_id=$board_id" : "index.php";
$chat_url = $board_id > 0 ? "chat.php?board_id=$board_id" : "index.php";

// Check if user has access to this board (either as owner or collaborator)
if ($board_id > 0) {
    $access_sql = "SELECT b.board_id, b.board_name, b.user_id 
                  FROM Planotajs_Boards b
                  LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id 
                  WHERE b.board_id = ? 
                  AND (b.user_id = ? OR c.user_id = ?)
                  AND b.is_deleted = 0";
    
    $access_stmt = $connection->prepare($access_sql);
    $access_stmt->bind_param("iii", $board_id, $user_id, $user_id);
    $access_stmt->execute();
    $access_result = $access_stmt->get_result();
    
    if ($access_result->num_rows == 0) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p>Board not found or you don\'t have permission to access it.</p>
              </div>';
        exit();
    }
    
    $access_stmt->close();
}

// Check user's permission level for this board
$permission_level = 'read'; // Default to read-only
if ($board_id > 0) {
    // Check if user is the owner (full permissions)
    $owner_check_sql = "SELECT user_id FROM Planotajs_Boards WHERE board_id = ? AND user_id = ?";
    $owner_check_stmt = $connection->prepare($owner_check_sql);
    $owner_check_stmt->bind_param("ii", $board_id, $user_id);
    $owner_check_stmt->execute();
    $owner_result = $owner_check_stmt->get_result();
    
    if ($owner_result->num_rows > 0) {
        $permission_level = 'owner'; // User is the owner
    } else {
        // Check collaborator permission level
        $collab_sql = "SELECT permission_level FROM Planotajs_Collaborators 
                      WHERE board_id = ? AND user_id = ?";
        $collab_stmt = $connection->prepare($collab_sql);
        $collab_stmt->bind_param("ii", $board_id, $user_id);
        $collab_stmt->execute();
        $collab_result = $collab_stmt->get_result();
        
        if ($collab_result->num_rows > 0) {
            $collab = $collab_result->fetch_assoc();
            $permission_level = $collab['permission_level'];
        }
        $collab_stmt->close();
    }
    $owner_check_stmt->close();
}

// Get username of current user
$username_sql = "SELECT username, full_name FROM Planotajs_Users WHERE user_id = ?";
$username_stmt = $connection->prepare($username_sql);
$username_stmt->bind_param("i", $user_id);
$username_stmt->execute();
$username_result = $username_stmt->get_result();
$user_data = $username_result->fetch_assoc();
$current_username = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
$username_stmt->close();

// Display board header if board_id is valid
if ($board_id > 0): ?>
    <div class="flex justify-between items-center border-b pb-6 mb-8">
        <h2 class="text-3xl font-bold text-[#e63946]">
            <?= isset($title) ? htmlspecialchars($title) : "Chat" ?>
        </h2>
        <a href="<?= $kanban_url ?>" class="bg-[#e63946] text-white py-3 px-6 rounded-lg font-semibold hover:bg-red-700 transition">
            Back to Kanban
        </a>
    </div>
<?php endif; ?>

<?php if ($board_id > 0): ?>
    <div class="chat-container bg-white shadow-lg rounded-lg p-4 mb-8">
        <div class="chat-header flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-[#e63946]">Chat</h3>
            <button id="toggleChatBtn" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
        
        <div id="chatContent" class="chat-content">
            <div id="chatMessages" class="chat-messages h-64 overflow-y-auto p-2 mb-4 bg-gray-50 rounded-lg">
                <!-- Messages will be loaded here via AJAX -->
                <div class="text-center text-gray-500 p-4">Loading messages...</div>
            </div>
            
            <form id="chatForm" class="chat-form">
                <div class="flex items-center">
                    <input type="text" id="messageInput" class="flex-grow p-2 border rounded-l-lg" placeholder="Type your message..." <?= ($permission_level == 'read') ? 'disabled' : '' ?>>
                    <button type="submit" class="bg-[#e63946] text-white p-2 rounded-r-lg" <?= ($permission_level == 'read') ? 'disabled' : '' ?>>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                        </svg>
                    </button>
                </div>
                <?php if ($permission_level == 'read'): ?>
                    <div class="text-xs text-gray-500 mt-2">You have read-only access and cannot send messages.</div>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="bg-white shadow-md rounded-lg p-8">
        <div class="text-center">
            <h3 class="text-xl font-semibold text-[#e63946] mb-4">No Board Selected</h3>
            <p class="text-gray-600 mb-6">Please select a board from your dashboard to view its chat.</p>
            <a href="index.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg hover:bg-red-700 transition">
                Go to Dashboard
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Make sure jQuery is loaded before this script -->
<script>
    $(document).ready(function() {
        // Variables
        const boardId = <?= $board_id ? $board_id : 0 ?>; // Make sure it's 0 if not set
        const userId = <?= $user_id ? $user_id : 0 ?>; // Make sure it's 0 if not set
        const currentUsername = "<?= isset($current_username) ? $current_username : 'User' ?>";
        let isMinimized = false;
        let lastMessageId = 0;
        
        // Don't run chat functionality if no board selected
        if (boardId <= 0) {
            return;
        }
        
        // Toggle chat visibility
        $("#toggleChatBtn").click(function() {
            isMinimized = !isMinimized;
            if (isMinimized) {
                $("#chatContent").hide();
                $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>');
            } else {
                $("#chatContent").show();
                $(this).html('<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>');
                scrollToBottom();
            }
        });
        
        // Load initial messages
        loadMessages();
        
        // Set up polling for new messages every 5 seconds
        setInterval(function() {
            loadMessages();
        }, 5000);
        
        // Handle form submission
        $("#chatForm").submit(function(e) {
            e.preventDefault();
            const message = $("#messageInput").val().trim();
            
            if (message) {
                sendMessage(message);
                $("#messageInput").val('');
            }
        });
        
        // Function to load messages
        function loadMessages() {
            $.ajax({
                url: 'ajax_handlers/get_messages.php',
                type: 'GET',
                data: {
                    board_id: boardId,
                    last_id: lastMessageId
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        
                        if (result.success) {
                            if (result.messages.length > 0) {
                                // Clear loading message if present
                                if ($("#chatMessages").find(".text-center.text-gray-500").length > 0) {
                                    $("#chatMessages").empty();
                                }
                                
                                // Add new messages
                                const wasAtBottom = isScrolledToBottom();
                                
                                result.messages.forEach(message => {
                                    appendMessage(message);
                                    lastMessageId = Math.max(lastMessageId, message.message_id);
                                });
                                
                                // If user was at the bottom before new messages, scroll down
                                if (wasAtBottom) {
                                    scrollToBottom();
                                }
                            } else if ($("#chatMessages").text().includes("Loading messages...")) {
                                // If this is the first load and there are no messages
                                $("#chatMessages").html('<div class="text-center text-gray-500 p-4">No messages yet. Start the conversation!</div>');
                            }
                        } else {
                            console.error('Error loading messages:', result.message);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        $("#chatMessages").html('<div class="text-center text-red-500 p-4">Error loading messages. Please refresh the page.</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error connecting to server:', status, error);
                    $("#chatMessages").html('<div class="text-center text-red-500 p-4">Connection error. Please check your internet connection.</div>');
                }
            });
        }
        
        // Function to send a message
        function sendMessage(messageText) {
            $.ajax({
                url: 'ajax_handlers/send_message.php',
                type: 'POST',
                data: {
                    board_id: boardId,
                    message: messageText
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        
                        if (result.success) {
                            // Message was sent successfully, it will be picked up by the next loadMessages call
                            // But for immediate feedback, we'll add it now
                            appendMessage({
                                message_id: result.message_id,
                                user_id: userId,
                                username: currentUsername,
                                message_text: messageText,
                                created_at: new Date().toISOString(),
                                is_own: true
                            });
                            
                            scrollToBottom();
                        } else {
                            console.error('Error sending message:', result.message);
                            alert('Failed to send message: ' + result.message);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('Error sending message. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error connecting to server:', status, error);
                    alert('Connection error. Please check your internet connection and try again.');
                }
            });
        }
        
        // Function to append a message to the chat window
        function appendMessage(message) {
            const isOwnMessage = message.user_id == userId;
            const alignClass = isOwnMessage ? 'justify-end' : 'justify-start';
            const bubbleClass = isOwnMessage ? 'bg-[#e63946] text-white' : 'bg-gray-200 text-gray-800';
            
            const messageHtml = `
                <div class="flex ${alignClass} mb-3" id="msg-${message.message_id}">
                    <div class="flex flex-col max-w-3/4">
                        <div class="text-xs text-gray-500 ${isOwnMessage ? 'text-right' : 'text-left'} mb-1">
                            ${message.username} · ${formatTimestamp(message.created_at)}
                        </div>
                        <div class="${bubbleClass} px-4 py-2 rounded-lg break-words">
                            ${message.message_text}
                        </div>
                    </div>
                </div>`;
            
            // Check if message already exists to avoid duplicates
            if ($(`#msg-${message.message_id}`).length === 0) {
                $("#chatMessages").append(messageHtml);
            }
        }
        
        // Function to format timestamp
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            
            const isToday = date >= today;
            const isYesterday = date >= yesterday && date < today;
            
            if (isToday) {
                return `Today at ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
            } else if (isYesterday) {
                return `Yesterday at ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
            } else {
                return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
            }
        }
        
        // Function to check if scroll is at the bottom
        function isScrolledToBottom() {
            const element = document.getElementById('chatMessages');
            return element.scrollHeight - element.clientHeight <= element.scrollTop + 50;
        }
        
        // Function to scroll to the bottom of the chat
        function scrollToBottom() {
            const element = document.getElementById('chatMessages');
            element.scrollTop = element.scrollHeight;
        }
    });
</script>