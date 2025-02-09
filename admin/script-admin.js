// admin/script-admin.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Admin panel loaded.');
    // Example: you can add interactivity like sidebar toggling for mobile, dynamic updates, etc.
    
    // Example: Toggle sidebar (if you add a button for that in the future)
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    if (sidebarToggleBtn) {
      sidebarToggleBtn.addEventListener('click', () => {
        document.querySelector('aside').classList.toggle('hidden');
      });
    }
  });
  