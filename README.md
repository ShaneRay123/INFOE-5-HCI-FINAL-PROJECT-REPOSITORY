Here is your detailed guide to getting the Student Wellness Hub running on your machine.

1. Launch XAMPP Open the XAMPP Control Panel.

Click Start for Apache and MySQL.

2. Create the Database in phpMyAdmin The name must match exactly for the project to work:

Go to http://localhost/phpmyadmin/.

Click the "New" tab in the top left corner.

Under Database name, type: if0_40312270_db_userdata

Click Create.

3. Import Your Data On the left-hand list, click on the database you just created (if0_40312270_db_userdata).

Click the "Import" tab at the top.

Click Choose File and select your database.sql file (located inside your group_4-Buarao-Guilingen-Boston-Zamora folder).

Scroll to the bottom and click Import (or Go).

You should see a green success message saying "Import has been successfully finished."

4. Verify Your Folder Path Ensure your files are placed correctly so the URL works. Your path should look like this: C:\xampp\htdocs\group_4-Buarao-Guilingen-Boston-Zamora\index.php

5. Open Your Project Now, open your web browser and go to:

http://localhost/group_4-Buarao-Guilingen-Boston-Zamora/

6. Log In Use the default credentials from your project info:

Admin/Counselor Email: admin2@gmail.com

Password: Admin123

Register code for admin: SWHub2025

Common Fix: If you get a "Connection Error" Check the file named db_connect.php or config.php in your folder. Make sure it looks like this:

PHP

$servername = "localhost"; $username = "root"; $password = ""; // Leave blank for XAMPP $dbname = "if0_40312270_db_userdata";
