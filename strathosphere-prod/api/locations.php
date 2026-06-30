<?php
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

    case 'update':
        requireAdmin();
        $id = intval($_POST['id'] ?? 0);
        $name = sanitize($conn, $_POST['name'] ?? '');
        $category = sanitize($conn, $_POST['category'] ?? '');
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
        $description = sanitize($conn, $_POST['description'] ?? '');
        $icon = sanitize($conn, $_POST['icon'] ?? 'building');

        if ($id <= 0 || empty($name) || empty($category) || $lat === null || $lng === null || empty($description)) {
            jsonResponse(['success' => false, 'message' => 'All location fields are required']);
        }

        $allowedCategories = ['academic', 'dining', 'sports', 'events', 'religious', 'services'];
        if (!in_array($category, $allowedCategories, true)) {
            jsonResponse(['success' => false, 'message' => 'Invalid location category']);
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            jsonResponse(['success' => false, 'message' => 'Invalid map coordinates']);
        }

        $stmt = $conn->prepare("UPDATE locations SET name = ?, category = ?, lat = ?, lng = ?, description = ?, icon = ? WHERE id = ?");
        $stmt->bind_param("ssddssi", $name, $category, $lat, $lng, $description, $icon, $id);

        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Location updated successfully']);
        }

        jsonResponse(['success' => false, 'message' => 'Failed to update location']);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}