<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: comlab-map.php');
    exit;
}

$role = $_SESSION['user_role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!in_array($role, ['admin', 'instructor'], true)) {
    $_SESSION['schedule_flash'] = 'You do not have permission to change schedules.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php');
    exit;
}

$action     = $_POST['action'] ?? 'create';
$roomId     = (int) ($_POST['room_id'] ?? 0);
$subject    = trim($_POST['subject'] ?? '');
$section    = trim($_POST['section'] ?? '');
$dayOfWeek  = trim($_POST['day_of_week'] ?? '');
$timeStart  = trim($_POST['time_start'] ?? '');
$timeEnd    = trim($_POST['time_end'] ?? '');
$scheduleId = (int) ($_POST['schedule_id'] ?? 0);
// For create: admin may pick an instructor_id; instructor always self-assigns.
$postedInstructorId = (int) ($_POST['instructor_id'] ?? 0);

$validDays = ['Monday and Thursday', 'Tuesday and Wednesday'];

if ($action === 'delete') {
    if ($scheduleId <= 0 || $roomId <= 0) {
        $_SESSION['schedule_flash'] = 'Invalid schedule delete request.';
        $_SESSION['schedule_flash_type'] = 'error';
        header('Location: comlab-map.php?room_id=' . $roomId);
        exit;
    }

    if ($role === 'instructor') {
        $deleteStmt = $conn->prepare("DELETE FROM schedules WHERE id = ? AND room_id = ? AND instructor_id = ?");
        $deleteStmt->bind_param('iii', $scheduleId, $roomId, $userId);
    } else {
        $deleteStmt = $conn->prepare("DELETE FROM schedules WHERE id = ? AND room_id = ?");
        $deleteStmt->bind_param('ii', $scheduleId, $roomId);
    }
    $ok = $deleteStmt->execute();
    $affected = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($ok && $affected > 0) {
        $_SESSION['schedule_flash'] = 'Schedule deleted successfully.';
        $_SESSION['schedule_flash_type'] = 'success';
    } else {
        $_SESSION['schedule_flash'] = $role === 'instructor'
            ? 'Delete failed. Instructors can only delete their own schedules.'
            : 'Unable to delete schedule.';
        $_SESSION['schedule_flash_type'] = 'error';
    }
    header('Location: comlab-map.php?room_id=' . $roomId);
    exit;
}

if ($roomId <= 0 || $subject === '' || !in_array($dayOfWeek, $validDays, true) || $timeStart === '' || $timeEnd === '') {
    $_SESSION['schedule_flash'] = 'Please fill in all required schedule fields.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php?room_id=' . $roomId);
    exit;
}

if ($timeEnd <= $timeStart) {
    $_SESSION['schedule_flash'] = 'End time must be later than start time.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php?room_id=' . $roomId);
    exit;
}

// Instructors cannot create/update schedules when room is unavailable.
if (in_array($action, ['create', 'update'], true)) {
    $roomStatusStmt = $conn->prepare("SELECT status FROM rooms WHERE id = ? LIMIT 1");
    $roomStatusStmt->bind_param('i', $roomId);
    $roomStatusStmt->execute();
    $roomStatusResult = $roomStatusStmt->get_result()->fetch_assoc();
    $roomStatusStmt->close();

    if (!$roomStatusResult) {
        $_SESSION['schedule_flash'] = 'Selected room does not exist.';
        $_SESSION['schedule_flash_type'] = 'error';
        header('Location: comlab-map.php?room_id=' . $roomId);
        exit;
    }

    $roomStatus = $roomStatusResult['status'] ?? 'available';
    if ($role === 'instructor' && in_array($roomStatus, ['occupied', 'out_of_service'], true)) {
        $_SESSION['schedule_flash'] = 'This room is currently unavailable. You cannot create or edit schedules for occupied or out-of-service rooms.';
        $_SESSION['schedule_flash_type'] = 'error';
        header('Location: comlab-map.php?room_id=' . $roomId);
        exit;
    }
}

// Determine the instructor_id for CREATE:
// - Instructors always use their own ID.
// - Admins use the posted instructor_id if valid, else fall back to their own.
if ($role === 'instructor') {
    $instructorId = $userId;
} else {
    $instructorId = ($postedInstructorId > 0) ? $postedInstructorId : $userId;
}

