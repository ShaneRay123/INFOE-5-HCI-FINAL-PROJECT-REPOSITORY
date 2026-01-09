<?php
require_once 'config/auth.php';
redirectIfNotLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Study Planner</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="dashboard.php" style="text-decoration: none; color: inherit;">
                    <h1><i class="fas fa-book-open"></i> Study Planner</h1>
                </a>
            </div>
            <div class="nav-user">
                <a href="dashboard.php" class="nav-link active">Dashboard</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <span id="userName"></span>
                <button id="logoutBtn" class="btn btn-secondary btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <div class="dashboard-header">
            <h2>My Study Plans</h2>
            <button id="addSubjectBtn" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Subject
            </button>
        </div>

        <div id="subjectsContainer" class="subjects-grid">
            <!-- Subjects will be loaded here -->
        </div>

        <div id="tasksContainer" class="tasks-section" style="display: none;">
            <div class="tasks-header">
                <h3 id="tasksSubjectTitle"></h3>
                <div class="tasks-actions">
                    <select id="taskView" class="form-control" style="max-width: 160px;">
                        <option value="list">View: List</option>
                        <option value="calendar">View: Calendar</option>
                    </select>
                    <select id="taskFilterStatus" class="form-control" style="max-width: 180px;">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                    <select id="taskSort" class="form-control" style="max-width: 200px;">
                        <option value="due_date_asc">Sort: Due Date ↑</option>
                        <option value="due_date_desc">Sort: Due Date ↓</option>
                        <option value="priority_desc">Sort: Priority</option>
                        <option value="created_desc">Sort: Newest</option>
                    </select>
                    <label class="switch" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="groupByStatusToggle" />
                        <span>Group by status</span>
                    </label>
                    <button id="addTaskBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
            </div>
            <div class="quick-add-row" style="display: none;" id="quickAddRow">
                <input type="text" id="quickTaskTitle" class="form-control" placeholder="Quick add task..." style="max-width: 280px;">
                <input type="date" id="quickTaskDue" class="form-control" style="max-width: 180px;">
                <select id="quickTaskPriority" class="form-control" style="max-width: 160px;">
                    <option value="medium" selected>Priority: Medium</option>
                    <option value="low">Priority: Low</option>
                    <option value="high">Priority: High</option>
                </select>
                <button id="quickTaskAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button>
            </div>
            <div id="tasksList" class="tasks-list">
                <!-- Tasks will be loaded here -->
            </div>
            <div id="tasksCalendar" class="tasks-calendar" style="display: none;">
                <!-- Calendar view -->
            </div>
        </div>
    </main>

    <!-- Subject Modal -->
    <div id="subjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="subjectModalTitle">Add Subject</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="subjectForm">
                <input type="hidden" id="subjectId">
                <div class="form-group">
                    <label for="subjectName">Subject Name *</label>
                    <input type="text" id="subjectName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="subjectColor">Color</label>
                    <input type="color" id="subjectColor" class="form-control" style="height: 40px;" value="#4f46e5">
                </div>
                <div class="form-group">
                    <label for="subjectDescription">Description</label>
                    <textarea id="subjectDescription" class="form-control" rows="3"></textarea>
                </div>
                <div id="subjectErrorMessage" class="error-message" style="display: none; color: var(--danger-color); margin-bottom: 1rem;"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-text">Save</span>
                        <span class="btn-loader" style="display: none;"><i class="fas fa-spinner fa-spin"></i></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="taskModalTitle">Add Task</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="taskForm">
                <input type="hidden" id="taskId">
                <div class="form-group">
                    <label for="taskTitle">Task Title *</label>
                    <input type="text" id="taskTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="taskSubject">Subject *</label>
                    <select id="taskSubject" class="form-control" required></select>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="taskDueDate">Due Date</label>
                        <input type="date" id="taskDueDate" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="taskPriority">Priority</label>
                        <select id="taskPriority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" class="form-control" rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
