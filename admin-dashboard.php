<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login-page.php');
    exit;
}

header('Location: comlab-map.php');
exit;
