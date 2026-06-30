<?php
require_once '../includes/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $search = sanitize($conn, $_GET['search'] ?? '');
        $category = sanitize($conn, $_GET['category'] ?? 'all');
        
        $sql = "SELECT * FROM locations WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($category !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        jsonResponse(['success' => true, 'locations' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
        
    case 'get':
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM locations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            jsonResponse(['success' => true, 'location' => $result->fetch_assoc()]);
        }
        jsonResponse(['success' => false, 'message' => 'Location not found']);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}