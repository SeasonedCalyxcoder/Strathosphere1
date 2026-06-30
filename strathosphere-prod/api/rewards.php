<?php
require_once '../includes/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'leaderboard':
        $stmt = $conn->prepare("SELECT id, name, email, points, avatar FROM users WHERE role = 'student' ORDER BY points DESC LIMIT 10");
        $stmt->execute();
        jsonResponse(['success' => true, 'leaderboard' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
        
    case 'transactions':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        jsonResponse(['success' => true, 'transactions' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
        
    case 'redeem':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $cost = intval($_POST['cost']);
        $reward = sanitize($conn, $_POST['reward']);
        
        $stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $currentPoints = $stmt->get_result()->fetch_assoc()['points'];
        
        if ($currentPoints < $cost) {
            jsonResponse(['success' => false, 'message' => 'Not enough points']);
        }
        
        $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->bind_param("ii", $cost, $userId);
        $stmt->execute();
        $_SESSION['user_points'] -= $cost;
        
        $reason = "Redeemed: " . $reward;
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, reason, type) VALUES (?, ?, ?, 'redeemed')");
        $stmt->bind_param("iis", $userId, $cost, $reason);
        $stmt->execute();
        
        jsonResponse(['success' => true, 'message' => 'Reward redeemed successfully']);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}