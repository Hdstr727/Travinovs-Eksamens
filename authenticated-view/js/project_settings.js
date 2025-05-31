document.addEventListener('DOMContentLoaded', () => {
    // Function to show/hide settings tabs
    window.showTab = function(tabName, clickedLink) {
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.classList.add('hidden');
        });
        const targetTab = document.getElementById(tabName + '-tab');
        if (targetTab) {
            targetTab.classList.remove('hidden');
        }

        const navLinks = document.querySelectorAll('#settings-tabs-nav a');
        navLinks.forEach(link => {
            link.classList.remove('border-b-2', 'border-[#e63946]', 'text-[#e63946]');
            link.classList.add('text-gray-600', 'hover:text-[#e63946]');
        });

        if (clickedLink) {
            clickedLink.classList.remove('text-gray-600', 'hover:text-[#e63946]');
            clickedLink.classList.add('border-b-2', 'border-[#e63946]', 'text-[#e63946]');
        }

        // Store active tab in URL hash
        window.location.hash = tabName;
    }

    // Load Font Awesome if not already present
    if (!document.querySelector('link[href*="fontawesome"]')) {
        const fontAwesome = document.createElement('link');
        fontAwesome.rel = 'stylesheet';
        fontAwesome.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(fontAwesome);
    }

    // Invitation Modal Logic
    const invitationModal = document.getElementById('invitationModal');
    const sendInvitationForm = document.getElementById('sendInvitationForm');
    const invitationStatusDiv = document.getElementById('invitationStatus');

    window.openInvitationModal = function() {
        if (invitationModal) {
            invitationModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (invitationStatusDiv) {
                invitationStatusDiv.innerHTML = ''; // Clear previous status
            }
            if (sendInvitationForm) {
                sendInvitationForm.reset(); // Reset form fields
            }
        }
    }

    window.closeInvitationModal = function() {
        if (invitationModal) {
            invitationModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    if (invitationModal) {
        invitationModal.addEventListener('click', function(event) {
            if (event.target === this) { // Clicked on the modal backdrop
                closeInvitationModal();
            }
        });
    }
    
    // AJAX for sending invitation
    if (sendInvitationForm) {
        sendInvitationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            if (invitationStatusDiv) {
                invitationStatusDiv.innerHTML = '<p class="text-sm text-gray-500">Sending...</p>';
            }

            // The form's action attribute should be "contents/send_invitation.php"
            // This path is relative to project_settings.php (in authenticated-view/)
            fetch(this.action, { 
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (invitationStatusDiv) {
                    if (data.success) {
                        invitationStatusDiv.innerHTML = `<p class="text-sm text-green-600">${data.message}</p>`;
                        setTimeout(() => { 
                            closeInvitationModal(); 
                            // Reload the page to show pending invitations or updated collaborator list
                            window.location.reload(); 
                        }, 2500); // Increased delay slightly
                    } else {
                        invitationStatusDiv.innerHTML = `<p class="text-sm text-red-600">Error: ${data.message}</p>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error sending invitation:', error);
                if (invitationStatusDiv) {
                    invitationStatusDiv.innerHTML = '<p class="text-sm text-red-600">An unexpected error occurred. Please try again.</p>';
                }
            });
        });
    }

    // Function to cancel a pending invitation (NEW)
    window.cancelInvitation = function(invitationId) {
        if (!confirm("Are you sure you want to cancel this invitation?")) return;

        const formData = new FormData();
        formData.append('invitation_id', invitationId);
        
        // Path relative to project_settings.php (in authenticated-view/)
        fetch('ajax_handlers/cancel_invitation.php', { 
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Invitation cancelled successfully.");
                window.location.reload(); // Reload to update the pending list
            } else {
                alert("Error cancelling invitation: " + data.message);
            }
        })
        .catch(error => {
            console.error("Cancel invitation error:", error);
            alert("An error occurred while cancelling the invitation.");
        });
    }

    // Activity Log Filter Logic
    const activityFilterElement = document.getElementById('activity-filter');
    if (activityFilterElement) {
        activityFilterElement.addEventListener('change', function() {
            const filterValue = this.value; 
            const activitiesContainer = document.querySelector('#activity-tab .divide-y'); 
            if (activitiesContainer) {
                const activities = activitiesContainer.querySelectorAll('[data-activity-category]');
                
                activities.forEach(activity => {
                    const activityCategory = activity.dataset.activityCategory;
                    if (filterValue === 'all' || activityCategory === filterValue) {
                        activity.style.display = 'flex'; 
                    } else {
                        activity.style.display = 'none';
                    }
                });
            }
        });
    }

    // Activate tab based on URL hash on page load
    const hash = window.location.hash.substring(1);
    const validTabs = ['general', 'collaborators', 'notifications', 'advanced', 'activity'];
    let initialTab = 'general'; 

    if (hash && validTabs.includes(hash)) {
        initialTab = hash;
    }
    
    const initialTabLink = document.querySelector(`#settings-tabs-nav a[href="#${initialTab}"]`);
    if (initialTabLink) {
        showTab(initialTab, initialTabLink);
    } else {
        const defaultTabLink = document.querySelector(`#settings-tabs-nav a[href="#general"]`);
        if (defaultTabLink) {
            showTab('general', defaultTabLink);
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            if (invitationModal && !invitationModal.classList.contains('hidden')) {
                closeInvitationModal();
            }
        }
    });

    const deleteProjectModal = document.getElementById('deleteProjectModal');
    const deleteProjectForm = document.getElementById('deleteProjectForm');
    const confirmProjectNameInput = document.getElementById('confirmProjectNameInput');
    const confirmDeleteProjectButton = document.getElementById('confirmDeleteProjectButton');
    const deleteProjectNameConfirmSpan = document.getElementById('deleteProjectNameConfirm');
    const deleteProjectNameTypeSpan = document.getElementById('deleteProjectNameType');
    const boardIdToDeleteInput = document.getElementById('boardIdToDelete');
    const deleteErrorText = document.getElementById('deleteErrorText');

    let expectedProjectNameToDelete = "";

    window.openDeleteProjectModal = function(boardName, boardId) {
        if (deleteProjectModal && boardName && boardId) {
            expectedProjectNameToDelete = boardName;
            if (deleteProjectNameConfirmSpan) deleteProjectNameConfirmSpan.textContent = boardName;
            if (deleteProjectNameTypeSpan) deleteProjectNameTypeSpan.textContent = boardName;
            if (boardIdToDeleteInput) boardIdToDeleteInput.value = boardId;
            if (deleteProjectForm) deleteProjectForm.action = `project_settings.php?board_id=${boardId}`; // Keep action pointing to current page context

            if (confirmProjectNameInput) confirmProjectNameInput.value = ''; // Clear input
            if (confirmDeleteProjectButton) confirmDeleteProjectButton.disabled = true; // Disable button
            if (deleteErrorText) {
                deleteErrorText.classList.add('hidden');
                deleteErrorText.textContent = '';
            }
            
            deleteProjectModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scroll
        } else {
            console.error("Delete project modal elements not found or missing parameters.");
        }
    }

    window.closeDeleteProjectModal = function() {
        if (deleteProjectModal) {
            deleteProjectModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }

    if (deleteProjectModal) {
        // Close modal on backdrop click
        deleteProjectModal.addEventListener('click', function(event) {
            if (event.target === this) {
                closeDeleteProjectModal();
            }
        });
    }

    if (confirmProjectNameInput && confirmDeleteProjectButton) {
        confirmProjectNameInput.addEventListener('input', function() {
            if (this.value === expectedProjectNameToDelete) {
                confirmDeleteProjectButton.disabled = false;
                if (deleteErrorText) deleteErrorText.classList.add('hidden');
            } else {
                confirmDeleteProjectButton.disabled = true;
            }
        });
    }

    if (deleteProjectForm) {
        deleteProjectForm.addEventListener('submit', function(e) {
            if (confirmProjectNameInput.value !== expectedProjectNameToDelete) {
                e.preventDefault(); // Stop form submission
                if (deleteErrorText) {
                    deleteErrorText.textContent = 'The project name you typed does not match.';
                    deleteErrorText.classList.remove('hidden');
                }
                return false;
            }
            // If it matches, the form will submit normally to the PHP handler
            // Add a loading state to the button if desired
            if (confirmDeleteProjectButton) {
                confirmDeleteProjectButton.disabled = true;
                confirmDeleteProjectButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
            }
        });
    }
    
    // Add to existing Escape key handler
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            if (invitationModal && !invitationModal.classList.contains('hidden')) {
                closeInvitationModal();
            }
            // Add this part for the delete modal
            if (deleteProjectModal && !deleteProjectModal.classList.contains('hidden')) {
                closeDeleteProjectModal();
            }
        }
    });
});