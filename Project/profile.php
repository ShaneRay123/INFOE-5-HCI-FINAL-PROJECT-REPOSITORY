<?php
require_once 'config/auth.php';
redirectIfNotLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Study Planner</title>
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
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="profile.php" class="nav-link active">Profile</a>
                <span id="userName"></span>
                <button id="logoutBtn" class="btn btn-secondary btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <div class="profile-container">
            <!-- Left Column: Profile Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                        <img id="profileAvatarDisplay" src="" alt="Profile" style="display: none;">
                    </div>
                    <h2 id="profileNameDisplay">User Name</h2>
                    <p id="profileRoleDisplay" class="text-muted">Student</p>
                </div>
                <div class="profile-info">
                    <div class="info-item">
                        <i class="fas fa-envelope"></i>
                        <span id="profileEmailDisplay">email@example.com</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <span id="profilePhoneDisplay">No phone added</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="profileLocationDisplay">No location added</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Edit Form -->
            <div class="profile-content">
                <div class="card">
                    <div class="card-header">
                        <h3>Edit Profile</h3>
                    </div>
                    <div class="card-body">
                        <form id="profileForm">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="fullName">Full Name</label>
                                    <input type="text" id="fullName" name="full_name" class="form-control">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="jobTitle">Role / Job Title</label>
                                    <input type="text" id="jobTitle" name="job_title" class="form-control" placeholder="e.g. Student, Developer">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="phone">Phone</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="+1 234 567 8900">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="location">Location</label>
                                    <input type="text" id="location" name="location" class="form-control" placeholder="City, Country">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="avatarUrl">Avatar URL</label>
                                <input type="url" id="avatarUrl" name="avatar_url" class="form-control" placeholder="https://example.com/avatar.jpg">
                            </div>

                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" rows="4" class="form-control" placeholder="Tell us about yourself..."></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>Study Stats</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem;">
                            <div class="stat-card">
                                <div class="stat-title">Subjects</div>
                                <div class="stat-value" id="statSubjects">0</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Tasks</div>
                                <div class="stat-value" id="statTasks">0</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Completed</div>
                                <div class="stat-value" id="statCompleted">0</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Completion Rate</div>
                                <div class="stat-value" id="statCompletionRate">0%</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-title">Due Soon (7d)</div>
                                <div class="stat-value" id="statDueSoon">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/auth.js"></script>
    <script src="assets/js/profile.js"></script>
</body>
</html>
