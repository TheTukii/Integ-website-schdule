<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login-page.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage-users.php');
    exit;
}

$action      = $_POST['action'] ?? '';
$targetUserId = (int) ($_POST['user_id'] ?? 0);
$adminId     = (int) $_SESSION['user_id'];

// Prevent acting on own account
if ($targetUserId <= 0 || $targetUserId === $adminId) {
    $_SESSION['user_manage_flash']      = 'Invalid operation.';
    $_SESSION['user_manage_flash_type'] = 'error';
    header('Location: manage-users.php');
    exit;
}

if ($action === 'change_role') {
    $newRole = $_POST['role'] ?? '';
    if (!in_array($newRole, ['student', 'instructor', 'admin'], true)) {
        $_SESSION['user_manage_flash']      = 'Invalid role selected.';
        $_SESSION['user_manage_flash_type'] = 'error';
        header('Location: manage-users.php');
        exit;
    }
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param('si', $newRole, $targetUserId);
    $ok = $stmt->execute();
    $stmt->close();

    $_SESSION['user_manage_flash']      = $ok ? 'User role updated successfully.' : 'Failed to update role.';
    $_SESSION['user_manage_flash_type'] = $ok ? 'success' : 'error';

} elseif ($action === 'delete') {
    // Also delete their schedules to avoid orphaned records
    $delSched = $conn->prepare("DELETE FROM schedules WHERE instructor_id = ?");
    $delSched->bind_param('i', $targetUserId);
    $delSched->execute();
    $delSched->close();

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $targetUserId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($ok && $affected > 0) {
        $_SESSION['user_manage_flash']      = 'User deleted successfully.';
        $_SESSION['user_manage_flash_type'] = 'success';
    } else {
        $_SESSION['user_manage_flash']      = 'Failed to delete user.';
        $_SESSION['user_manage_flash_type'] = 'error';
    }
} else {
    $_SESSION['user_manage_flash']      = 'Unknown action.';
    $_SESSION['user_manage_flash_type'] = 'error';
}

header('Location: manage-users.php');
exit;
