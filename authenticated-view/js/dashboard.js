// js/dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    // --- Highlight task from URL hash ---
    if (window.location.hash && window.location.hash.startsWith('#task-')) {
        const taskId = window.location.hash.substring(6);
        const taskLink = document.querySelector(`a[href*="#task-${taskId}"]`);
        if (taskLink) {
            const taskElement = taskLink.closest('li') || taskLink;
            taskElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            taskElement.classList.add('highlighted-task');
            setTimeout(() => taskElement.classList.remove('highlighted-task'), 3000);
        }
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'create_board') {
        const addBoardModal = document.getElementById('add-board-modal');
        if (addBoardModal) {
            addBoardModal.classList.remove('hidden');
        }
        // Optionally, remove the query parameter from the URL so it doesn't persist on refresh
        // Or if the user bookmarks the page.
        const newUrl = window.location.pathname + window.location.search.replace(/&?action=create_board/, '').replace(/\?$/, '');
        window.history.replaceState({}, document.title, newUrl);
    }

    // --- Dark Mode Toggle Script ---
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const htmlElement = document.documentElement;
    function setDarkMode(isDark) {
        htmlElement.classList.toggle('dark-mode', isDark);
        if (darkModeToggle) darkModeToggle.textContent = isDark ? '☀️' : '🌙';
    }
    const prefersDarkScheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    let currentDarkMode = localStorage.getItem('darkMode') === 'true' || (localStorage.getItem('darkMode') === null && prefersDarkScheme);
    setDarkMode(currentDarkMode);

    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', () => {
            currentDarkMode = !htmlElement.classList.contains('dark-mode');
            setDarkMode(currentDarkMode);
            localStorage.setItem('darkMode', currentDarkMode);
        });
    }

    // --- Profile Dropdown Script ---
    const profileToggle = document.getElementById('profile-toggle');
    const profileDropdown = document.getElementById('profile-dropdown');
    const notificationsToggle = document.getElementById('notifications-toggle');
    const notificationsDropdown = document.getElementById('notifications-dropdown');

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
        });
    }

    // --- Notifications Dropdown Script ---
    const notificationsList = document.getElementById('notifications-list');
    const markAllReadBtn = document.getElementById('mark-all-read');
    const notificationCountBadge = document.getElementById('notification-count-badge');
    const NOTIFICATION_POLLING_INTERVAL_DASHBOARD = 15000; // Poll every 15 seconds
    let notificationPollerDashboard = null;


    function fetchNotifications() {
        // Path relative to authenticated-view/index.php which includes this script
        // So, 'ajax_handlers/...' is correct if index.php is in authenticated-view/
        // and ajax_handlers is also in authenticated-view/
        fetch('ajax_handlers/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderNotifications(data.notifications);
                updateUnreadCount(data.unread_count);
            } else {
                if(notificationsList) notificationsList.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error || 'Could not load notifications.'}</p>`;
            }
        }).catch(error => {
            if(notificationsList) notificationsList.innerHTML = '<p class="text-sm text-red-500">Error fetching notifications.</p>';
            console.error("Dashboard Fetch notifications error:", error);
        });
    }

    function renderNotifications(notifications) {
        // const notificationsList = document.getElementById('notifications-list'); // Already defined globally in this scope
        if (!notificationsList) {
            console.error("Dashboard Notifications list element not found!");
            return;
        }

        if (!notifications || notifications.length === 0) {
            notificationsList.innerHTML = '<p class="text-sm text-gray-600">No new notifications.</p>';
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            const isUnreadClass = notif.is_read == 0 ? 'font-semibold bg-sky-50' : 'text-gray-700';
            const messageText = String(notif.message || '').replace(/</g, "<").replace(/>/g, ">"); // Basic sanitization
            const createdAtText = String(notif.formatted_created_at || '').replace(/</g, "<").replace(/>/g, ">");

            let actionButtons = '';
            if (notif.type === 'invitation' &&
                notif.related_entity_type === 'invitation' &&
                notif.related_entity_id &&
                notif.is_read == 0) {

                actionButtons = `
                    <div class="mt-2 flex space-x-2">
                        <button
                            onclick="handleInvitationAction(${notif.related_entity_id}, 'accept', this)"
                            class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                            Accept
                        </button>
                        <button
                            onclick="handleInvitationAction(${notif.related_entity_id}, 'decline', this)"
                            class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                            Decline
                        </button>
                    </div>`;
            } else if (notif.type === 'invitation' && notif.is_read == 1) {
                 actionButtons = `<p class="text-xs text-gray-500 italic mt-1">Invitation already actioned.</p>`;
            }

            const linkHtml = notif.link && !actionButtons ?
                `<a href="${encodeURI(notif.link)}" class="block hover:bg-gray-100 p-2 rounded ${isUnreadClass}" data-id="${notif.notification_id}">` :
                `<div class="p-2 ${isUnreadClass}" data-id="${notif.notification_id}">`;
            const linkEndHtml = notif.link && !actionButtons ? `</a>` : `</div>`;

            html += `<div class="notification-item border-b border-gray-200 last:border-b-0">
                        ${linkHtml}
                            <p class="text-sm ">${messageText}</p>
                            <p class="text-xs text-gray-500 mt-1">${createdAtText}</p>
                            ${actionButtons}
                        ${linkEndHtml}
                     </div>`;
        });
        notificationsList.innerHTML = html;

        // Re-attach general click listeners for marking as read (if not handled by action buttons)
        document.querySelectorAll('#notifications-list .notification-item > a, #notifications-list .notification-item > div[data-id]').forEach(item => {
            if (!item.querySelector('button[onclick^="handleInvitationAction"]')) {
                item.addEventListener('click', function(e) {
                    const notificationId = this.dataset.id;
                    // Check if it's unread by looking for the specific classes
                    if (this.classList.contains('font-semibold') || this.classList.contains('bg-sky-50')) {
                        markNotificationAsRead(notificationId, this.tagName !== 'A');
                    }
                    // If it's not a link, stop propagation to prevent closing dropdown if not intended
                    if (this.tagName !== 'A') {
                         // e.stopPropagation(); // Only stop if you don't want the dropdown to close on non-link item click
                    }
                });
            }
        });
    }

    window.handleInvitationAction = function(invitationId, action, buttonElement) {
        // ... (your existing handleInvitationAction function - looks good)
        buttonElement.disabled = true;
        buttonElement.textContent = 'Processing...';
        const otherButton = action === 'accept' ?
                            buttonElement.nextElementSibling :
                            buttonElement.previousElementSibling;
        if(otherButton) otherButton.disabled = true;

        const formData = new FormData();
        formData.append('invitation_id', invitationId);

        let targetUrl = '';
        if (action === 'accept') {
            targetUrl = 'ajax_handlers/accept_invitation.php';
        } else if (action === 'decline') {
            targetUrl = 'ajax_handlers/decline_invitation.php';
        } else {
            console.error('Invalid invitation action:', action);
            buttonElement.disabled = false;
            if(otherButton) otherButton.disabled = false;
            buttonElement.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            return;
        }

        fetch(targetUrl, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationItem = buttonElement.closest('.notification-item');
                if (notificationItem) {
                    const actionDiv = buttonElement.parentElement;
                    if(actionDiv) actionDiv.innerHTML = `<p class="text-xs text-gray-600 italic mt-1">Invitation ${action}ed.</p>`;
                }
                location.reload();
            } else {
                alert('Error: ' + data.message);
                buttonElement.disabled = false;
                if(otherButton) otherButton.disabled = false;
                buttonElement.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            }
        })
        .catch(error => {
            console.error('Invitation action error:', error);
            alert('An error occurred.');
            buttonElement.disabled = false;
            if(otherButton) otherButton.disabled = false;
            buttonElement.textContent = action.charAt(0).toUpperCase() + action.slice(1);
        });
    }

    function updateUnreadCount(count) {
        if (notificationCountBadge) {
            if (count > 0) { notificationCountBadge.textContent = count; notificationCountBadge.style.display = 'flex'; }
            else { notificationCountBadge.textContent = ''; notificationCountBadge.style.display = 'none'; }
        }
    }

    function markNotificationAsRead(notificationId, refreshList = true) {
        const formData = new FormData(); formData.append('notification_id', notificationId);
        fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData })
        .then(response => response.json()).then(data => {
            if (data.success) {
                if (refreshList) {
                    fetchNotifications();
                } else {
                    // Manually update UI for the clicked item
                    const itemClicked = notificationsList.querySelector(`.notification-item [data-id="${notificationId}"]`);
                    if (itemClicked) {
                        itemClicked.classList.remove('font-semibold', 'bg-sky-50');
                        itemClicked.classList.add('text-gray-700'); // Or whatever your "read" style is
                    }
                    // Manually update badge count
                    if (notificationCountBadge) {
                        let currentCount = parseInt(notificationCountBadge.textContent || "0");
                        if (currentCount > 0) updateUnreadCount(currentCount - 1);
                    }
                }
            }
        });
    }

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation(); const formData = new FormData(); formData.append('mark_all', 'true');
            fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData })
            .then(response => response.json()).then(data => { if (data.success) fetchNotifications(); });
        });
    }

    if (notificationsToggle && notificationsDropdown) {
        notificationsToggle.addEventListener('click', (e) => {
            e.stopPropagation(); const isHidden = notificationsDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden');
            if (!isHidden) fetchNotifications(); // Fetch when opened
        });
    }

    // --- Close dropdowns on outside click ---
    document.addEventListener('click', (e) => {
        if (notificationsDropdown && !notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) notificationsDropdown.classList.add('hidden');
        if (profileDropdown && !profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) profileDropdown.classList.add('hidden');
    });

    // --- START: Added Polling Logic for Dashboard Notifications ---
    function startDashboardNotificationPolling() {
        if (notificationPollerDashboard) clearInterval(notificationPollerDashboard);
        if (notificationsList) { // Only poll if the list element exists on this page
            fetchNotifications(); // Fetch immediately first
            notificationPollerDashboard = setInterval(fetchNotifications, NOTIFICATION_POLLING_INTERVAL_DASHBOARD);
        }
    }

    function stopDashboardNotificationPolling() {
        if (notificationPollerDashboard) clearInterval(notificationPollerDashboard);
    }

    // Start polling when the dashboard page loads
    startDashboardNotificationPolling();

    // Optional: Pause polling when tab is not active, resume when active
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopDashboardNotificationPolling();
        } else {
            startDashboardNotificationPolling();
        }
    });
    // --- END: Added Polling Logic for Dashboard Notifications ---


    // --- Add Board Modal Script ---
    // ... (your existing Add Board Modal Script - looks good) ...
    const addBoardBtn = document.getElementById('add-board-btn');
    const addBoardModal = document.getElementById('add-board-modal');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const createBoardBtn = document.getElementById('create-board-btn');
    const boardNameModalInput = document.getElementById('board-name-modal');
    const boardTemplateModalSelect = document.getElementById('board-template-modal');

    if (addBoardBtn) addBoardBtn.addEventListener('click', () => { if(addBoardModal) addBoardModal.classList.remove('hidden'); });
    if (closeModalBtn) closeModalBtn.addEventListener('click', () => { if(addBoardModal) addBoardModal.classList.add('hidden'); });
    if (addBoardModal) addBoardModal.addEventListener('click', function(e) { if (e.target === this) this.classList.add('hidden'); });

    if (createBoardBtn) {
        createBoardBtn.addEventListener('click', () => {
            const boardName = boardNameModalInput.value.trim(); const boardTemplate = boardTemplateModalSelect.value;
            if (boardName) {
                fetch('create_board.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `board_name=${encodeURIComponent(boardName)}&board_template=${encodeURIComponent(boardTemplate)}`
                })
                .then(response => response.json()).then(data => {
                    if (data.success) location.reload();
                    else alert(`Error: ${data.message || 'Unknown error'}`);
                })
                .catch(error => {
                    console.error("Create board error:", error);
                    alert('Error creating board.');
                });
            } else alert('Please enter a board name.');
        });
    }

    // --- Show Archived Toggle Script ---
    // ... (your existing Show Archived Toggle Script - looks good) ...
    const showArchivedToggle = document.getElementById('show-archived-toggle');
    if (showArchivedToggle) {
        showArchivedToggle.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            this.checked ? currentUrl.searchParams.set('show_archived', '1') : currentUrl.searchParams.delete('show_archived');
            window.location.href = currentUrl.toString();
        });
    }

    // --- Dashboard Search Functionality ---
    // ... (your existing Dashboard Search Functionality - looks good) ...
    const searchInput = document.getElementById('dashboardSearchInput');
    const boardsGridContainer = document.getElementById('boardsGridContainer');
    const noSearchResultsMessage = document.getElementById('noSearchResultsMessage');
    const noBoardsMessage = document.getElementById('noBoardsMessage');
    const initialBoardCountFromPHP = typeof initialBoardCount !== 'undefined' ? initialBoardCount : 999;

    if (searchInput && boardsGridContainer) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const boardCards = boardsGridContainer.querySelectorAll('.board-card-item');
            let visibleBoardsCount = 0;

            boardCards.forEach(card => {
                const boardName = card.dataset.boardName;
                if (boardName.includes(searchTerm)) {
                    card.classList.remove('hidden-by-search');
                    visibleBoardsCount++;
                } else {
                    card.classList.add('hidden-by-search');
                }
            });

            if (noSearchResultsMessage) {
                noSearchResultsMessage.classList.toggle('hidden', !(visibleBoardsCount === 0 && searchTerm !== ""));
            }

            if (noBoardsMessage) {
                if (searchTerm === "" && initialBoardCountFromPHP === 0) {
                    noBoardsMessage.style.display = 'block';
                } else {
                    noBoardsMessage.style.display = 'none';
                }
            }
        });
    }
});