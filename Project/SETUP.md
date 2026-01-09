# Quick Setup Guide

## For Local Development (XAMPP/WAMP/MAMP)

### Step 1: Setup Database
1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Create a new database named `study_planner`
3. Click on the database, then go to "Import" tab
4. Choose file: `database/schema.sql`
5. Click "Go" to import

### Step 2: Configure Database
1. Open `config/database.php`
2. Update credentials (usually these work for XAMPP/WAMP):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'study_planner');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Usually empty for local
   ```

### Step 3: Copy Files
1. Copy entire project folder to:
   - XAMPP: `C:\xampp\htdocs\study-planner\`
   - WAMP: `C:\wamp64\www\study-planner\`
   - MAMP: `/Applications/MAMP/htdocs/study-planner/`

### Step 4: Access Application
1. Open browser
2. Go to: `http://localhost/study-planner/`
3. Register a new account
4. Start using the application!

---

## For Free Hosting (InfinityFree, 000WebHost)

### Step 1: Upload Files
1. Login to your hosting control panel
2. Open File Manager
3. Upload all project files to `public_html` or `htdocs` folder

### Step 2: Create Database
1. In hosting control panel, find "MySQL Databases" or "Database" section
2. Create a new database (note the database name)
3. Create a database user (note username and password)
4. Assign user to database with all privileges

### Step 3: Configure Database Connection
1. Edit `config/database.php` via File Manager
2. Update with hosting database details:
   ```php
   define('DB_HOST', 'localhost');  // Or hosting provided host (e.g., mysql.hosting.com)
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

### Step 4: Import Database Schema
1. Find phpMyAdmin in hosting control panel
2. Select your database
3. Click "Import" tab
4. Upload `database/schema.sql`
5. Click "Go"

### Step 5: Access Your Site
1. Visit your hosting domain (e.g., `yourname.infinityfreeapp.com`)
2. Register and login
3. Enjoy your Study Planner!

---

## Troubleshooting

### "Database connection failed"
- ✅ Check database credentials in `config/database.php`
- ✅ Verify database exists
- ✅ Check database user has proper permissions
- ✅ For hosting: Use the exact database host provided by hosting

### "Session error" or "Headers already sent"
- ✅ Ensure no whitespace before `<?php` tags
- ✅ Check no BOM (Byte Order Mark) in PHP files
- ✅ Verify PHP version is 7.4 or higher

### "404 Not Found" or "File not found"
- ✅ Verify all files are uploaded correctly
- ✅ Check file paths are correct
- ✅ Ensure `.htaccess` is uploaded (if using Apache)

### AJAX requests not working
- ✅ Open browser Developer Tools (F12)
- ✅ Check Console tab for JavaScript errors
- ✅ Check Network tab to see if API requests are being made
- ✅ Verify API endpoints are accessible (try direct URL access)

---

## Testing Checklist

After setup, test these features:

- [ ] Can register a new account
- [ ] Can login with registered credentials
- [ ] Can create a subject
- [ ] Can edit a subject
- [ ] Can delete a subject
- [ ] Can create a task
- [ ] Can edit a task
- [ ] Can delete a task
- [ ] Can view tasks by subject
- [ ] Can logout
- [ ] Cannot access dashboard without login

---

## Common Database Hosts for Free Hosting

- **InfinityFree**: Usually `localhost` or `sqlXXX.infinityfree.com`
- **000WebHost**: Usually `localhost`
- **Freehostia**: Usually `mysql.freehostia.com`
- Check your hosting control panel for exact database host address

---

## Support

If you encounter issues:
1. Check error logs in hosting control panel
2. Enable error display (temporarily) by adding to `config/database.php`:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
3. Check PHP version compatibility (7.4+)
4. Verify all PHP extensions are enabled: PDO, PDO_MySQL, session


