<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login-page.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'student';

if ($action === 'create') {
    $roomId = (int) $_POST['room_id'];
    $building = $_POST['building'] ?? '';
    $issueType = $_POST['issue_type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    
    // Validate
    if ($roomId <= 0 || empty($issueType) || empty($description)) {
        $_SESSION['schedule_flash'] = 'Please fill out all fields.';
        $_SESSION['schedule_flash_type'] = 'error';
        header("Location: comlab-map.php?building=" . urlencode($building) . "&room_id=" . $roomId);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO room_reports (room_id, reported_by, issue_type, description, status) VALUES (?, ?, ?, ?, 'pending')");
    if ($stmt) {
        $stmt->bind_param('iiss', $roomId, $userId, $issueType, $description);
        if ($stmt->execute()) {
            $_SESSION['schedule_flash'] = 'Room issue reported successfully.';
            $_SESSION['schedule_flash_type'] = 'success';
        } else {
            $_SESSION['schedule_flash'] = 'Database error: Could not submit report.';
            $_SESSION['schedule_flash_type'] = 'error';
        }
        $stmt->close();
    }
    header("Location: comlab-map.php?building=" . urlencode($building) . "&room_id=" . $roomId);
    exit;
}

if ($action === 'update_status' && $userRole === 'admin') {
    $reportId = (int) $_POST['report_id'];
    $status = $_POST['status'] ?? 'pending';
    
    if ($reportId > 0 && in_array($status, ['pending', 'resolved', 'dismissed'])) {
        if ($status === 'resolved') {
            $stmt = $conn->prepare("UPDATE room_reports SET status = ?, resolved_at = CURRENT_TIMESTAMP, resolved_by = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('sii', $status, $userId, $reportId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE room_reports SET status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $status, $reportId);
                $stmt->execute();
                $stmt->close();
            }
        }
        $_SESSION['admin_report_flash'] = "Report marked as {$status}.";
        $_SESSION['admin_report_flash_type'] = 'success';
    } else {
        $_SESSION['admin_report_flash'] = "Invalid status update.";
        $_SESSION['admin_report_flash_type'] = 'error';
    }
    header("Location: admin-reports.php");
    exit;
}

// Fallback
header("Location: comlab-map.php");
exit;
