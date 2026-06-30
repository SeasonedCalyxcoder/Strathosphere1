/**
 * Strathosphere Production App
 * Communicates with PHP/MySQL backend via Fetch API
 */

function resolveApiBase() {
    if (window.location.protocol === 'file:') {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        const fileName = pathParts[pathParts.length - 1] || '';
        const projectFolder = pathParts[pathParts.length - 2] || 'strathosphere-prod';
        const inferredProject = fileName.toLowerCase().endsWith('.html') ? projectFolder : 'strathosphere-prod';
        return `http://localhost/${inferredProject}/api/`;
    }

    return new URL('api/', window.location.href).href;
}

const API_BASE = resolveApiBase();

function isValidStrathmoreEmail(email) {
    return /^[a-zA-Z]+\.[a-zA-Z]+@strathmore\.edu$/i.test(email);
}

async function apiRegister(name, email, password, phoneNumber) {
    const res = await fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=register&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}&phone_number=${encodeURIComponent(phoneNumber)}`
    });
    return await res.json();
}

async function apiLogin(email, password) {
    const res = await fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
    });
    return await res.json();
}

async function apiLogout() {
    const res = await fetch(API_BASE + 'auth.php?action=logout');
    const data = await res.json();
    window.location.href = 'login.html';
    return data;
}

async function apiMe() {
    const res = await fetch(API_BASE + 'auth.php?action=me');
    return await res.json();
}

async function apiUpdateProfile(formData) {
    formData.append('action', 'update_profile');
    const res = await fetch(API_BASE + 'auth.php', {
        method: 'POST',
        body: formData
    });
    return await res.json();
}

async function apiChangePassword(currentPassword, newPassword) {
    const res = await fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=change_password&current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}`
    });
    return await res.json();
}

async function apiDeleteMyAccount(currentPassword) {
    const res = await fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_account&current_password=${encodeURIComponent(currentPassword)}`
    });
    return await res.json();
}

async function apiGetEvents(category = 'all', search = '', sort = 'date', locationId = 0) {
    const res = await fetch(`${API_BASE}events.php?action=list&category=${category}&search=${encodeURIComponent(search)}&sort=${sort}&location_id=${encodeURIComponent(locationId)}`);
    return await res.json();
}

async function apiRegisterEvent(eventId, phoneNumber) {
    const res = await fetch(API_BASE + 'events.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=register&event_id=${eventId}&phone_number=${encodeURIComponent(phoneNumber)}`
    });
    return await res.json();
}

async function apiDeregisterEvent(eventId) {
    const res = await fetch(API_BASE + 'events.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=deregister&event_id=${eventId}`
    });
    return await res.json();
}

async function apiCreateEvent(formData) {
    formData.append('action', 'create');
    const res = await fetch(API_BASE + 'events.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    });
    return await res.json();
}

async function apiMyEvents() {
    const res = await fetch(API_BASE + 'events.php?action=my_events');
    return await res.json();
}

async function apiGetLocations(category = 'all', search = '') {
    const res = await fetch(`${API_BASE}locations.php?action=list&category=${category}&search=${encodeURIComponent(search)}`);
    return await res.json();
}

async function apiGetLocation(id) {
    const res = await fetch(`${API_BASE}locations.php?action=get&id=${id}`);
    return await res.json();
}

async function apiUpdateLocation(locationData) {
    const res = await fetch(API_BASE + 'locations.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update&id=${encodeURIComponent(locationData.id)}&name=${encodeURIComponent(locationData.name)}&category=${encodeURIComponent(locationData.category)}&lat=${encodeURIComponent(locationData.lat)}&lng=${encodeURIComponent(locationData.lng)}&description=${encodeURIComponent(locationData.description)}&icon=${encodeURIComponent(locationData.icon || 'building')}`
    });
    return await res.json();
}

async function apiGetDocuments(category = 'all', search = '', sort = 'date') {
    const res = await fetch(`${API_BASE}documents.php?action=list&category=${category}&search=${encodeURIComponent(search)}&sort=${sort}`);
    return await res.json();
}

async function apiDownloadDocument(id) {
    const res = await fetch(`${API_BASE}documents.php?action=download&id=${id}`);
    return await res.json();
}

