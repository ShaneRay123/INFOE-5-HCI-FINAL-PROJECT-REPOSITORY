# Study Planner Web Application

A fully functional, dynamic Study Planner web application built with Core PHP, MySQL, HTML, CSS, and JavaScript. This application helps students organize their study schedules, subjects, and tasks efficiently.

## Features

- ✅ **User Authentication**: Secure registration and login system with PHP sessions
- ✅ **Password Security**: Passwords hashed using `password_hash()` and verified with `password_verify()`
- ✅ **Subject Management**: Create, read, update, and delete study subjects with color coding
- ✅ **Task Management**: Full CRUD operations for study tasks with priority and status tracking
- ✅ **Dynamic UI**: AJAX-powered interface that updates without page refresh
- ✅ **Responsive Design**: Modern, mobile-friendly interface using Flexbox and Grid
- ✅ **User Experience**: Loading indicators, error messages, and intuitive interactions
- ✅ **Database Relationships**: Properly structured database with foreign key relationships

## Technology Stack

- **Backend**: Core PHP (no framework)
- **Database**: MySQL with PDO for secure database access
- **Frontend**: HTML5, CSS3 (Flexbox/Grid), Vanilla JavaScript
- **API**: RESTful API-style structure with JSON responses

## Project Structure

```
Project/
│
├── api/                    # API endpoints
│   ├── login.php          # User login
│   ├── register.php       # User registration
│   ├── logout.php         # User logout
│   ├── user.php           # Get current user info
│   ├── subjects.php       # Subjects CRUD operations
│   └── tasks.php          # Tasks CRUD operations
│
├── assets/                # Static assets
│   ├── css/
│   │   └── style.css      # Main stylesheet
│   └── js/
│       ├── auth.js        # Authentication JavaScript
│       └── dashboard.js   # Dashboard JavaScript
│
├── config/                # Configuration files
│   ├── database.php       # Database connection
│   └── auth.php           # Authentication helpers
│
├── database/              # Database files
│   └── schema.sql         # Database schema
│
├── index.php              # Entry point (redirects to login)
├── login.php              # Login page
├── register.php           # Registration page
├── dashboard.php          # Main dashboard
│
└── README.md              # This file
```

## Installation & Setup

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or PHP built-in server
- PHP extensions: PDO, PDO_MySQL, session

### Step 1: Database Setup

1. Create a MySQL database:
```sql
CREATE DATABASE study_planner;
```

2. Import the database schema:
   - Open phpMyAdmin or your MySQL client
   - Select the `study_planner` database
   - Import `database/schema.sql` file
   - Or run the SQL commands from `database/schema.sql`

### Step 2: Configure Database Connection

1. Open `config/database.php`
2. Update the database credentials:

```php
define('DB_HOST', 'localhost');      // Your database host
define('DB_NAME', 'study_planner');   // Database name
define('DB_USER', 'root');            // Database username
define('DB_PASS', '');                // Database password
```

### Step 3: Deploy Files

**For Local Development:**
1. Place all files in your web server directory (e.g., `htdocs`, `www`, or `public_html`)
2. Start PHP built-in server (if not using Apache/Nginx):
```bash
php -S localhost:8000
```
3. Open browser and navigate to `http://localhost:8000`

**For Free Hosting (InfinityFree, 000WebHost, etc.):**
1. Upload all files via FTP or File Manager
2. Ensure PHP version is 7.4 or higher
3. Create database using hosting control panel
4. Update `config/database.php` with hosting database credentials
5. Import `database/schema.sql` via phpMyAdmin
6. Access your site via the provided domain

### Step 4: File Permissions (Linux/Unix)

Ensure proper file permissions:
```bash
chmod 755 -R .
chmod 644 config/database.php
```

## Usage Guide

### User Registration

1. Navigate to the registration page
2. Fill in:
   - Full Name
   - Username (minimum 3 characters)
   - Email (valid email format)
   - Password (minimum 6 characters)
3. Click "Register"
4. You'll be automatically logged in and redirected to the dashboard

### Creating Subjects

1. From the dashboard, click "Add Subject"
2. Enter:
   - Subject Name (required)
   - Color (for visual organization)
   - Description (optional)
3. Click "Save"
4. Subject appears in the grid

