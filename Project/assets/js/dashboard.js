/**
 * Dashboard JavaScript
 * Handles subjects, tasks, and all CRUD operations
 */

let currentSubjectId = null;
let subjects = [];
let tasks = [];
let taskFilterStatus = 'all';
let taskSort = 'due_date_asc';
let taskView = 'list';
let taskFilterDate = null; // YYYY-MM-DD or null
let groupByStatus = false;

document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('Dashboard initialized');
        // Setup listeners immediately so buttons work even if data loads slowly
        setupEventListeners();
        initializeDashboard();
    } catch (error) {
        console.error('Error initializing dashboard:', error);
    }
});

/**
 * Initialize dashboard
 */
async function initializeDashboard() {
    await loadUserInfo();
    await loadSubjects();
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Add subject button
    const addSubjectBtn = document.getElementById('addSubjectBtn');
    if (addSubjectBtn) {
        addSubjectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Add Subject button clicked');
            openSubjectModal();
        });
    } else {
        console.error('Add Subject button not found!');
    }

    
    // Add task button
    const addTaskBtn = document.getElementById('addTaskBtn');
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openTaskModal();
        });
    }
    
    // Task filter and sort controls
    const filterSelect = document.getElementById('taskFilterStatus');
    if (filterSelect) {
        filterSelect.addEventListener('change', function(e) {
            taskFilterStatus = e.target.value;
            renderTasks();
        });
    }
    const sortSelect = document.getElementById('taskSort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function(e) {
            taskSort = e.target.value;
            renderTasks();
        });
    }
    
    const viewSelect = document.getElementById('taskView');
    if (viewSelect) {
        viewSelect.addEventListener('change', function(e) {
            taskView = e.target.value;
            updateTasksView();
        });
    }
    
    const groupToggle = document.getElementById('groupByStatusToggle');
    if (groupToggle) {
        groupToggle.addEventListener('change', function(e) {
            groupByStatus = e.target.checked;
            renderTasks();
        });
    }
    
    const quickAddRow = document.getElementById('quickAddRow');
    const quickAddBtn = document.getElementById('quickTaskAddBtn');
    if (quickAddRow && quickAddBtn) {
        quickAddRow.style.display = 'flex';
        quickAddBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            await quickAddTask();
        });
    }
    
    // Subject form
    const subjectForm = document.getElementById('subjectForm');
    if (subjectForm) {
        subjectForm.addEventListener('submit', handleSubjectSubmit);
    }
    
    // Task form
    const taskForm = document.getElementById('taskForm');
    if (taskForm) {
        taskForm.addEventListener('submit', handleTaskSubmit);
    }
    
    // Modal close buttons
    document.querySelectorAll('.modal-close, .modal-cancel').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeModals();
        });
    });
    
    // Close modal on background click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModals();
            }
        });
    });
}

/**
 * Load user information
 */
async function loadUserInfo() {
    try {
        // Use fetchWithAuth if available, otherwise fetch
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc('api/user.php');
        const data = await response.json();
        
        if (data.success) {
            const userNameEl = document.getElementById('userName');
            if (userNameEl) {
                userNameEl.textContent = data.data.full_name || data.data.username;
            }
        }
    } catch (error) {
        console.error('Error loading user info:', error);
    }
}

/**
 * Load all subjects
 */