async function apiUploadDocument(formData) {
    formData.append('action', 'create');
    const res = await fetch(API_BASE + 'documents.php', {
        method: 'POST',
        body: new URLSearchParams(formData)
    });
    return await res.json();
}

async function apiGetLeaderboard() {
    const res = await fetch(API_BASE + 'rewards.php?action=leaderboard');
    return await res.json();
}

async function apiGetTransactions() {
    const res = await fetch(API_BASE + 'rewards.php?action=transactions');
    return await res.json();
}

async function apiRedeem(cost, reward) {
    const res = await fetch(API_BASE + 'rewards.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=redeem&cost=${cost}&reward=${encodeURIComponent(reward)}`
    });
    return await res.json();
}

async function apiGetAdminStats() {
    const res = await fetch(API_BASE + 'admin.php?action=stats');
    return await res.json();
}

async function apiGetAdminUsers(search = '') {
    const res = await fetch(`${API_BASE}admin.php?action=users&search=${encodeURIComponent(search)}`);
    return await res.json();
}

async function apiAdminUpdateUserRole(userId, role) {
    const res = await fetch(API_BASE + 'admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update_role&user_id=${encodeURIComponent(userId)}&role=${encodeURIComponent(role)}`
    });
    return await res.json();
}

async function apiAdminDeleteUser(userId) {
    const res = await fetch(API_BASE + 'admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=delete_user&user_id=${encodeURIComponent(userId)}`
    });
    return await res.json();
}

async function apiAdminReviewUser(userId, decision) {
    const res = await fetch(API_BASE + 'admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=review_user&user_id=${encodeURIComponent(userId)}&decision=${encodeURIComponent(decision)}`
    });
    return await res.json();
}

async function apiGetNotifications() {
    const res = await fetch(API_BASE + 'auth.php?action=notifications');
    return await res.json();
}

async function apiMarkNotificationsRead() {
    const res = await fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_notifications_read'
    });
    return await res.json();
}

function formatDate(dateStr) {
    const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateStr).toLocaleDateString('en-KE', options);
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${minutes} ${ampm}`;
}

function formatCurrency(amount) {
    return amount == 0 ? 'Free' : `KES ${amount}`;
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

document.addEventListener('DOMContentLoaded', () => {
    updateNavUser();
});

async function updateNavUser() {
    const navUser = document.getElementById('nav-user');
    const navAuth = document.getElementById('nav-auth');
    const adminLinks = document.querySelectorAll('.admin-only');
    
    const res = await apiMe();
    
    if (res.success) {
        const user = res.user;
        if (navUser) {
            navUser.innerHTML = `
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <img src="${user.avatar}" alt="${user.name}" class="rounded-circle" width="32" height="32">
                        <span class="d-none d-md-inline">${user.name.split(' ')[0]}</span>
                        ${user.points > 0 ? `<span class="badge bg-warning text-dark">${user.points} pts</span>` : ''}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.html"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="rewards.html"><i class="fas fa-trophy me-2"></i>My Points</a></li>
                        <li><a class="dropdown-item" href="events.html"><i class="fas fa-calendar me-2"></i>My Events</a></li>
                        ${user.role === 'admin' ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="admin.html"><i class="fas fa-cog me-2"></i>Admin Dashboard</a></li>' : ''}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="apiLogout(); return false;"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            `;
        }
        if (navAuth) navAuth.style.display = 'none';
        adminLinks.forEach(el => {
            if (user.role === 'admin') {
                el.style.display = 'block';
                el.classList.remove('d-none');
            }
        });

        if (!sessionStorage.getItem('shownNotifications')) {
            const notificationsRes = await apiGetNotifications();
            if (notificationsRes.success && notificationsRes.notifications.length > 0) {
                notificationsRes.notifications.forEach(note => showToast(note.message, note.type === 'account_rejected' ? 'danger' : 'info'));
                await apiMarkNotificationsRead();
            }
            sessionStorage.setItem('shownNotifications', '1');
        }
    } else {
        if (navUser) navUser.innerHTML = '';
        if (navAuth) navAuth.style.display = 'flex';
        adminLinks.forEach(el => el.style.display = 'none');
    }
}