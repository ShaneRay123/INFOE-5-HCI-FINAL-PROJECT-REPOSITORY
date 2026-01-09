# Paano I-run ang Study Planner (How to Run)

## Option 1: Gamit ang XAMPP (Pinakamadali para sa Windows)

### Step 1: I-download at i-install ang XAMPP
1. I-download ang XAMPP: https://www.apachefriends.org/
2. I-install ang XAMPP (default location: `C:\xampp`)
3. I-open ang XAMPP Control Panel
4. I-start ang **Apache** at **MySQL** (click Start button)

### Step 2: I-setup ang Database
1. I-open ang browser
2. Pumunta sa: `http://localhost/phpmyadmin`
3. I-click ang "New" sa left sidebar para gumawa ng bagong database
4. Pangalan: `study_planner`
5. I-click "Create"

6. I-select ang `study_planner` database
7. I-click ang "Import" tab
8. I-click "Choose File" at piliin ang: `database/schema.sql`
9. I-click "Go" sa baba

### Step 3: I-copy ang Project Files
1. I-copy ang buong Project folder
2. I-paste sa: `C:\xampp\htdocs\`
3. I-rename kung gusto mo (halimbawa: `study-planner`)

### Step 4: I-configure ang Database Connection
1. I-open ang folder: `C:\xampp\htdocs\study-planner\config\`
2. I-edit ang `database.php` (right-click > Open with > Notepad)
3. I-verify na ganito ang settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'study_planner');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Walang password sa XAMPP default
   ```

### Step 5: I-run ang Application
1. I-open ang browser
2. Pumunta sa: `http://localhost/study-planner/`
3. Dapat makikita mo ang login page!
4. I-click "Register here" para gumawa ng account
5. I-fill up ang registration form
6. Tapos mag-login ka na!

---

## Option 2: Gamit ang PHP Built-in Server (Mas simple, walang XAMPP)

### Step 1: I-check kung may PHP
1. I-open ang Command Prompt (cmd) o PowerShell
2. I-type: `php -v`
3. Kung may lumabas na version number, OK na!
4. Kung hindi, i-download ang PHP: https://www.php.net/downloads.php

### Step 2: I-setup ang MySQL Database
- Kailangan pa rin ng MySQL
- Pwede mong i-install ang MySQL separately
- O gumamit ng XAMPP para sa MySQL lang

### Step 3: I-run ang Server
1. I-open ang Command Prompt
2. I-navigate sa project folder:
   ```cmd
   cd C:\Users\Lenovo\Desktop\Project
   ```
3. I-run ang PHP server:
   ```cmd
   php -S localhost:8000
   ```
4. Dapat makikita mo: "Development Server started at http://localhost:8000"

### Step 4: I-open sa Browser
1. I-open ang browser
2. Pumunta sa: `http://localhost:8000`
3. Tapos na!

---

## Option 3: Gamit ang WAMP (Windows Alternative)

### Step 1: I-install ang WAMP
1. I-download: https://www.wampserver.com/
2. I-install (default: `C:\wamp64`)

### Step 2-5: Pareho sa XAMPP
- I-copy ang files sa: `C:\wamp64\www\`
- I-setup ang database sa phpMyAdmin
- I-access sa: `http://localhost/study-planner/`

---

## Quick Checklist

Bago mo i-run, siguraduhin:
- [ ] Naka-install ang PHP (7.4 o mas bago)
- [ ] Naka-install ang MySQL
- [ ] Naka-start ang MySQL service
- [ ] Nagawa mo na ang database na `study_planner`
- [ ] Na-import mo na ang `schema.sql`
- [ ] Na-configure mo na ang `config/database.php`
- [ ] Naka-start ang Apache (kung XAMPP/WAMP) o PHP server

---

## Troubleshooting

### "Database connection failed"
- I-check kung naka-start ang MySQL
- I-verify ang database credentials sa `config/database.php`
- I-sigurado na nagawa mo na ang database

### "Cannot access phpMyAdmin"
- I-check kung naka-start ang Apache sa XAMPP Control Panel
- I-try: `http://localhost/phpmyadmin`

### "404 Not Found"
- I-check kung tama ang folder path
- I-verify kung naka-start ang Apache
- I-try: `http://localhost/` para makita kung ano ang available

### "This site can't be reached"
- I-check kung naka-start ang Apache/Server
- I-try i-restart ang XAMPP/WAMP
- I-check kung may firewall blocking

---

## Para sa Free Hosting (InfinityFree, 000WebHost)

1. I-upload ang lahat ng files via FTP o File Manager
2. I-create ang database sa hosting control panel
3. I-import ang `schema.sql` via phpMyAdmin
4. I-update ang `config/database.php` na may hosting database credentials
5. I-access ang site sa provided domain

---

**Good luck! Message ka lang kung may tanong!** 🚀