async function loadSubjects() {
    showLoading();
    
    try {
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc('api/subjects.php');
        const data = await response.json();
        
        if (data.success) {
            subjects = data.data;
            renderSubjects();
        } else {
            showError('Failed to load subjects');
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
        showError('An error occurred while loading subjects');
    } finally {
        hideLoading();
    }
}

/**
 * Render subjects
 */
function renderSubjects() {
    const container = document.getElementById('subjectsContainer');
    if (!container) return;
    
    if (subjects.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="grid-column: 1 / -1;">
                <div class="empty-state-icon">📚</div>
                <h3>No Subjects Yet</h3>
                <p>Create your first subject to start organizing your studies</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = subjects.map(subject => `
        <div class="subject-card" style="border-left-color: ${subject.color}" onclick="viewSubjectTasks(${subject.id})">
            <div class="subject-card-header">
                <div>
                    <div class="subject-card-title">${escapeHtml(subject.name)}</div>
                </div>
                <div class="subject-card-actions" onclick="event.stopPropagation()">
                    <button class="btn-edit" onclick="editSubject(${subject.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-delete" onclick="deleteSubject(${subject.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="subject-card-description">
                ${subject.description ? escapeHtml(subject.description) : '<em>No description</em>'}
            </div>
            <div class="subject-card-footer">
                <span class="task-count" id="task-count-${subject.id}">Loading...</span>
            </div>
        </div>
    `).join('');
    
    // Load task counts for each subject
    subjects.forEach(subject => loadTaskCount(subject.id));
}

/**
 * Load task count for a subject
 */
async function loadTaskCount(subjectId) {
    try {
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc(`api/tasks.php?subject_id=${subjectId}`);
        const data = await response.json();
        
        if (data.success) {
            const count = data.data.length;
            const completed = data.data.filter(t => t.status === 'completed').length;
            const percent = count > 0 ? Math.round((completed / count) * 100) : 0;
            const countElement = document.getElementById(`task-count-${subjectId}`);
            if (countElement) {
                countElement.textContent = `${count} task${count !== 1 ? 's' : ''} • ${percent}% completed`;
            }
        }
    } catch (error) {
        console.error('Error loading task count:', error);
    }
}

/**
 * View tasks for a subject
 */
async function viewSubjectTasks(subjectId) {
    currentSubjectId = subjectId;
    const subject = subjects.find(s => s.id === subjectId);
    
    if (!subject) return;
    
    const titleEl = document.getElementById('tasksSubjectTitle');
    const tasksContainer = document.getElementById('tasksContainer');
    
    if (titleEl) titleEl.textContent = subject.name;
    if (tasksContainer) {
        tasksContainer.style.display = 'block';
        
        await loadTasks(subjectId);
        
        // Scroll to tasks section
        tasksContainer.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Load tasks
 */
async function loadTasks(subjectId = null) {
    showLoading();
    
    try {
        const url = subjectId ? `api/tasks.php?subject_id=${subjectId}` : 'api/tasks.php';
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc(url);
        const data = await response.json();
        
        if (data.success) {
            tasks = data.data;
            updateTasksView();
        } else {
            showError('Failed to load tasks');
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
        showError('An error occurred while loading tasks');
    } finally {
        hideLoading();
    }
}

/**
 * Render tasks
 */
function renderTasks() {
    const container = document.getElementById('tasksList');
    if (!container) return;
    
    // Apply filtering (status + date)
    const filtered = tasks.filter(t => {
        if (taskFilterStatus === 'all') return true;
        return t.status === taskFilterStatus;
    });
    const dateFiltered = filtered.filter(t => {
        if (!taskFilterDate) return true;
        return (t.due_date && t.due_date === taskFilterDate);
    });
    
    // Apply sorting
    const prioRank = { low: 1, medium: 2, high: 3 };
    const sorted = dateFiltered.slice().sort((a, b) => {
        switch (taskSort) {
            case 'due_date_desc': {
                const ad = a.due_date ? new Date(a.due_date).getTime() : Infinity;
                const bd = b.due_date ? new Date(b.due_date).getTime() : Infinity;
                return bd - ad;
            }
            case 'priority_desc':
                return (prioRank[b.priority] || 0) - (prioRank[a.priority] || 0);
            case 'created_desc': {
                const ac = a.created_at ? new Date(a.created_at).getTime() : 0;
                const bc = b.created_at ? new Date(b.created_at).getTime() : 0;
                return bc - ac;
            }
            case 'due_date_asc':
            default: {
                const ad = a.due_date ? new Date(a.due_date).getTime() : Infinity;
                const bd = b.due_date ? new Date(b.due_date).getTime() : Infinity;
                return ad - bd;
            }
        }
    });
    
    if (sorted.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">✅</div>
                <h3>No Tasks Yet</h3>
                <p>${taskFilterDate ? 'No tasks on selected date' : 'Create your first task for this subject'}</p>
            </div>
        `;
        return;
    }
    
    const todayTime = new Date().setHours(0,0,0,0);
    const renderTaskItem = (task) => {
        const isCompleted = task.status === 'completed';
        const dueTime = task.due_date ? new Date(task.due_date).setHours(0,0,0,0) : null;
        const isOverdue = !isCompleted && dueTime && dueTime < todayTime;
        return `
            <div class="task-item ${isCompleted ? 'completed' : ''} ${isOverdue ? 'overdue' : ''}" style="border-left-color: ${task.subject_color}">
                <div class="task-header">
                    <input type="checkbox" class="task-checkbox" onclick="toggleTaskCompleted(${task.id}, this.checked)" ${isCompleted ? 'checked' : ''} />
                    <div class="task-content">
                        <div class="task-title"><span class="priority-dot priority-${task.priority}"></span>${escapeHtml(task.title)}</div>
                        ${task.description ? `<div class="task-description">${escapeHtml(task.description)}</div>` : ''}
                    </div>
                    <div class="task-actions">
                        <button class="btn btn-primary btn-sm" onclick="editTask(${task.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteTask(${task.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="task-meta">
                    <div class="task-meta-item">
                        <span class="priority-badge priority-${task.priority}">${task.priority}</span>
                    </div>
                    <div class="task-meta-item">
                        <span class="status-badge status-${task.status}">${task.status.replace('_', ' ')}</span>
                    </div>
                    ${task.due_date ? `<div class="task-meta-item">${isOverdue ? '⚠️ Overdue • ' : '📅 '} ${formatDate(task.due_date)}</div>` : ''}
                    <div class="task-meta-item">📚 ${escapeHtml(task.subject_name)}</div>
                </div>
            </div>
        `;
    };
    
    if (!groupByStatus) {
        container.innerHTML = sorted.map(renderTaskItem).join('');
    } else {
        const groups = {
            pending: [],
            in_progress: [],
            completed: []
        };
        sorted.forEach(t => groups[t.status] ? groups[t.status].push(t) : groups.pending.push(t));
        const section = (status, label) => {
            const items = groups[status];
            const count = items.length;
            const sectionId = `group-${status}`;
            return `
                <div class="task-group">
                    <div class="task-group-header" onclick="toggleGroup('${sectionId}')">
                        <span class="status-badge status-${status}">${label}</span>
                        <span class="task-group-count">${count}</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div id="${sectionId}" class="task-group-body">${items.map(renderTaskItem).join('')}</div>
                </div>
            `;
        };
        container.innerHTML = [
            section('pending', 'Pending'),
            section('in_progress', 'In Progress'),
            section('completed', 'Completed')
        ].join('');
    }
}

window.toggleGroup = function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.toggle('collapsed');
    }
};

function updateTasksView() {
    const listEl = document.getElementById('tasksList');
    const calEl = document.getElementById('tasksCalendar');
    if (!listEl || !calEl) return;
    if (taskView === 'calendar') {
        listEl.style.display = 'none';
        calEl.style.display = 'block';
        renderCalendar();
    } else {
        calEl.style.display = 'none';
        listEl.style.display = 'block';
        renderTasks();
    }
}

function renderCalendar() {
    const container = document.getElementById('tasksCalendar');
    if (!container) return;
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth(); // 0-11
    const firstDay = new Date(year, month, 1);
    const startDayIdx = firstDay.getDay(); // 0-6
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Build map of due counts
    const counts = {};
    tasks.forEach(t => {
        if (!t.due_date) return;
        const d = new Date(t.due_date);
        if (d.getFullYear() === year && d.getMonth() === month) {
            const key = d.getDate();
            counts[key] = (counts[key] || 0) + 1;
        }
    });
    
    let grid = '<div class="calendar-header">' +
        `<div class="calendar-title">${now.toLocaleString(undefined, { month: 'long', year: 'numeric' })}</div>` +
        '<div class="calendar-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>' +
        '<div class="calendar-grid">';
    for (let i = 0; i < startDayIdx; i++) {
        grid += '<div class="calendar-cell empty"></div>';
    }
    for (let day = 1; day <= daysInMonth; day++) {
        const count = counts[day] || 0;
        grid += `<div class="calendar-cell" onclick="filterByDate('${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}')">
            <div class="calendar-day">${day}</div>
            ${count ? `<div class="calendar-count">${count} task${count>1?'s':''}</div>` : ''}
        </div>`;
    }
    grid += '</div></div>';
    container.innerHTML = grid + (taskFilterDate ? `<div class="calendar-selected">Filter: ${formatDate(taskFilterDate)} <button class="btn btn-secondary btn-sm" onclick="clearDateFilter()">Clear</button></div>` : '');
}

window.filterByDate = function(dateStr) {
    taskFilterDate = dateStr;
    taskView = 'list';
    updateTasksView();
};

window.clearDateFilter = function() {
    taskFilterDate = null;
    renderTasks();
};

/**
 * Open subject modal for creating/editing
 * Make it globally available
 */
function openSubjectModal(subjectId = null) {
    const modal = document.getElementById('subjectModal');
    const form = document.getElementById('subjectForm');
    const title = document.getElementById('subjectModalTitle');
    const errorMsg = document.getElementById('subjectErrorMessage');
    
    if (!modal || !form) {
        console.error('Modal or form not found');
        return;
    }
    
    if (errorMsg) errorMsg.style.display = 'none';
    form.reset();
    
    if (subjectId) {
        const subject = subjects.find(s => s.id === subjectId);
        if (subject) {
            if (title) title.textContent = 'Edit Subject';
            const idInput = document.getElementById('subjectId');
            const nameInput = document.getElementById('subjectName');
            const colorInput = document.getElementById('subjectColor');
            const descInput = document.getElementById('subjectDescription');
            
            if (idInput) idInput.value = subject.id;
            if (nameInput) nameInput.value = subject.name;
            if (colorInput) colorInput.value = subject.color;
            if (descInput) descInput.value = subject.description || '';
        }
    } else {
        if (title) title.textContent = 'Add Subject';
        const idInput = document.getElementById('subjectId');
        const colorInput = document.getElementById('subjectColor');
        
        if (idInput) idInput.value = '';
        if (colorInput) colorInput.value = '#3498db';
    }
    
    modal.classList.add('active');
    console.log('Modal opened');
}
window.openSubjectModal = openSubjectModal;

/**
 * Handle subject form submission
 */
async function handleSubjectSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    const errorMsg = document.getElementById('subjectErrorMessage');
    
    const id = document.getElementById('subjectId').value;
    const name = document.getElementById('subjectName').value.trim();
    const color = document.getElementById('subjectColor').value;
    const description = document.getElementById('subjectDescription').value.trim();
    
    // Show loading state
    submitBtn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnLoader) btnLoader.style.display = 'inline-block';
    if (errorMsg) errorMsg.style.display = 'none';
    
    try {
        const url = 'api/subjects.php';
        const method = id ? 'PUT' : 'POST';
        const body = id 
            ? { id: parseInt(id), name, color, description }
            : { name, color, description };
        
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModals();
            await loadSubjects();
            if (currentSubjectId && currentSubjectId === (id ? parseInt(id) : data.data.id)) {
                await loadTasks(currentSubjectId);
            }
        } else {
            if (errorMsg) {
                errorMsg.textContent = data.message || 'Operation failed';
                errorMsg.style.display = 'block';
            }
            
            submitBtn.disabled = false;
            if (btnText) btnText.style.display = 'inline';
            if (btnLoader) btnLoader.style.display = 'none';
        }
    } catch (error) {
        console.error('Error saving subject:', error);
        if (errorMsg) {
            errorMsg.textContent = 'An error occurred. Please try again.';
            errorMsg.style.display = 'block';
        }
        
        submitBtn.disabled = false;
        if (btnText) btnText.style.display = 'inline';
        if (btnLoader) btnLoader.style.display = 'none';
    }
}

/**
 * Edit subject
 */
function editSubject(subjectId) {
    openSubjectModal(subjectId);
}
window.editSubject = editSubject;

/**
 * Delete subject
 */
async function deleteSubject(subjectId) {
    if (!confirm('Are you sure you want to delete this subject? All associated tasks will also be deleted.')) {
        return;
    }
    
    showLoading();
    
    try {
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc('api/subjects.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: subjectId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadSubjects();
            if (currentSubjectId === subjectId) {
                const tasksContainer = document.getElementById('tasksContainer');
                if (tasksContainer) tasksContainer.style.display = 'none';
                currentSubjectId = null;
            }
        } else {
            alert(data.message || 'Failed to delete subject');
        }
    } catch (error) {
        console.error('Error deleting subject:', error);
        alert('An error occurred. Please try again.');
    } finally {
        hideLoading();
    }
}
window.deleteSubject = deleteSubject;

/**
 * Open task modal for creating/editing
 * Make global
 */
function openTaskModal(taskId = null) {
    const modal = document.getElementById('taskModal');
    const form = document.getElementById('taskForm');
    const title = document.getElementById('taskModalTitle');
    const errorMsg = document.getElementById('taskErrorMessage');
    const subjectSelect = document.getElementById('taskSubject');
    
    if (!modal || !form) return;
    
    if (errorMsg) errorMsg.style.display = 'none';
    form.reset();
    
    // Populate subject dropdown
    if (subjectSelect) {
        subjectSelect.innerHTML = subjects.map(s => 
            `<option value="${s.id}" ${s.id === currentSubjectId ? 'selected' : ''}>${escapeHtml(s.name)}</option>`
        ).join('');
    }
    
    if (taskId) {
        const task = tasks.find(t => t.id === taskId);
        if (task) {
            if (title) title.textContent = 'Edit Task';
            const idInput = document.getElementById('taskId');
            const titleInput = document.getElementById('taskTitle');
            const subInput = document.getElementById('taskSubject');
            const dueInput = document.getElementById('taskDueDate');
            const prioInput = document.getElementById('taskPriority');
            const descInput = document.getElementById('taskDescription');
            
            if (idInput) idInput.value = task.id;
            if (titleInput) titleInput.value = task.title;
            if (subInput) subInput.value = task.subject_id;
            if (dueInput) dueInput.value = task.due_date;
            if (prioInput) prioInput.value = task.priority;
            if (descInput) descInput.value = task.description || '';
        }
    } else {
        if (title) title.textContent = 'Add Task';
        const idInput = document.getElementById('taskId');
        if (idInput) idInput.value = '';
    }
    
    modal.classList.add('active');
}
window.openTaskModal = openTaskModal;

/**
 * Handle task form submission
 */
async function handleTaskSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    const id = document.getElementById('taskId').value;
    const title = document.getElementById('taskTitle').value.trim();
    const subjectId = document.getElementById('taskSubject').value;
    const dueDate = document.getElementById('taskDueDate').value;
    const priority = document.getElementById('taskPriority').value;
    const description = document.getElementById('taskDescription').value.trim();
    
    try {
        const url = 'api/tasks.php';
        const method = id ? 'PUT' : 'POST';
        const body = id 
            ? { id: parseInt(id), title, subject_id: subjectId, due_date: dueDate, priority, description }
            : { title, subject_id: subjectId, due_date: dueDate, priority, description };
        
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModals();
            if (currentSubjectId) {
                await loadTasks(currentSubjectId);
            }
            await loadSubjects(); // Refresh counts
        } else {
            alert(data.message || 'Operation failed');
        }
    } catch (error) {
        console.error('Error saving task:', error);
        alert('An error occurred');
    }
}

/**
 * Edit task
 */
function editTask(taskId) {
    openTaskModal(taskId);
}
window.editTask = editTask;

/**
 * Delete task
 */
async function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }
    
    showLoading();
    
    try {
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc('api/tasks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (currentSubjectId) {
                await loadTasks(currentSubjectId);
            }
            await loadSubjects(); // Refresh counts
        } else {
            alert(data.message || 'Failed to delete task');
        }
    } catch (error) {
        console.error('Error deleting task:', error);
        alert('An error occurred');
    } finally {
        hideLoading();
    }
}
window.deleteTask = deleteTask;

/**
 * Toggle task completed/pending
 */
async function toggleTaskCompleted(taskId, checked) {
    try {
        const newStatus = checked ? 'completed' : 'pending';
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc('api/tasks.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskId, status: newStatus })
        });
        const data = await response.json();
        if (data.success) {
            if (currentSubjectId) {
                await loadTasks(currentSubjectId);
            } else {
                await loadTasks();
            }
            await loadSubjects(); // refresh progress/counts
        } else {
            alert(data.message || 'Failed to update task status');
        }
    } catch (error) {
        console.error('Error updating task status:', error);
        alert('An error occurred while updating task status');
    }
}
window.toggleTaskCompleted = toggleTaskCompleted;

async function quickAddTask() {
    const titleEl = document.getElementById('quickTaskTitle');
    const dueEl = document.getElementById('quickTaskDue');
    const prioEl = document.getElementById('quickTaskPriority');
    if (!titleEl || !prioEl) return;
    const title = titleEl.value.trim();
    const dueDate = dueEl ? dueEl.value : null;
    const priority = prioEl.value || 'medium';
    if (!title) {
        alert('Please provide a task title');
        return;
    }
    try {
        const fetchFunc = (typeof fetchWithAuth === 'function') ? fetchWithAuth : fetch;
        const response = await fetchFunc('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, subject_id: currentSubjectId, due_date: dueDate, priority })
        });
        const data = await response.json();
        if (data.success) {
            titleEl.value = '';
            if (dueEl) dueEl.value = '';
            await loadTasks(currentSubjectId);
            await loadSubjects(); // refresh progress
        } else {
            alert(data.message || 'Failed to add task');
        }
    } catch (error) {
        console.error('Quick add error:', error);
        alert('An error occurred while adding task');
    }
}

/**
 * Close all modals
 */
function closeModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
}
window.closeModals = closeModals;

/**
 * Helper: Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Helper: Format date
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

/**
 * Helper: Show loading overlay
 */
function showLoading() {
    // Implement if needed, or use simple indicator
}

/**
 * Helper: Hide loading overlay
 */
function hideLoading() {
    // Implement if needed
}

/**
 * Helper: Show error notification
 */
function showError(message) {
    console.error(message);
    // Could add a toast notification here
}
