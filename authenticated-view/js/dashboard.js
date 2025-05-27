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

    // --- Dark Mode Toggle Script ---
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    const htmlElement = document.documentElement;
    function setDarkMode(isDark) {
        htmlElement.classList.toggle('dark-mode', isDark);
        if (darkModeToggle) darkModeToggle.textContent = isDark ? '☀️' : '🌙';
    }
    // Check for system preference first, then localStorage
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
    const notificationsToggle = document.getElementById('notifications-toggle'); // Needed for closing logic
    const notificationsDropdown = document.getElementById('notifications-dropdown'); // Needed for closing logic

    if (profileToggle && profileDropdown) {
        profileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            if (notificationsDropdown) notificationsDropdown.classList.add('hidden'); // Close other dropdown
        });
    }

    // --- Notifications Dropdown Script ---
    const notificationsList = document.getElementById('notifications-list');
    const markAllReadBtn = document.getElementById('mark-all-read');
    const notificationCountBadge = document.getElementById('notification-count-badge');

    function fetchNotifications() {
        fetch('ajax_handlers/get_notifications.php') // Path relative to authenticated-view/index.php
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
            console.error("Fetch notifications error:", error);
        });
    }

    function renderNotifications(notifications) {
        if (!notificationsList) return;
        if (!notifications || notifications.length === 0) {
            notificationsList.innerHTML = '<p class="text-sm text-gray-600">No new notifications.</p>'; return;
        }
        let html = '';
        notifications.forEach(notif => {
            const isUnreadClass = notif.is_read == 0 ? 'font-semibold bg-sky-50' : 'text-gray-700';
            const messageText = String(notif.message || '').replace(/</g, "<").replace(/>/g, ">");
            const createdAtText = String(notif.formatted_created_at || '').replace(/</g, "<").replace(/>/g, ">");
            const linkHtml = notif.link ? `<a href="${encodeURI(notif.link)}" class="block hover:bg-gray-100 p-2 rounded ${isUnreadClass}" data-id="${notif.notification_id}">` : `<div class="p-2 ${isUnreadClass}" data-id="${notif.notification_id}">`;
            const linkEndHtml = notif.link ? `</a>` : `</div>`;
            html += `<div class="notification-item border-b border-gray-200 last:border-b-0">${linkHtml}<p class="text-sm ">${messageText}</p><p class="text-xs text-gray-500 mt-1">${createdAtText}</p>${linkEndHtml}</div>`;
        });
        notificationsList.innerHTML = html;
        document.querySelectorAll('.notification-item a, .notification-item div[data-id]').forEach(item => {
            item.addEventListener('click', function(e) {
                const notificationId = this.dataset.id; const isLink = this.tagName === 'A';
                if (this.classList.contains('font-semibold')) markNotificationAsRead(notificationId, !isLink);
                if (!isLink) e.stopPropagation();
            });
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
        fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData }) // Path relative to authenticated-view/index.php
        .then(response => response.json()).then(data => {
            if (data.success) {
                if (refreshList) { fetchNotifications(); }
                else {
                    const itemClicked = notificationsList.querySelector(`.notification-item [data-id="${notificationId}"]`);
                    if (itemClicked) { itemClicked.classList.remove('font-semibold', 'bg-sky-50'); itemClicked.classList.add('text-gray-700'); }
                    if (notificationCountBadge) { let currentCount = parseInt(notificationCountBadge.textContent || "0"); if (currentCount > 0) updateUnreadCount(currentCount - 1); }
                }
            }
        });
    }

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation(); const formData = new FormData(); formData.append('mark_all', 'true');
            fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData }) // Path relative to authenticated-view/index.php
            .then(response => response.json()).then(data => { if (data.success) fetchNotifications(); });
        });
    }

    if (notificationsToggle && notificationsDropdown) {
        notificationsToggle.addEventListener('click', (e) => {
            e.stopPropagation(); const isHidden = notificationsDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden'); // Close other dropdown
            if (!isHidden) fetchNotifications();
        });
    }

    // --- Close dropdowns on outside click ---
    document.addEventListener('click', (e) => {
        if (notificationsDropdown && !notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) notificationsDropdown.classList.add('hidden');
        if (profileDropdown && !profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) profileDropdown.classList.add('hidden');
    });

    // --- Add Board Modal Script ---
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
                fetch('create_board.php', { // Path relative to authenticated-view/index.php
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
    const showArchivedToggle = document.getElementById('show-archived-toggle');
    if (showArchivedToggle) {
        showArchivedToggle.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            this.checked ? currentUrl.searchParams.set('show_archived', '1') : currentUrl.searchParams.delete('show_archived');
            window.location.href = currentUrl.toString();
        });
    }

    // --- Dashboard Search Functionality ---
    const searchInput = document.getElementById('dashboardSearchInput');
    const boardsGridContainer = document.getElementById('boardsGridContainer');
    const noSearchResultsMessage = document.getElementById('noSearchResultsMessage');
    const noBoardsMessage = document.getElementById('noBoardsMessage'); 
    
    // initialBoardCount will be defined by an inline script in index.php
    // If it's not available, default to a high number to avoid incorrectly showing "no boards"
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