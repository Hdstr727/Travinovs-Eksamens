
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar with projects list -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div>
            <h2 class="text-xl font-bold mb-2">My Projects</h2>
            <?php if (empty($owned_boards)): ?>
                <p class="text-gray-500 text-sm mb-4">No projects created by you. <a href="index.php?action=create_board" class="text-blue-600 hover:underline">Create one now?</a></p>
            <?php else: ?>
                <div class="space-y-2 mb-4">
                    <?php foreach ($owned_boards as $board_item): ?>
                        <a href="project_settings.php?board_id=<?= $board_item['board_id'] ?>" 
                           class="block p-3 rounded-lg hover:bg-gray-100 <?= ($board_item['board_id'] == $active_board_id) ? 'bg-gray-100 border-l-4 border-[#e63946]' : '' ?> <?= ($board_item['is_archived'] ?? 0) ? 'opacity-60' : '' ?>">
                            <?= htmlspecialchars($board_item['board_name']) ?>
                            <?php if ($board_item['is_archived'] ?? 0): ?><span class="text-xs text-gray-500 ml-1">(Archived)</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <hr class="my-4">
        <div>
            <h2 class="text-xl font-bold mb-2">Projects Shared With Me</h2>
            <?php if (empty($shared_boards)): ?>
                <p class="text-gray-500 text-sm">No projects currently shared with you.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($shared_boards as $board_item): ?>
                        <a href="project_settings.php?board_id=<?= $board_item['board_id'] ?>" 
                           class="block p-3 rounded-lg hover:bg-gray-100 <?= ($board_item['board_id'] == $active_board_id) ? 'bg-gray-100 border-l-4 border-[#e63946]' : '' ?> <?= ($board_item['is_archived'] ?? 0) ? 'opacity-60' : '' ?>">
                            <?= htmlspecialchars($board_item['board_name']) ?>
                            <span class="text-xs text-gray-500 ml-1">(<?= ucfirst(htmlspecialchars($board_item['permission_level'])) ?>)</span>
                            <?php if ($board_item['is_archived'] ?? 0): ?><span class="text-xs text-gray-500 ml-1">(Archived)</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="mt-6"><a href="index.php" class="text-blue-600 hover:underline flex items-center gap-2"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
    </div>
    
    <!-- Main settings content -->
    <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-3">
        <?php if (empty($active_board_id) && (empty($owned_boards) && empty($shared_boards))): ?>
            <div class="text-center py-8">
                <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">No Projects Found</h3>
                <p class="text-gray-500 mt-2">You are not part of any projects yet. Go to the <a href="index.php?action=create_board" class="text-blue-600 hover:underline">Dashboard to create one</a>.</p>
            </div>
        <?php elseif (empty($active_board_id) && (!empty($owned_boards) || !empty($shared_boards))): ?>
             <div class="text-center py-8">
                <i class="fas fa-mouse-pointer text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">Select a project</h3>
                <p class="text-gray-500 mt-2">Choose a project from the sidebar to view or manage its settings.</p>
            </div>
        <?php elseif ($active_board_id > 0 && (empty($board_details) || empty($current_user_permission_on_active_board)) ): ?>
             <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">Project Access Error</h3>
                <p class="text-gray-500 mt-2"><?= htmlspecialchars($error ?: "The selected project could not be found or you do not have permission to access its settings.") ?></p>
            </div>
        <?php elseif ($board_details && $current_user_permission_on_active_board): ?>
            <?php if ($message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="mb-6 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px" id="settings-tabs-nav">
                    <li class="mr-2"><a href="#general" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('general', this); return false;">General Settings</a></li>
                    
                    <?php if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin'): ?>
                        <li class="mr-2"><a href="#collaborators" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('collaborators', this); return false;">Collaborators</a></li>
                    <?php endif; ?>
                    
                    <li class="mr-2"><a href="#notifications" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('notifications', this); return false;">Notifications</a></li>
                    
                    <?php
                    $show_activity_log_tab_link = false;
                    if ($current_user_permission_on_active_board == 'owner') {
                        $show_activity_log_tab_link = true;
                    } elseif ($board_details) { 
                        $role_for_tab_check = $current_user_permission_on_active_board;
                        if ($role_for_tab_check == 'admin' && $log_access_admin_setting) {
                            $show_activity_log_tab_link = true;
                        } elseif ($role_for_tab_check == 'edit' && $log_access_edit_setting) {
                            $show_activity_log_tab_link = true;
                        } elseif ($role_for_tab_check == 'view' && $log_access_view_setting) {
                            $show_activity_log_tab_link = true;
                        }
                    }
                    ?>
                    <?php if ($show_activity_log_tab_link): ?>
                         <li class="mr-2"><a href="#activity" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('activity', this); return false;">Activity Log</a></li>
                    <?php endif; ?>

                    <?php if ($current_user_permission_on_active_board == 'owner'): ?>
                        <li class="mr-2"><a href="#advanced" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('advanced', this); return false;">Advanced Settings</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- General Tab -->
            <div id="general-tab" class="settings-tab">
                <h2 class="text-xl font-bold mb-4">Project Settings for "<?= htmlspecialchars($board_details['board_name']) ?>"</h2>
                <?php if ($board_details['is_archived']): ?><div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-archive mr-2"></i>This project is currently archived. Some functionalities might be limited.</p></div><?php endif; ?>
                
                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                    <?php $can_edit_general = ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin'); ?>
                    <div class="mb-4"><label for="board_name" class="block text-gray-700 font-medium mb-2">Project Name</label><input type="text" id="board_name" name="board_name" value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>" required <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"></div>
                    <div class="mb-4"><label for="board_description" class="block text-gray-700 font-medium mb-2">Description</label><textarea id="board_description" name="board_description" rows="4" <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"><?= htmlspecialchars($board_details['board_description'] ?? '') ?></textarea></div>
                    <div class="mb-4"><label for="project_tags" class="block text-gray-700 font-medium mb-2">Tags (comma-separated)</label><input type="text" id="project_tags" name="project_tags" value="<?= htmlspecialchars($board_details['tags'] ?? '') ?>" placeholder="e.g., work, personal, urgent" <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"></div>
                    <?php if ($can_edit_general && !($board_details['is_archived'] ?? 0)): ?>
                    <div class="flex justify-end"><button type="submit" name="update_project" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Save Changes</button></div>
                    <?php endif; ?>
                </form>

                <?php if ($current_user_permission_on_active_board && $current_user_permission_on_active_board !== 'owner' && !($board_details['is_archived'] ?? 0) ): ?>
                    <hr class="my-8 border-gray-300">
                    <div class="mt-6 p-4 border border-red-300 rounded-lg bg-red-50">
                        <h3 class="text-lg font-semibold mb-2 text-red-700">Leave Project</h3> 
                        <p class="text-sm text-gray-600 mb-3">If you leave this project, you will lose access to its content and will need to be invited again to rejoin.</p>
                        <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" 
                              onsubmit="return confirm('Are you sure you want to leave the project \"<?= htmlspecialchars($board_details['board_name']) ?>\"? You will lose access.');">
                            <input type="hidden" name="action" value="leave_project">
                            <button type="submit" name="leave_project_submit" class="bg-red-500 text-white px-6 py-2 rounded-lg hover:bg-red-600 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2"></i>Leave Project
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Collaborators Tab -->
            <?php if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin'): ?>
            <div id="collaborators-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Manage Collaborators</h2>
                <?php if ($board_details['is_archived']): ?> <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Collaborator management is disabled for archived projects.</p></div> <?php endif; ?>
                
                <div class="mb-8 p-4 border rounded-lg bg-gray-50 <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Invite New Collaborator</h3>
                     <button onclick="openInvitationModal()" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 w-full sm:w-auto justify-center"><i class="fas fa-user-plus"></i> Send Invitation</button>
                </div>
                
                <div class="<?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Current Collaborators</h3>
                    <?php if (empty($collaborators)): ?>
                        <p class="text-gray-500">No active collaborators on this project yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead><tr class="bg-gray-100"><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Username</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Email</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Permission</th><th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($collaborators as $collab): ?>
                                        <?php
                                        $is_this_collaborator_the_board_owner = ($collab['collaborator_user_id'] == $board_details['user_id']);
                                        $can_logged_in_user_manage_this_collaborator = false;
                                        if ($current_user_permission_on_active_board == 'owner' && !$is_this_collaborator_the_board_owner) {
                                            $can_logged_in_user_manage_this_collaborator = true;
                                        } elseif ($current_user_permission_on_active_board == 'admin') {
                                            if (!$is_this_collaborator_the_board_owner && 
                                                $collab['permission_level'] != 'admin' && 
                                                $collab['collaborator_user_id'] != $user_id 
                                            ) {
                                                $can_logged_in_user_manage_this_collaborator = true;
                                            }
                                        }
                                        ?>
                                        <tr class="border-t hover:bg-gray-50">
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['username']) ?> <?= $is_this_collaborator_the_board_owner ? '<span class="text-xs text-green-600 font-semibold">(Owner)</span>' : '' ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['email']) ?></td>
                                            <td class="py-3 px-4">
                                                <?php if ($is_this_collaborator_the_board_owner): ?>
                                                    <span class="px-2 py-1 text-sm text-gray-700 font-medium">Admin (Owner)</span>
                                                <?php elseif ($can_logged_in_user_manage_this_collaborator): ?>
                                                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" class="flex items-center gap-2">
                                                        <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                        <select name="new_permission_level" class="px-2 py-1 border rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-[#e63946] w-auto">
                                                            <option value="view" <?= ($collab['permission_level'] == 'view') ? 'selected' : '' ?>>View</option>
                                                            <option value="edit" <?= ($collab['permission_level'] == 'edit') ? 'selected' : '' ?>>Edit</option>
                                                            <?php if ($current_user_permission_on_active_board == 'owner'): ?>
                                                            <option value="admin" <?= ($collab['permission_level'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                                            <?php endif; ?>
                                                        </select>
                                                        <button type="submit" name="update_collaborator_permission" class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">Save</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-sm text-gray-700"><?= ucfirst(htmlspecialchars($collab['permission_level'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <?php if ($can_logged_in_user_manage_this_collaborator && !$is_this_collaborator_the_board_owner): ?>
                                                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" 
                                                      onsubmit="return confirm('Are you sure you want to remove <?= htmlspecialchars($collab['username']) ?> from this project?');" style="display: inline;">
                                                    <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                    <button type="submit" name="remove_collaborator" class="text-red-600 hover:underline text-sm">Remove</button>
                                                </form>
                                                <?php else: echo '-'; endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $pending_invitations = [];
                if ($active_board_id > 0 && $board_details && ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin') ) {
                    $sql_pending = "SELECT i.invitation_id, i.permission_level, i.custom_message, i.created_at, u.username as invited_username, u.email as invited_email
                                    FROM Planner_Invitations i
                                    JOIN Planner_Users u ON i.invited_user_id = u.user_id
                                    WHERE i.board_id = ? AND i.status = 'pending'";
                    $stmt_pending = $connection->prepare($sql_pending);
                    $stmt_pending->bind_param("i", $active_board_id);
                    $stmt_pending->execute();
                    $result_pending = $stmt_pending->get_result();
                    while ($row = $result_pending->fetch_assoc()) { $pending_invitations[] = $row; }
                    $stmt_pending->close();
                }
                ?>
                <?php if (!empty($pending_invitations)): ?>
                <div class="mt-8 <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Pending Invitations</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border">
                            <thead><tr class="bg-gray-100"><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Username</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Email</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Permission</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Sent</th><th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pending_invitations as $invite): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="py-3 px-4"><?= htmlspecialchars($invite['invited_username']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($invite['invited_email']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars(ucfirst($invite['permission_level'])) ?></td>
                                        <td class="py-3 px-4 text-xs text-gray-500"><?= date('M d, Y', strtotime($invite['created_at'])) ?></td>
                                        <td class="py-3 px-4 text-right"><button onclick="cancelInvitation(<?= $invite['invitation_id'] ?>)" class="text-red-600 hover:underline text-sm">Cancel</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <div id="invitationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-auto">
                        <div class="flex justify-between items-center border-b px-6 py-4"><h3 class="text-xl font-bold">Invite Collaborator</h3><button onclick="closeInvitationModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button></div>
                        <form id="sendInvitationForm" method="post" action="contents/send_invitation.php" class="p-6">
                            <input type="hidden" name="board_id" value="<?= $active_board_id ?>">
                            <input type="hidden" name="board_name" value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>">
                            <div class="mb-4"><label for="invitation_email" class="block text-gray-700 font-medium mb-2">Email Address</label><input type="email" id="invitation_email" name="email" required placeholder="user@example.com" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></div>
                            <div class="mb-4">
                                <label for="invitation_permission_level" class="block text-gray-700 font-medium mb-2">Permission Level</label>
                                <select id="invitation_permission_level" name="permission_level" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                                    <option value="view">View</option>
                                    <option value="edit">Edit</option>
                                    <?php if ($current_user_permission_on_active_board == 'owner'): ?>
                                    <option value="admin">Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-4"><label for="custom_message" class="block text-gray-700 font-medium mb-2">Personal Message (Optional)</label><textarea id="custom_message" name="custom_message" rows="3" placeholder="Add a personal message..." class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></textarea></div>
                            <div class="mt-6 flex justify-end"><button type="button" onclick="closeInvitationModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg mr-3">Cancel</button><button type="submit" name="send_invitation_via_modal" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Send Invitation</button></div>
                            <div id="invitationStatus" class="mt-4"></div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notifications Tab -->
            <div id="notifications-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">My Notification Settings for "<?= htmlspecialchars($board_details['board_name']) ?>"</h2>
                <?php if ($notification_settings['source'] === 'global'): ?> <div class="mb-4 p-3 bg-blue-100 border-l-4 border-blue-500 text-blue-700"><p><i class="fas fa-info-circle mr-2"></i>Using your <strong>global default</strong> notification preferences. Changes saved here will be specific to "<?= htmlspecialchars($board_details['board_name']) ?>".</p></div>
                <?php elseif ($notification_settings['source'] === 'default' || $notification_settings['source'] === 'default_no_board'): ?> <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Using <strong>system default</strong> notification preferences. Changes saved here will be specific to "<?= htmlspecialchars($board_details['board_name']) ?>".</p></div>
                <?php endif; ?>
                <?php if ($board_details['is_archived']): ?> <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Notification settings are disabled for archived projects.</p></div> <?php endif; ?>

                <div class="<?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>#notifications">
                        <h3 class="font-semibold mb-3 text-lg">Notify me when:</h3>
                        <p class="text-sm text-gray-600 mb-4">Select which activities on this board should trigger a notification for you.</p>
                        <div class="space-y-4">
                            <?php $event_notifications_map = [ 
                                'notify_task_created' => 'A new task is created on this board',
                                'notify_task_assignment' => 'A task is assigned to me or I am @mentioned in a task',
                                'notify_task_status_changed' => 'Status of my task or a task I follow changes',
                                'notify_task_updated' => 'Details of my task or a task I follow are updated',
                                'notify_new_comment' => "New comments on tasks I'm involved with or @mentioned in",
                                'notify_collaborator_added' => 'A new collaborator joins this board',
                                'notify_task_deleted' => 'A task is deleted from this board',
                                'notify_new_chat_message' => 'New chat messages related to this board (if applicable)',
                                'notify_column_changes' => 'Board structure changes (columns added/edited/deleted)',
                                'notify_project_management' => 'Project management updates (e.g., invitations, board settings changes)',
                            ];
                            foreach ($event_notifications_map as $key => $label): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="<?= $key ?>" name="<?= $key ?>" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" <?= (isset($notification_settings[$key]) && $notification_settings[$key] == 1) ? 'checked' : '' ?>>
                                <label for="<?= $key ?>" class="text-gray-700"><?= htmlspecialchars($label) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex justify-end mt-8">
                            <button type="submit" name="update_notification_settings_submit" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Save Notification Settings</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Advanced Tab (OWNER ONLY) -->
            <?php if ($current_user_permission_on_active_board == 'owner'): ?>
            <div id="advanced-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Advanced Settings</h2>
                
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-1">Activity Log Visibility for Collaborator Roles</h4>
                    <p class="text-sm text-gray-600 mb-3">Control which collaborator roles can view this project's activity log. Owners always have access.</p>
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                        <input type="hidden" name="action_update_log_visibility" value="1">
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="log_access_roles[admin]" value="1"
                                       class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946] border-gray-300"
                                       <?= $log_access_admin_setting ? 'checked' : '' ?>
                                       <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>
                                <span class="ml-2 text-gray-700">Allow 'Admin' collaborators to view activity log.</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="log_access_roles[edit]" value="1"
                                       class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946] border-gray-300"
                                       <?= $log_access_edit_setting ? 'checked' : '' ?>
                                       <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>
                                <span class="ml-2 text-gray-700">Allow 'Edit' collaborators to view activity log.</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="log_access_roles[view]" value="1"
                                       class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946] border-gray-300"
                                       <?= $log_access_view_setting ? 'checked' : '' ?>
                                       <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>
                                <span class="ml-2 text-gray-700">Allow 'View' collaborators to view activity log.</span>
                            </label>
                        </div>
                        <?php if (!($board_details['is_archived'] ?? 0)): ?>
                        <div class="mt-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Save Log Visibility</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <hr class="my-8 border-gray-300">

                <div class="p-4 border border-red-300 rounded-lg bg-red-50">
                   <h3 class="text-lg font-semibold mb-3 text-red-700">Danger Zone</h3>
                    <p class="text-gray-700 mb-4">These actions may be irreversible. Please proceed with caution.</p>
                    <div class="flex flex-wrap gap-3">
                        <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" onsubmit="return confirm('Are you sure you want to <?= ($board_details['is_archived'] ?? 0) ? 'unarchive' : 'archive' ?> this project?');" class="inline-block">
                            <?php if ($board_details['is_archived'] ?? 0): ?> <button type="submit" name="unarchive_project" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-undo"></i> Unarchive Project</button>
                            <?php else: ?> <button type="submit" name="archive_project" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-archive"></i> Archive Project</button> <?php endif; ?>
                        </form>
                        <button type="button" 
                                onclick="openDeleteProjectModal('<?= htmlspecialchars(addslashes($board_details['board_name']), ENT_QUOTES) ?>', <?= $active_board_id ?>)" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center gap-2" 
                                <?= ($board_details['is_archived'] ?? 0) ? 'disabled title="Unarchive project first to delete"' : '' ?>>
                            <i class="fas fa-trash-alt"></i> Delete Project
                        </button>
                    </div>
                    <?php if ($board_details['is_archived'] ?? 0): ?><p class="text-sm text-yellow-700 mt-2">Note: To delete this project, you must unarchive it first.</p><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Delete Project Confirmation Modal -->
            <div id="deleteProjectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-auto">
                    <div class="flex justify-between items-center border-b px-6 py-4">
                        <h3 class="text-xl font-bold text-red-700">Delete Project</h3>
                        <button onclick="closeDeleteProjectModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                    </div>
                    <form id="deleteProjectForm" method="post" action="project_settings.php"> 
                        <div class="p-6 space-y-4">
                            <p class="text-sm text-gray-700">
                                This action is <strong class="font-semibold">irreversible</strong> and will permanently delete the project
                                "<strong id="deleteProjectNameConfirm" class="font-semibold"></strong>", including all its columns, tasks, and associated data.
                            </p>
                            <p class="text-sm text-gray-700">
                                To confirm deletion, please type the project name 
                                "<strong id="deleteProjectNameType" class="font-semibold text-red-600"></strong>" in the box below.
                            </p>
                            <div>
                                <label for="confirmProjectNameInput" class="sr-only">Confirm Project Name</label>
                                <input type="text" id="confirmProjectNameInput" name="confirm_project_name"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                    placeholder="Type project name here">
                                <p id="deleteErrorText" class="text-xs text-red-500 mt-1 hidden"></p>
                            </div>
                            <input type="hidden" name="board_id_to_delete" id="boardIdToDelete" value="">
                            <input type="hidden" name="delete_project" value="1">
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end space-x-3">
                            <button type="button" onclick="closeDeleteProjectModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
                                Cancel
                            </button>
                            <button type="submit" id="confirmDeleteProjectButton"
                                    class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled> 
                                Delete Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Log Tab -->
            <?php
            $can_view_project_activity_log = false;
            if ($current_user_permission_on_active_board == 'owner') {
                $can_view_project_activity_log = true;
            } else if ($board_details) { 
                $role_tab = $current_user_permission_on_active_board;
                if ($role_tab == 'admin' && $log_access_admin_setting) {
                    $can_view_project_activity_log = true;
                } elseif ($role_tab == 'edit' && $log_access_edit_setting) {
                    $can_view_project_activity_log = true;
                } elseif ($role_tab == 'view' && $log_access_view_setting) {
                    $can_view_project_activity_log = true;
                }
            }
            ?>
            <?php if ($can_view_project_activity_log): ?>
            <div id="activity-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Project Activity Log</h2>
                 <?php
                $activities_project = []; 
                if ($active_board_id > 0) {
                    $sql_activities_fetch_project = "SELECT a.*, u.username 
                                       FROM Planner_ActivityLog a 
                                       JOIN Planner_Users u ON a.user_id = u.user_id 
                                       WHERE a.board_id = ? 
                                       ORDER BY a.created_at DESC LIMIT 50";
                    $stmt_activities_fetch_project = $connection->prepare($sql_activities_fetch_project);
                    if ($stmt_activities_fetch_project) {
                        $stmt_activities_fetch_project->bind_param("i", $active_board_id);
                        $stmt_activities_fetch_project->execute();
                        $result_activities_fetch_project = $stmt_activities_fetch_project->get_result();
                        while ($activity_row_project = $result_activities_fetch_project->fetch_assoc()) {
                            $activities_project[] = $activity_row_project;
                        }
                        $stmt_activities_fetch_project->close();
                    } else {
                        if (!$error) $error = "Error preparing project activity log query.";
                    }
                }
                ?>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <?php if (empty($activities_project)): ?>
                        <div class="p-8 text-center">
                            <i class="far fa-clock text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No activity recorded for this project yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($activities_project as $activity_item): ?>
                                <?php
                                $icon_class = 'fas fa-info-circle text-blue-500'; 
                                $activity_type_key = strtolower($activity_item['activity_type']);
                                $icon_map = [ 
                                    'task_created' => 'fas fa-plus-circle text-green-500', 'task_updated' => 'fas fa-edit text-blue-500', 
                                    'task_deleted' => 'fas fa-trash-alt text-red-500', 'task_moved' => 'fas fa-arrows-alt text-indigo-500', 
                                    'task_completed' => 'fas fa-check-circle text-green-600', 'task_reopened'  => 'fas fa-undo text-yellow-500', 
                                    'comment_added'  => 'fas fa-comment text-gray-500', 'collaborator_added' => 'fas fa-user-plus text-purple-500', 
                                    'collaborator_removed' => 'fas fa-user-minus text-orange-500', 
                                    'collaborator_left' => 'fas fa-sign-out-alt text-orange-600',
                                    'collaborator_permission_changed' => 'fas fa-user-shield text-teal-500', 
                                    'settings_updated' => 'fas fa-cog text-gray-600', 'board_created' => 'fas fa-chalkboard text-pink-500', 
                                    'project_archived' => 'fas fa-archive text-yellow-600', 'project_unarchived' => 'fas fa-undo text-green-600', 
                                    'project_deleted' => 'fas fa-trash-alt text-red-700',
                                    'invitation_sent' => 'fas fa-paper-plane text-blue-500',
                                    'invitation_accepted' => 'fas fa-user-check text-green-500',
                                    'invitation_declined' => 'fas fa-user-times text-red-500',
                                    'invitation_cancelled' => 'fas fa-ban text-orange-500'
                                ];
                                if (array_key_exists($activity_type_key, $icon_map)) { 
                                    $icon_class = $icon_map[$activity_type_key]; 
                                }
                                $timestamp_from_db = $activity_item['created_at']; 
                                try {
                                    $activity_date = new DateTime($timestamp_from_db);
                                    $formatted_date = $activity_date->format('M d, Y H:i');
                                } catch (Exception $e) {
                                    error_log("Error parsing date for activity log (Project Settings Tab): " . $e->getMessage() . " - Timestamp: " . $timestamp_from_db);
                                    $formatted_date = "Invalid date";
                                }
                                ?>
                                <div class="p-4 hover:bg-gray-50 flex items-start" data-activity-type="<?= htmlspecialchars($activity_item['activity_type']) ?>" data-activity-category="<?= explode('_', $activity_item['activity_type'])[0] ?>">
                                    <div class="mr-4 mt-1"><i class="<?= $icon_class ?> text-lg"></i></div>
                                    <div class="flex-grow">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($activity_item['username']) ?></span>
                                                <span class="text-gray-600 ml-1"><?= htmlspecialchars($activity_item['activity_description']) ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500 whitespace-nowrap ml-2"><?= $formatted_date ?></div>
                                        </div>
                                        <?php if ($activity_item['related_entity_type'] == 'task' && $activity_item['related_entity_id']): ?>
                                            <div class="mt-1"><a href="kanban.php?board_id=<?= $active_board_id ?>&task_id=<?= $activity_item['related_entity_id'] ?>" class="text-blue-600 hover:underline text-sm">View Task</a></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                 <?php if (isset($activities_project) && count($activities_project) >= 50): ?>
                    <div class="mt-6 text-center"><a href="activity_log_all.php?board_id=<?= $active_board_id ?>" class="text-blue-600 hover:underline">View All Activities for this Project</a></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script src="js/project_settings.js" defer></script>