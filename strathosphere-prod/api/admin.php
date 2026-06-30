<?php
require_once '../includes/config.php';
requireAdmin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        $stats = [];
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $stats['total_users'] = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM events");
        $stats['total_events'] = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM tickets");
        $stats['total_tickets'] = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type = 'earned'");
        $stats['total_points'] = $result->fetch_assoc()['total'] ?? 0;
        
        jsonResponse(['success' => true, 'stats' => $stats]);
        break;
        
    case 'users':
        $search = sanitize($conn, $_GET['search'] ?? '');
        $sql = "SELECT id, name, email, role, status, points, avatar, created_at FROM users WHERE 1=1";
        if (!empty($search)) {
            $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
        }
        $sql .= " ORDER BY (status = 'pending') DESC, created_at DESC";
        $result = $conn->query($sql);
        jsonResponse(['success' => true, 'users' => $result->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'review_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        $decision = sanitize($conn, $_POST['decision'] ?? '');

        if ($userId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            jsonResponse(['success' => false, 'message' => 'Invalid review request']);
        }

        $stmt = $conn->prepare("SELECT id, name, status FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();

        if (!$target) {
            jsonResponse(['success' => false, 'message' => 'User not found']);
        }

        if ($target['status'] !== 'pending') {
            jsonResponse(['success' => false, 'message' => 'Only pending requests can be reviewed']);
        }

        $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $userId);
        if (!$stmt->execute()) {
            jsonResponse(['success' => false, 'message' => 'Unable to update request status']);
        }

        $title = $decision === 'approve' ? 'Account approved' : 'Account rejected';
        $message = $decision === 'approve'
            ? 'Your account has been approved by an administrator. You can now log in.'
            : 'Your account request has been rejected. Please contact administration for help.';
        $type = $decision === 'approve' ? 'account_approved' : 'account_rejected';

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $title, $message, $type);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => 'Request updated successfully']);
        break;

    case 'update_role':
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = sanitize($conn, $_POST['role'] ?? '');

        if ($userId <= 0 || !in_array($role, ['student', 'admin'], true)) {
            jsonResponse(['success' => false, 'message' => 'Invalid user or role']);
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $exists = $stmt->get_result();
        if ($exists->num_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'User not found']);
        }

        if ($userId === (int)$_SESSION['user_id'] && $role !== 'admin') {
            jsonResponse(['success' => false, 'message' => 'You cannot remove your own admin role']);
        }

        if ($role === 'student') {
            $stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
            $stmt->execute();
            $adminCount = (int)$stmt->get_result()->fetch_assoc()['admin_count'];

            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $currentRole = $stmt->get_result()->fetch_assoc()['role'] ?? 'student';

            if ($currentRole === 'admin' && $adminCount <= 1) {
                jsonResponse(['success' => false, 'message' => 'At least one administrator must remain']);
            }
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $userId);
        if ($stmt->execute()) {
            $title = 'Account type updated';
            $message = 'Your account type is now set to ' . $role . '.';
            $type = 'account_type_updated';
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $userId, $title, $message, $type);
            $stmt->execute();

            jsonResponse(['success' => true, 'message' => 'Account type updated']);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to update account type']);
        break;

    case 'delete_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid user']);
        }

        if ($userId === (int)$_SESSION['user_id']) {
            jsonResponse(['success' => false, 'message' => 'You cannot delete your own account']);
        }

        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'User not found']);
        }

        $targetRole = $result->fetch_assoc()['role'];
        if ($targetRole === 'admin') {
            $countResult = $conn->query("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
            $adminCount = (int)$countResult->fetch_assoc()['admin_count'];
            if ($adminCount <= 1) {
                jsonResponse(['success' => false, 'message' => 'At least one administrator must remain']);
            }
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'User account deleted']);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to delete user']);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}