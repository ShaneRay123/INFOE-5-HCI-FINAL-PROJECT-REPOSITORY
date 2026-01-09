# Quick Start - Paano I-run sa Browser (XAMPP)

## Step 1: I-start ang XAMPP Services

1. I-open ang **XAMPP Control Panel**
   - I-search sa Start Menu: "XAMPP"
   - O i-open: `C:\xampp\xampp-control.exe`

2. I-start ang **Apache** at **MySQL**
   - I-click ang "Start" button sa Apache
   - I-click ang "Start" button sa MySQL
   - Dapat mag-green ang status

## Step 2: I-copy ang Project Files

I-copy ang buong Project folder sa `C:\xampp\htdocs\`

**Option A: Manual Copy**
1. I-copy ang folder: `C:\Users\Lenovo\Desktop\Project`
2. I-paste sa: `C:\xampp\htdocs\`
3. I-rename kung gusto mo (halimbawa: `study-planner`)

**Option B: Via Command (pwede rin)**
```cmd
xcopy "C:\Users\Lenovo\Desktop\Project" "C:\xampp\htdocs\study-planner\" /E /I
```

## Step 3: I-setup ang Database

1. I-open ang browser
2. Pumunta sa: **http://localhost/phpmyadmin**
3. I-click ang **"New"** sa left sidebar
4. Database name: **study_planner**
5. Collation: **utf8mb4_general_ci** (optional)
6. I-click **"Create"**

7. I-select ang **study_planner** database (i-click ang name)
8. I-click ang **"Import"** tab (sa top)
9. I-click **"Choose File"** button
10. Pumunta sa: `C:\xampp\htdocs\study-planner\database\`
11. Piliin ang **schema.sql**
12. I-click **"Go"** button sa baba
13. Dapat makikita mo: "Import has been successfully finished"

## Step 4: I-verify ang Database Connection

1. I-open ang file: `C:\xampp\htdocs\study-planner\config\database.php`
2. I-check na ganito ang settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'study_planner');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Usually walang password sa XAMPP
   ```

## Step 5: I-run sa Browser! 🚀

1. I-open ang browser (Chrome, Firefox, Edge, etc.)
2. Pumunta sa: **http://localhost/study-planner/**
3. Dapat makikita mo ang **Login Page**!
4. I-click **"Register here"** para gumawa ng account
5. Tapos mag-login ka na!

---

## Troubleshooting

### "This site can't be reached"
- ✅ I-check kung naka-start ang Apache sa XAMPP Control Panel
- ✅ I-restart ang Apache
- ✅ I-check kung naka-block ng firewall

### "Database connection failed"
- ✅ I-check kung naka-start ang MySQL sa XAMPP Control Panel
- ✅ I-verify na nagawa mo na ang `study_planner` database
- ✅ I-check ang database credentials sa `config/database.php`

### "404 Not Found"
- ✅ I-check kung tama ang folder name sa htdocs
- ✅ I-verify na nasa `C:\xampp\htdocs\study-planner\` ang files
- ✅ I-try: `http://localhost/` para makita ang list ng folders

### "Cannot access phpMyAdmin"
- ✅ I-check kung naka-start ang Apache
- ✅ I-try i-restart ang Apache
- ✅ I-access: `http://localhost/phpmyadmin`

---

## Quick Checklist ✅

Bago mo i-run:
- [ ] Naka-start ang Apache sa XAMPP
- [ ] Naka-start ang MySQL sa XAMPP
- [ ] Na-copy mo na ang files sa htdocs
- [ ] Nagawa mo na ang database sa phpMyAdmin
- [ ] Na-import mo na ang schema.sql
- [ ] Na-check mo na ang database.php settings

Tapos:
- [ ] I-open ang browser
- [ ] Pumunta sa: http://localhost/study-planner/
- [ ] Register at Login!

---

**Good luck! Message ka lang kung may problem!** 😊


