# Student Wellness Hub

A comprehensive web application for student mental health assessments and counseling appointment management.

## Features

- **User Management**: Admin can create, update and delete users (students and counselors)
- **Assessment Tools**: Counselors can create and manage assessment forms
- **Appointment Scheduling**: Students can schedule appointments with counselors
- **Profile Management**: Users can update their personal information
- **Calendar View**: Visual representation of appointments

## Installation

1. Clone or download this repository to your XAMPP htdocs directory
2. Import the database schema from `database.sql` or let the application create it automatically
3. Access the application through your browser: `http://localhost/syu/`

## Default Credentials

- **Admin/Counselor**:
  - Username: admin
  - Password: admin123

## System Requirements

- PHP 7.2 or higher
- MySQL 5.7 or higher
- Apache web server

## File Structure

- `/admin` - Contains all counselor/admin functionality
- `/student` - Contains all student user functionality
- `/includes` - Contains helper functions and common code
- `/uploads` - Storage for user profile photos

## Security Notes

- Image uploads are restricted to JPG files only
- User passwords are hashed with PHP's password_hash() function
- The .htaccess file in the uploads directory prevents execution of PHP files

## License

Copyright © 2023 Student Wellness Hub. All rights reserved.

# Student Wellness Hub System

## Database Setup Instructions

There are two ways to set up the database:

### Option 1: Automatic Setup (Recommended)
1. The system automatically creates the database and all required tables when first accessed
2. Simply navigate to `http://localhost/syu/` in your browser
3. Default admin credentials:
   - Username: admin
   - Password: admin123

### Option 2: Manual Database Import
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `syu`
3. Select the newly created database
4. Click on the "Import" tab
5. Choose the `database.sql` file from the project directory
6. Click "Go" to import the database structure and default admin account

## System Features

### Student Features:
- Take wellness assessments
- View completed assessments and responses
- Schedule and manage counseling appointments
- Update profile information including department, course year, and section
- View appointment calendar

### Counselor/Admin Features:
- Create and manage assessment tools
- Review student assessment responses
- Manage user accounts
- Schedule and respond to appointment requests
- Dashboard with system statistics

## Default Login Credentials

### Administrator/Counselor:
- Username: admin
- Password: admin123

### Student:
- Register through the registration page

## Technical Information
- PHP 7.2+ required
- MySQL/MariaDB database
- Bootstrap 4.5 front-end framework
- Font Awesome 5 icons
