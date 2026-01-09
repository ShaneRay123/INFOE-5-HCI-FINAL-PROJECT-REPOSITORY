# Troubleshooting "404 Not Found" Error

## Checklist - I-verify mo ito:

### 1. ✅ I-check kung naka-start ang Apache
- I-open ang XAMPP Control Panel
- Dapat **GREEN** ang status ng Apache
- Kung hindi, i-click ang "Start" button

### 2. ✅ I-check kung tama ang folder name
I-try mo i-access ang:
- `http://localhost/study-planner/`
- `http://localhost/Study-Planner/` (capital S)
- `http://localhost/Project/` (kung hindi mo na-rename)

O i-check mo kung anong folder name:
- Pumunta sa: `C:\xampp\htdocs\`
- I-check kung ano ang exact folder name

### 3. ✅ I-try i-access ang root
- Pumunta sa: `http://localhost/`
- Dapat makikita mo ang list ng folders
- I-click mo kung anong folder ang nakita mo

### 4. ✅ I-check kung nasa tamang location ang files
Dapat nasa: `C:\xampp\htdocs\study-planner\` (o anong folder name)

---

## Common Solutions:

### Solution 1: I-check ang exact URL
I-try mo lahat ng variations:
- `http://localhost/study-planner/`
- `http://localhost/study-planner/index.php`
- `http://localhost/study-planner/login.php`
- `http://localhost/Project/` (kung yun ang folder name)

### Solution 2: I-restart ang Apache
1. Sa XAMPP Control Panel, i-click "Stop" sa Apache
2. Wait 2 seconds
3. I-click "Start" ulit
4. I-try ulit sa browser

### Solution 3: I-check kung anong folder ang available
1. I-open ang browser
2. Pumunta sa: `http://localhost/`
3. Makikita mo ang list ng folders
4. I-click mo kung anong folder ang nakita mo

### Solution 4: I-verify ang file location
1. I-open ang File Explorer
2. Pumunta sa: `C:\xampp\htdocs\`
3. I-check kung may folder na `study-planner` o `Project`
4. I-open ang folder at i-check kung may `index.php` file

---

## Kung Wala Pa Ring Folder:

I-copy mo ulit manually:
1. I-open ang File Explorer
2. Pumunta sa: `C:\Users\Lenovo\Desktop\Project`
3. I-select lahat ng files (Ctrl+A)
4. I-copy (Ctrl+C)
5. Pumunta sa: `C:\xampp\htdocs\`
6. I-create ng bagong folder: `study-planner`
7. I-paste ang files (Ctrl+V)

---

**Message mo kung anong error message ang nakikita mo, o kung anong URL ang tinatry mo!** 🔍


