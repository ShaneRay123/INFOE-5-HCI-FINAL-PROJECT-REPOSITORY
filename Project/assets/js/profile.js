document.addEventListener('DOMContentLoaded', () => {
    // Profile page specific initialization
    loadProfile();
    loadStats();

    // Handle form submission
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await updateProfile();
        });
    }
});

async function loadProfile() {
    try {
        const response = await fetch('api/user.php');
        const result = await response.json();

        if (result.success) {
            const user = result.data;
            
            // Update display
            const userNameEl = document.getElementById('userName');
            if (userNameEl) userNameEl.textContent = user.full_name;
            
            const profileNameDisplay = document.getElementById('profileNameDisplay');
            if (profileNameDisplay) profileNameDisplay.textContent = user.full_name;
            
            const profileRoleDisplay = document.getElementById('profileRoleDisplay');
            if (profileRoleDisplay) profileRoleDisplay.textContent = user.job_title || 'No Role Set';
            
            const profileEmailDisplay = document.getElementById('profileEmailDisplay');
            if (profileEmailDisplay) profileEmailDisplay.textContent = user.email;
            
            const profilePhoneDisplay = document.getElementById('profilePhoneDisplay');
            if (profilePhoneDisplay) profilePhoneDisplay.textContent = user.phone || 'No phone added';
            
            const profileLocationDisplay = document.getElementById('profileLocationDisplay');
            if (profileLocationDisplay) profileLocationDisplay.textContent = user.location || 'No location added';

            if (user.avatar_url) {
                const avatarImg = document.getElementById('profileAvatarDisplay');
                if (avatarImg) {
                    avatarImg.src = user.avatar_url;
                    avatarImg.style.display = 'block';
                    const icon = document.querySelector('.profile-avatar i');
                    if (icon) icon.style.display = 'none';
                }
            }

            // Update form
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val || '';
            };

            setVal('fullName', user.full_name);
            setVal('jobTitle', user.job_title);
            setVal('phone', user.phone);
            setVal('location', user.location);
            setVal('avatarUrl', user.avatar_url);
            setVal('bio', user.bio);
        } else {
            // If failed to load user, maybe session expired
            if (result.message === 'Unauthorized') {
                window.location.href = 'login.php';
            }
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        // Do not alert on load, just log
    }
}

async function loadStats() {
    try {
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        // Get subjects
        const subjRes = await fetchFunc('api/subjects.php');
        const subjData = await subjRes.json();
        const subjects = subjData.success ? subjData.data : [];
        // Get tasks
        const taskRes = await fetchFunc('api/tasks.php');
        const taskData = await taskRes.json();
        const tasks = taskData.success ? taskData.data : [];
        
        const totalSubjects = subjects.length;
        const totalTasks = tasks.length;
        const completedTasks = tasks.filter(t => t.status === 'completed').length;
        const completionRate = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;
        
        // Due soon within 7 days
        const now = new Date();
        const in7 = new Date();
        in7.setDate(now.getDate() + 7);
        const dueSoon = tasks.filter(t => {
            if (!t.due_date) return false;
            const d = new Date(t.due_date);
            return d >= now && d <= in7 && t.status !== 'completed';
        }).length;
        
        const setText = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };
        
        setText('statSubjects', totalSubjects);
        setText('statTasks', totalTasks);
        setText('statCompleted', completedTasks);
        setText('statCompletionRate', `${completionRate}%`);
        setText('statDueSoon', dueSoon);
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}
async function updateProfile() {
    const formData = new FormData(document.getElementById('profileForm'));
    // Convert FormData to JSON
    const data = {};
    formData.forEach((value, key) => data[key] = value);
    
    // Show loading state
    const submitBtn = document.querySelector('#profileForm button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;

    try {
        const response = await fetch('api/user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload profile data to update display
            await loadProfile();
            alert('Profile updated successfully!');
        } else {
            alert(result.message || 'Failed to update profile');
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        alert('An error occurred while updating profile');
    } finally {
        // Restore button state
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
    }
}
