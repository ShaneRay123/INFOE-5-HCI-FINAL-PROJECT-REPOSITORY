<?php
require_once 'config/auth.php';
redirectIfLoggedIn();
header('Location: login.php');
exit;
?>