### Managing Tasks

1. Click on any subject card to view its tasks
2. Click "Add Task" to create a new task
3. Fill in task details:
   - Title (required)
   - Subject
   - Description (optional)
   - Due Date (optional)
   - Priority (Low, Medium, High)
   - Status (Pending, In Progress, Completed)
4. Click "Save"

### Editing & Deleting

- **Edit Subject/Task**: Click the edit icon (✏️) or "Edit" button
- **Delete Subject/Task**: Click the delete icon (🗑️) or "Delete" button
- Deleting a subject will also delete all its associated tasks

## API Endpoints

### Authentication

- `POST /api/register.php` - Register new user
- `POST /api/login.php` - User login
- `GET /api/logout.php` - User logout
- `GET /api/user.php` - Get current user info

### Subjects

- `GET /api/subjects.php` - Get all subjects
- `POST /api/subjects.php` - Create subject
- `PUT /api/subjects.php` - Update subject
- `DELETE /api/subjects.php` - Delete subject

### Tasks

- `GET /api/tasks.php` - Get all tasks
- `GET /api/tasks.php?subject_id=X` - Get tasks for specific subject
- `POST /api/tasks.php` - Create task
- `PUT /api/tasks.php` - Update task
- `DELETE /api/tasks.php` - Delete task

All API endpoints return JSON responses in the format:
```json
{
    "success": true/false,
    "message": "Response message",
    "data": {...}
}
```

## Security Features

- ✅ Password hashing using `password_hash()` with PASSWORD_DEFAULT
- ✅ Password verification using `password_verify()`
- ✅ Prepared statements with PDO to prevent SQL injection
- ✅ Session-based authentication
- ✅ Input validation and sanitization
- ✅ XSS protection with HTML escaping
- ✅ User ownership verification for all operations

## Database Schema

### Users Table
- `id` - Primary key
- `username` - Unique username
- `email` - Unique email
- `password` - Hashed password
- `full_name` - User's full name
- `created_at` - Registration timestamp

### Subjects Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `name` - Subject name
- `color` - Color code (hex)
- `description` - Subject description
- `created_at` - Creation timestamp

### Tasks Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `subject_id` - Foreign key to subjects
- `title` - Task title
- `description` - Task description
- `due_date` - Due date
- `priority` - Priority level (low/medium/high)
- `status` - Task status (pending/in_progress/completed)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

## Deployment on Free Hosting

### InfinityFree

1. Sign up at [infinityfree.net](https://www.infinityfree.net)
2. Create a new account and website
3. Upload files via FTP (FileZilla) or File Manager
4. Create MySQL database in control panel
5. Update `config/database.php` with database credentials
6. Import `database/schema.sql` via phpMyAdmin

### 000WebHost

1. Sign up at [000webhost.com](https://www.000webhost.com)
2. Create a new website
3. Upload files via File Manager or FTP
4. Create MySQL database
5. Update database configuration
6. Import schema via phpMyAdmin

### Ngrok (For Testing)

1. Install ngrok: `npm install -g ngrok`
2. Start local server: `php -S localhost:8000`
3. Start ngrok: `ngrok http 8000`
4. Use the provided ngrok URL to access your app

## Troubleshooting

### Database Connection Error
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database name, username, and password

### Session Issues
- Ensure PHP sessions are enabled
- Check `session.save_path` in php.ini
- Verify file permissions

### AJAX Not Working
- Check browser console for JavaScript errors
- Verify API endpoints are accessible
- Ensure Content-Type headers are set correctly

### 500 Internal Server Error
- Check PHP error logs
- Verify file permissions
- Ensure all required PHP extensions are installed

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Future Enhancements

Potential improvements for future versions:
- Email notifications for due tasks
- Calendar view for tasks
- Task filtering and search
- Export tasks to PDF/CSV
- Dark mode theme
- Task reminders
- Study statistics and analytics

## License

This project is open source and available for educational purposes.

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review PHP error logs
3. Verify database connectivity
4. Test API endpoints directly

## Credits

Built with Core PHP, MySQL, HTML5, CSS3, and JavaScript.
Designed for academic submission and project defense.

---

**Note**: Remember to update database credentials before deployment and never commit sensitive information to version control.


