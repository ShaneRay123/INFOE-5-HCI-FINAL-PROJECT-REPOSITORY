<?php
require_once 'config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Wellness Hub</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
      body {
          background-image: url('ASAT.jpg');
          background-repeat: no-repeat;
          background-size: cover;
          background-position: center;
          background-attachment: fixed;
          position: relative;
          color: #fff;
          opacity: 0;
          animation: fadeInBody 0.8s ease-in forwards;
      }

      @keyframes fadeInBody {
          from { opacity: 0; }
          to { opacity: 1; }
      }

      body::before {
          content: "";
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.55);
          z-index: -1;
          animation: fadeInOverlay 0.8s ease-in;
      }

      @keyframes fadeInOverlay {
          from { opacity: 0; }
          to { opacity: 1; }
      }

      .navbar, footer {
          background-color: #2c2f33 !important;
          opacity: 0;
          animation: fadeSlideDown 0.7s ease forwards;
      }

      @keyframes fadeSlideDown {
          from { opacity: 0; transform: translateY(-15px); }
          to { opacity: 1; transform: translateY(0); }
      }

      .jumbotron {
          background: rgba(179, 1, 4, 0.8);
          color: white;
          border-radius: 0;
          text-align: center;
          padding: 3rem 2rem;
          opacity: 0;
          transform: translateY(20px);
          animation: fadeUp 0.9s ease forwards 0.2s;
      }

      @keyframes fadeUp {
          to {
              opacity: 1;
              transform: translateY(0);
          }
      }

      #logo {
          max-width: 110px;
          height: auto;
          margin-right: 15px;
          transition: transform 0.25s ease;
      }

      #logo:hover {
          transform: scale(1.05) rotate(-2deg);
      }

      .card {
          margin-bottom: 20px;
          box-shadow: 0 4px 8px rgba(0,0,0,0.25);
          border: none;
          border-radius: 10px;
          background-color: #fff;
          color: #333;
          opacity: 0;
          transform: translateY(30px);
          animation: fadeUp 0.8s ease forwards;
      }

      .card:nth-child(1) { animation-delay: 0.3s; }
      .card:nth-child(2) { animation-delay: 0.45s; }

      .card-header {
          background-color: #b30104;
          color: white;
          border-radius: 10px 10px 0 0;
      }

      .btn-primary, .btn-secondary {
          transition: all 0.25s ease;
      }

      .btn-primary {
          background-color: #b30104;
          border: none;
      }

      .btn-primary:hover {
          background-color: #920003;
          transform: scale(1.05);
      }

      .btn-secondary {
          background-color: #2c2f33;
          border: none;
      }

      .btn-secondary:hover {
          background-color: #1e2023;
          transform: scale(1.05);
      }

      footer {
          color: #ddd;
          opacity: 0;
          animation: fadeUp 0.8s ease forwards 0.6s;
      }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container">
          <a class="navbar-brand" href="index.php">Student Wellness Hub</a>
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
              <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarNav">
              <ul class="navbar-nav ml-auto">
                  <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                  <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
              </ul>
          </div>
      </div>
  </nav>

  <div class="jumbotron">
      <div class="container d-flex flex-column flex-md-row align-items-center justify-content-center">
          <img src="ua1 (1).png" alt="Logo" id="logo">
          <div class="text-center text-md-left">
              <h1 class="display-4">Welcome to Student Wellness Hub</h1>
              <p class="lead">Online Assessment and Appointment Scheduling System</p>
          </div>
      </div>
  </div>

  <div class="container">
      <?php
      $flash = getFlashMessage();
      if ($flash) {
          echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">
                  ' . $flash['message'] . '
                  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>';
      }
      ?>
      
      <div class="row">
          <div class="col-md-6">
              <div class="card">
                  <div class="card-header"><h5>For Students</h5></div>
                  <div class="card-body">
                      <p>Access wellness resources, schedule counseling appointments, and take assessments to help monitor your well-being.</p>
                      <ul>
                          <li>Take wellness assessments</li>
                          <li>Schedule appointments with counselors</li>
                          <li>View and track your progress</li>
                          <li>Access wellness resources</li>
                      </ul>
                      <a href="register.php" class="btn btn-primary">Register Now</a>
                      <a href="login.php" class="btn btn-secondary">Student Login</a>
                  </div>
              </div>
          </div>
          <div class="col-md-6">
              <div class="card">
                  <div class="card-header"><h5>For Counselors</h5></div>
                  <div class="card-body">
                      <p>Efficiently manage student assessments, appointments, and track progress through our comprehensive system.</p>
                      <ul>
                          <li>Create and manage assessments</li>
                          <li>View student responses</li>
                          <li>Manage appointment schedules</li>
                          <li>Track student wellness metrics</li>
                      </ul>
                      <a href="login.php" class="btn btn-secondary">Counselor Login</a>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <footer class="text-white mt-5 py-3">
      <div class="container text-center">
          <p>&copy; <?php echo date('Y'); ?> Student Wellness Hub. All rights reserved.</p>
      </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
