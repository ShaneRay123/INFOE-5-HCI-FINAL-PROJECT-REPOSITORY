# RUN NOW - 5 Steps Lang! ⚡

## ✅ STEP 1: I-start ang XAMPP Services

1. I-open ang **XAMPP Control Panel**
   - I-search sa Start Menu: **"XAMPP"**
   - O i-double click: `C:\xampp\xampp-control.exe`

2. I-start ang services:
   - I-click **"Start"** sa **Apache** (dapat mag-green)
   - I-click **"Start"** sa **MySQL** (dapat mag-green)

## ✅ STEP 2: I-setup ang Database sa phpMyAdmin

1. I-open ang browser
2. Pumunta sa: **http://localhost/phpmyadmin**
3. Sa left sidebar, i-click ang **"New"** button
4. Database name: type mo **study_planner**
5. Collation: piliin **utf8mb4_general_ci** (o iwanan lang default)
6. I-click **"Create"**

7. I-select ang **study_planner** database (i-click ang name sa left)
8. I-click ang **"Import"** tab (sa top menu)
9. I-click **"Choose File"** o **"Browse"**
10. Pumunta sa folder: `C:\xampp\htdocs\study-planner\database\`
11. Piliin ang file: **schema.sql**
12. I-click **"Go"** button (sa baba)
13. Dapat makita mo: **"Import has been successfully finished"** ✅

## ✅ STEP 3: I-check ang Database Settings

1. I-open ang file: `C:\xampp\htdocs\study-planner\config\database.php`
   - Right-click > Open with > Notepad
   
2. I-verify na ganito ang settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'study_planner');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Empty string, walang password
   ```

3. I-save kung may binago

## ✅ STEP 4: I-run sa Browser! 🚀

1. I-open ang browser (Chrome, Firefox, Edge, etc.)
2. Sa address bar, type mo:
   ```
   http://localhost/study-planner/
   ```
3. I-press Enter
4. **Dapat makikita mo na ang LOGIN PAGE!** 🎉

## ✅ STEP 5: Gumawa ng Account

1. I-click ang **"Register here"** link (sa baba ng login form)
2. Fill up ang form:
   - Full Name: (halimbawa: Juan dela Cruz)
   - Username: (halimbawa: juan)
   - Email: (halimbawa: juan@email.com)
   - Password: (minimum 6 characters)
3. I-click **"Register"**
4. Auto-login at makikita mo na ang **Dashboard**! 🎊

---

## Kung May Error ❌

### "This site can't be reached" o "404 Not Found"
- ✅ I-check kung naka-start ang **Apache** sa XAMPP
- ✅ I-restart ang Apache (Stop then Start)
- ✅ I-verify na nasa `C:\xampp\htdocs\study-planner\` ang files
- ✅ I-try: `http://localhost/` para makita kung anong folders ang available

### "Database connection failed"
- ✅ I-check kung naka-start ang **MySQL** sa XAMPP
- ✅ I-verify na nagawa mo na ang database na `study_planner`
- ✅ I-check kung na-import mo na ang `schema.sql`
- ✅ I-try i-restart ang MySQL

### "Cannot access phpMyAdmin"
- ✅ I-check kung naka-start ang Apache
- ✅ I-try: `http://localhost/phpmyadmin` ulit
- ✅ I-restart ang Apache

---

## Quick Test Checklist ✅

Bago mo i-run:
- [ ] Naka-start ang Apache (green sa XAMPP)
- [ ] Naka-start ang MySQL (green sa XAMPP)
- [ ] Nagawa na ang `study_planner` database
- [ ] Na-import na ang `schema.sql`
- [ ] Na-check na ang `database.php` settings

Tapos:
- [ ] I-open: `http://localhost/study-planner/`
- [ ] Makikita mo ang Login page
- [ ] Register ka ng account
- [ ] Login at makikita mo ang Dashboard!

---

**Ready na! I-try mo na sa browser!** 🚀😊