$checkActor = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('admin', 'instructor') LIMIT 1");
$checkActor->bind_param('i', $instructorId);
$checkActor->execute();
$validActor = $checkActor->get_result()->num_rows > 0;
$checkActor->close();

if (!$validActor || $userId <= 0) {
    $_SESSION['schedule_flash'] = 'Unable to verify your account for scheduling.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php?room_id=' . $roomId);
    exit;
}

// For UPDATE as admin: preserve the original instructor_id from the DB.
if ($action === 'update' && $role === 'admin' && $scheduleId > 0) {
    $origStmt = $conn->prepare("SELECT instructor_id FROM schedules WHERE id = ? LIMIT 1");
    $origStmt->bind_param('i', $scheduleId);
    $origStmt->execute();
    $origRow = $origStmt->get_result()->fetch_assoc();
    $origStmt->close();
    if ($origRow) {
        $instructorId = (int) $origRow['instructor_id'];
    }
}

$hasConflict = false;
if ($action === 'update' && $scheduleId > 0) {
    $conflictStmt = $conn->prepare(
        "SELECT id FROM schedules
         WHERE room_id = ? AND day_of_week = ? AND status <> 'cancelled'
           AND id <> ?
           AND (time_start < ? AND time_end > ?)
         LIMIT 1"
    );
    $conflictStmt->bind_param('isiss', $roomId, $dayOfWeek, $scheduleId, $timeEnd, $timeStart);
} else {
    $conflictStmt = $conn->prepare(
        "SELECT id FROM schedules
         WHERE room_id = ? AND day_of_week = ? AND status <> 'cancelled'
           AND (time_start < ? AND time_end > ?)
         LIMIT 1"
    );
    $conflictStmt->bind_param('isss', $roomId, $dayOfWeek, $timeEnd, $timeStart);
}
$conflictStmt->execute();
$hasConflict = $conflictStmt->get_result()->num_rows > 0;
$conflictStmt->close();

if ($hasConflict) {
    $_SESSION['schedule_flash'] = 'Schedule conflict detected for this room, day, and time.';
    $_SESSION['schedule_flash_type'] = 'error';
    header('Location: comlab-map.php?room_id=' . $roomId);
    exit;
}

if ($action === 'update' && $scheduleId > 0) {
    if ($role === 'instructor') {
        // Instructors can only update their own schedules
        $updateStmt = $conn->prepare(
            "UPDATE schedules
             SET subject = ?, section = ?, day_of_week = ?, time_start = ?, time_end = ?
             WHERE id = ? AND room_id = ? AND instructor_id = ?"
        );
        $updateStmt->bind_param('sssssiii', $subject, $section, $dayOfWeek, $timeStart, $timeEnd, $scheduleId, $roomId, $userId);
    } else {
        // Admin: update fields but preserve existing instructor_id
        $updateStmt = $conn->prepare(
            "UPDATE schedules
             SET subject = ?, section = ?, day_of_week = ?, time_start = ?, time_end = ?, instructor_id = ?
             WHERE id = ? AND room_id = ?"
        );
        $updateStmt->bind_param('sssssiii', $subject, $section, $dayOfWeek, $timeStart, $timeEnd, $instructorId, $scheduleId, $roomId);
    }
    $ok = $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();
    if ($ok && $affected >= 0) {
        $_SESSION['schedule_flash'] = $affected > 0 ? 'Schedule updated successfully.' : 'No changes were made.';
        $_SESSION['schedule_flash_type'] = 'success';
    } else {
        $_SESSION['schedule_flash'] = $role === 'instructor'
            ? 'Update failed. Instructors can only edit their own schedules.'
            : 'Unable to update schedule.';
        $_SESSION['schedule_flash_type'] = 'error';
    }
} else {
    $insertStmt = $conn->prepare(
        "INSERT INTO schedules (room_id, instructor_id, subject, section, day_of_week, time_start, time_end, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'reserved')"
    );
    $insertStmt->bind_param('iisssss', $roomId, $instructorId, $subject, $section, $dayOfWeek, $timeStart, $timeEnd);
    $ok = $insertStmt->execute();
    $insertStmt->close();
    $_SESSION['schedule_flash'] = $ok ? 'Schedule added successfully.' : 'Unable to add schedule.';
    $_SESSION['schedule_flash_type'] = $ok ? 'success' : 'error';
}

header('Location: comlab-map.php?room_id=' . $roomId);
exit;
