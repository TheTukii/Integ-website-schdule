<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: comlab-map.php');
    exit;
}

$role = $_SESSION['user_role'] ?? '';
if ($role !== 'admin') {
    $_SESSION['schedule_flash'] = 'Only admin can change room status.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php');
    exit;
}

$roomId = (int) ($_POST['room_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowed = ['available', 'occupied', 'out_of_service'];

if ($roomId <= 0 || !in_array($status, $allowed, true)) {
    $_SESSION['schedule_flash'] = 'Invalid room status update request.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php?room_id=' . $roomId);
    exit;
}

$stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $roomId);
$ok = $stmt->execute();
$stmt->close();

$_SESSION['schedule_flash'] = $ok ? 'Room status updated.' : 'Unable to update room status.';
$_SESSION['schedule_flash_type'] = $ok ? 'success' : 'error';
header('Location: comlab-map.php?room_id=' . $roomId);
exit;
