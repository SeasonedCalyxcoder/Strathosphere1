<?php
require_once '../includes/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function normalizeKenyanPhone($phoneRaw) {
    $phone = preg_replace('/\s+|-/', '', trim((string)$phoneRaw));

    if (preg_match('/^\+254\d{9}$/', $phone)) {
        return $phone;
    }
    if (preg_match('/^254\d{9}$/', $phone)) {
        return '+' . $phone;
    }
    if (preg_match('/^0\d{9}$/', $phone)) {
        return '+254' . substr($phone, 1);
    }
    if (preg_match('/^\d{9}$/', $phone)) {
        return '+254' . $phone;
    }

    return false;
}

function simulateMpesaStkPush($phone, $amount, $eventTitle) {
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Invalid payment amount'];
    }

    $reference = 'MPESA-' . strtoupper(substr(uniqid(), -10));

    return [
        'success' => true,
        'reference' => $reference,
        'message' => 'M-Pesa STK push sent to ' . $phone . ' for KES ' . $amount . ' (' . $eventTitle . ')'
    ];
}

switch ($action) {
    case 'list':
        $category = sanitize($conn, $_GET['category'] ?? 'all');
        $search = sanitize($conn, $_GET['search'] ?? '');
        $sort = sanitize($conn, $_GET['sort'] ?? 'date');
        $locationId = intval($_GET['location_id'] ?? 0);
        
        $sql = "SELECT e.*, l.name as location_name, l.lat, l.lng 
                FROM events e 
                LEFT JOIN locations l ON e.location_id = l.id 
                WHERE 1=1";
        $params = [];
        $types = "";
        
        if ($category !== 'all') {
            $sql .= " AND e.category = ?";
            $params[] = $category;
            $types .= "s";
        }
        if ($locationId > 0) {
            $sql .= " AND e.location_id = ?";
            $params[] = $locationId;
            $types .= "i";
        }
        if (!empty($search)) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.organizer LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }
        
        switch ($sort) {
            case 'points': $sql .= " ORDER BY e.points DESC"; break;
            case 'cost': $sql .= " ORDER BY e.cost ASC"; break;
            default: $sql .= " ORDER BY e.event_date ASC"; break;
        }
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM event_attendees WHERE event_id = ?");
            $countStmt->bind_param("i", $row['id']);
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $row['attendee_count'] = $countResult['count'];
            
            if (isset($_SESSION['user_id'])) {
                $checkStmt = $conn->prepare("SELECT id FROM event_attendees WHERE event_id = ? AND user_id = ?");
                $checkStmt->bind_param("ii", $row['id'], $_SESSION['user_id']);
                $checkStmt->execute();
                $row['is_attending'] = $checkStmt->get_result()->num_rows > 0;
            } else {
                $row['is_attending'] = false;
            }
            $events[] = $row;
        }
        jsonResponse(['success' => true, 'events' => $events]);
        break;
        
    case 'register':
        requireAuth();
        $eventId = intval($_POST['event_id']);
        $userId = $_SESSION['user_id'];
        $phone = normalizeKenyanPhone($_POST['phone_number'] ?? '');

        if (!$phone) {
            $stmt = $conn->prepare("SELECT phone_number FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $savedPhone = $stmt->get_result()->fetch_assoc()['phone_number'] ?? '';
            $phone = normalizeKenyanPhone($savedPhone);
        }

        if (!$phone) {
            jsonResponse(['success' => false, 'message' => 'Enter a valid phone number in +254 format']);
        }
        
        $stmt = $conn->prepare("SELECT id FROM event_attendees WHERE event_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            jsonResponse(['success' => false, 'message' => 'Already registered for this event']);
        }
        
        $stmt = $conn->prepare("SELECT max_attendees, points, cost, title FROM events WHERE id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM event_attendees WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($count >= $event['max_attendees']) {
            jsonResponse(['success' => false, 'message' => 'Event is full']);
        }
        
        $paymentReference = null;
        $ticketCode = 'TKT-' . strtoupper(substr(uniqid(), -9));

        $status = $event['cost'] > 0 ? 'paid' : 'free';

        if ($event['cost'] > 0) {
            $mpesaResult = simulateMpesaStkPush($phone, (int)$event['cost'], $event['title']);
            if (!$mpesaResult['success']) {
                jsonResponse(['success' => false, 'message' => $mpesaResult['message']]);
            }
            $paymentReference = $mpesaResult['reference'];
        }

        $stmt = $conn->prepare("INSERT INTO event_attendees (event_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO tickets (user_id, event_id, ticket_code, status, phone_number, payment_reference) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $userId, $eventId, $ticketCode, $status, $phone, $paymentReference);
        $stmt->execute();
        
        $points = $event['points'];
        $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->bind_param("ii", $points, $userId);
        $stmt->execute();
        $_SESSION['user_points'] += $points;
        
        $reason = "Attended: " . $event['title'];
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, reason, type) VALUES (?, ?, ?, 'earned')");
        $stmt->bind_param("iis", $userId, $points, $reason);
        $stmt->execute();
        
        $message = $event['cost'] > 0
            ? 'Payment confirmed via M-Pesa. You are registered successfully.'
            : 'Registered successfully';

        jsonResponse([
            'success' => true,
            'message' => $message,
            'points_earned' => $points,
            'ticket_code' => $ticketCode,
            'payment_reference' => $paymentReference
        ]);
        break;

    case 'deregister':
        requireAuth();
        $eventId = intval($_POST['event_id']);
        $userId = $_SESSION['user_id'];

        $stmt = $conn->prepare("SELECT id FROM event_attendees WHERE event_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'You are not registered for this event']);
        }

        $stmt = $conn->prepare("SELECT title, points FROM events WHERE id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        if (!$event) {
            jsonResponse(['success' => false, 'message' => 'Event not found']);
        }

        $stmt = $conn->prepare("DELETE FROM event_attendees WHERE event_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE tickets SET status = 'cancelled' WHERE event_id = ? AND user_id = ? AND status <> 'cancelled'");
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();

        $points = (int)$event['points'];
        $stmt = $conn->prepare("UPDATE users SET points = GREATEST(points - ?, 0) WHERE id = ?");
        $stmt->bind_param("ii", $points, $userId);
        $stmt->execute();

        $_SESSION['user_points'] = max(0, (int)$_SESSION['user_points'] - $points);

        $reason = 'Deregistered: ' . $event['title'];
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, reason, type) VALUES (?, ?, ?, 'redeemed')");
        $stmt->bind_param("iis", $userId, $points, $reason);
        $stmt->execute();

        jsonResponse(['success' => true, 'message' => 'You have deregistered from the event', 'points_removed' => $points]);
        break;
        
    case 'create':
        requireAdmin();
        $title = sanitize($conn, $_POST['title']);
        $description = sanitize($conn, $_POST['description']);
        $locationId = intval($_POST['location_id']);
        $date = sanitize($conn, $_POST['event_date']);
        $time = sanitize($conn, $_POST['event_time']);
        $category = sanitize($conn, $_POST['category']);
        $cost = intval($_POST['cost']);
        $points = intval($_POST['points']);
        $organizer = sanitize($conn, $_POST['organizer']);
        $image = sanitize($conn, $_POST['image']);
        $maxAttendees = intval($_POST['max_attendees']);
        
        $stmt = $conn->prepare("INSERT INTO events (title, description, location_id, event_date, event_time, category, cost, points, organizer, image, max_attendees) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisssiiisi", $title, $description, $locationId, $date, $time, $category, $cost, $points, $organizer, $image, $maxAttendees);
        
        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Event created', 'id' => $stmt->insert_id]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to create event']);
        }
        break;
        
    case 'my_events':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT e.*, l.name as location_name FROM events e 
                               JOIN event_attendees ea ON e.id = ea.event_id 
                               LEFT JOIN locations l ON e.location_id = l.id 
                               WHERE ea.user_id = ? ORDER BY e.event_date ASC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        jsonResponse(['success' => true, 'events' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}