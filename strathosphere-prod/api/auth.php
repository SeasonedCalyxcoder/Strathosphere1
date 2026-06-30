<?php
require_once '../includes/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function isValidStrathmoreEmail($email) {
    return preg_match('/^[a-zA-Z]+\.[a-zA-Z]+@strathmore\.edu$/i', $email) === 1;
}

function normalizeLoginEmail($email) {
    $normalized = strtolower(trim((string)$email));

    // Backward compatibility for the original seeded admin credential.
    if ($normalized === 'admin@strathmore.edu') {
        return 'system.administrator@strathmore.edu';
    }

    return $normalized;
}

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

switch ($action) {
    case 'register':
        $name = sanitize($conn, $_POST['name']);
        $email = strtolower(trim(sanitize($conn, $_POST['email'])));
        $password = $_POST['password'];
        $phone = normalizeKenyanPhone($_POST['phone_number'] ?? '');
        $role = 'student';
        
        if (empty($name) || empty($email) || empty($password) || !$phone) {
            jsonResponse(['success' => false, 'message' => 'All fields are required']);
        }

        if (!isValidStrathmoreEmail($email)) {
            jsonResponse(['success' => false, 'message' => 'Use format firstname.surname@strathmore.edu']);
        }
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            jsonResponse(['success' => false, 'message' => 'Email already registered']);
        }
        
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $avatar = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=002855&color=fff&size=128";
        
        $status = 'pending';
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone_number, role, status, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $email, $hash, $phone, $role, $status, $avatar);
        
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;

            $adminTitle = 'New account request';
            $adminMessage = $name . ' (' . $email . ') registered and is waiting for approval.';
            $adminType = 'account_request';
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) SELECT id, ?, ?, ? FROM users WHERE role = 'admin' AND status = 'approved'");
            $stmt->bind_param("sss", $adminTitle, $adminMessage, $adminType);
            $stmt->execute();

            jsonResponse([
                'success' => true,
                'message' => 'Registration submitted. Your account is pending administrator approval.'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Registration failed']);
        }
        break;
        
    case 'login':
        $email = normalizeLoginEmail(sanitize($conn, $_POST['email'] ?? ''));
        $password = $_POST['password'];

        if (!isValidStrathmoreEmail($email)) {
            jsonResponse(['success' => false, 'message' => 'Use format firstname.surname@strathmore.edu']);
        }
        
        $stmt = $conn->prepare("SELECT id, name, email, password, phone_number, role, status, points, avatar, login_streak, last_login_date, last_streak_reward_date FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            jsonResponse(['success' => false, 'message' => 'User not found']);
        }
        
        $user = $result->fetch_assoc();
        if (!password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid password']);
        }

        if ($user['status'] === 'pending') {
            jsonResponse(['success' => false, 'message' => 'Your account is pending administrator approval']);
        }

        if ($user['status'] === 'rejected') {
            jsonResponse(['success' => false, 'message' => 'Your registration request was rejected. Please contact an administrator.']);
        }

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $currentStreak = (int)($user['login_streak'] ?? 0);
        $lastLoginDate = $user['last_login_date'] ?? null;
        $lastRewardDate = $user['last_streak_reward_date'] ?? null;

        $updatedStreak = $currentStreak;
        if ($lastLoginDate === $today) {
            $updatedStreak = max(1, $currentStreak);
        } elseif ($lastLoginDate === $yesterday) {
            $updatedStreak = $currentStreak + 1;
        } else {
            $updatedStreak = 1;
        }

        $pointsAwarded = 0;
        if ($updatedStreak > 3 && $lastRewardDate !== $today) {
            $pointsAwarded = 10;
            $stmt = $conn->prepare("UPDATE users SET login_streak = ?, last_login_date = ?, last_streak_reward_date = ?, points = points + ? WHERE id = ?");
            $stmt->bind_param("issii", $updatedStreak, $today, $today, $pointsAwarded, $user['id']);
            $stmt->execute();

            $reason = 'Daily login streak bonus';
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, reason, type) VALUES (?, ?, ?, 'earned')");
            $stmt->bind_param("iis", $user['id'], $pointsAwarded, $reason);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE users SET login_streak = ?, last_login_date = ? WHERE id = ?");
            $stmt->bind_param("isi", $updatedStreak, $today, $user['id']);
            $stmt->execute();
        }

        $updatedPoints = (int)$user['points'] + $pointsAwarded;
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_phone'] = $user['phone_number'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_points'] = $updatedPoints;
        $_SESSION['user_streak'] = $updatedStreak;
        $_SESSION['user_avatar'] = $user['avatar'];

        $stmt = $conn->prepare("SELECT id, title, message, type, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $user['points'] = $updatedPoints;
        $user['login_streak'] = $updatedStreak;
        unset($user['password']);

        $loginMessage = 'Login successful';
        if ($pointsAwarded > 0) {
            $loginMessage .= '. Daily streak bonus: +' . $pointsAwarded . ' points';
        }

        jsonResponse(['success' => true, 'message' => $loginMessage, 'user' => $user, 'notifications' => $notifications]);
        break;

    case 'notifications':
        requireAuth();
        $stmt = $conn->prepare("SELECT id, title, message, type, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        jsonResponse(['success' => true, 'notifications' => $notifications]);
        break;

    case 'mark_notifications_read':
        requireAuth();
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        jsonResponse(['success' => true]);
        break;
        
    case 'logout':
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out']);
        break;
        
    case 'me':
        if (isset($_SESSION['user_id'])) {
            jsonResponse(['success' => true, 'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'phone_number' => $_SESSION['user_phone'] ?? null,
                'role' => $_SESSION['user_role'],
                'points' => $_SESSION['user_points'],
                'login_streak' => $_SESSION['user_streak'] ?? 0,
                'avatar' => $_SESSION['user_avatar']
            ]]);
        }
        jsonResponse(['success' => false, 'message' => 'Not authenticated']);
        break;
        
    case 'update_profile':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $phoneInput = $_POST['phone_number'] ?? '';
        $phoneInput = trim((string)$phoneInput);
        $hasPhoneInput = $phoneInput !== '';
        $hasAvatarUpload = isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE;

        if (!$hasPhoneInput && !$hasAvatarUpload) {
            jsonResponse(['success' => false, 'message' => 'No changes detected. Update your phone number or choose a new profile picture.']);
        }

        $stmt = $conn->prepare("SELECT avatar, phone_number FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $currentData = $stmt->get_result()->fetch_assoc();
        $currentAvatar = $currentData['avatar'] ?? '';
        $currentPhone = $currentData['phone_number'] ?? '';

        $updateFields = [];
        $updateTypes = '';
        $updateValues = [];
        $newAvatarPath = null;

        if ($hasPhoneInput) {
            $normalizedPhone = normalizeKenyanPhone($phoneInput);
            if (!$normalizedPhone) {
                jsonResponse(['success' => false, 'message' => 'Enter a valid phone number in +254 format']);
            }

            if ($normalizedPhone !== $currentPhone) {
                $updateFields[] = 'phone_number = ?';
                $updateTypes .= 's';
                $updateValues[] = $normalizedPhone;
            }
        }

        if ($hasAvatarUpload) {
            if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'message' => 'Avatar upload failed']);
            }

            if ($_FILES['avatar']['size'] > 3 * 1024 * 1024) {
                jsonResponse(['success' => false, 'message' => 'Profile picture must be 3MB or smaller']);
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $originalName = $_FILES['avatar']['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                jsonResponse(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed']);
            }

            $tmpPath = $_FILES['avatar']['tmp_name'];
            if (@getimagesize($tmpPath) === false) {
                jsonResponse(['success' => false, 'message' => 'Invalid image file']);
            }

            $uploadDir = __DIR__ . '/../uploads/avatars';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                jsonResponse(['success' => false, 'message' => 'Unable to create avatar directory']);
            }

            $fileName = 'avatar_' . $userId . '_' . time() . '.' . $extension;
            $newAvatarPath = 'uploads/avatars/' . $fileName;
            $destination = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($tmpPath, $destination)) {
                jsonResponse(['success' => false, 'message' => 'Unable to save profile picture']);
            }

            $updateFields[] = 'avatar = ?';
            $updateTypes .= 's';
            $updateValues[] = $newAvatarPath;
        }

        if (empty($updateFields)) {
            jsonResponse(['success' => false, 'message' => 'No changes detected.']);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $updateTypes .= 'i';
        $updateValues[] = $userId;
        $stmt->bind_param($updateTypes, ...$updateValues);

        if ($stmt->execute()) {
            if ($newAvatarPath !== null) {
                $_SESSION['user_avatar'] = $newAvatarPath;

                if (!empty($currentAvatar) && strpos($currentAvatar, 'uploads/avatars/') === 0) {
                    $oldFile = __DIR__ . '/../' . $currentAvatar;
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
            }

            if ($hasPhoneInput && isset($normalizedPhone) && $normalizedPhone !== $currentPhone) {
                $_SESSION['user_phone'] = $normalizedPhone;
            }

            jsonResponse([
                'success' => true,
                'message' => 'Profile updated successfully',
                'avatar' => $_SESSION['user_avatar'] ?? $currentAvatar,
                'phone_number' => $_SESSION['user_phone'] ?? $currentPhone
            ]);
        }

        jsonResponse(['success' => false, 'message' => 'Update failed']);
        break;

    case 'change_password':
        requireAuth();
        $userId = $_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            jsonResponse(['success' => false, 'message' => 'Current and new password are required']);
        }

        if (strlen($newPassword) < 6) {
            jsonResponse(['success' => false, 'message' => 'New password must be at least 6 characters']);
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $storedHash = $stmt->get_result()->fetch_assoc()['password'] ?? '';

        if (!password_verify($currentPassword, $storedHash)) {
            jsonResponse(['success' => false, 'message' => 'Current password is incorrect']);
        }

        if (password_verify($newPassword, $storedHash)) {
            jsonResponse(['success' => false, 'message' => 'New password must be different from current password']);
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newHash, $userId);

        if ($stmt->execute()) {
            jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
        }
        jsonResponse(['success' => false, 'message' => 'Failed to change password']);
        break;

    case 'delete_account':
        requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';

        if (empty($currentPassword)) {
            jsonResponse(['success' => false, 'message' => 'Current password is required']);
        }

        $stmt = $conn->prepare("SELECT role, password, avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            jsonResponse(['success' => false, 'message' => 'Current password is incorrect']);
        }

        if ($user['role'] === 'admin') {
            $result = $conn->query("SELECT COUNT(*) AS admin_count FROM users WHERE role = 'admin' AND status = 'approved'");
            $adminCount = (int)$result->fetch_assoc()['admin_count'];
            if ($adminCount <= 1) {
                jsonResponse(['success' => false, 'message' => 'At least one administrator must remain']);
            }
        }

        $avatarPath = $user['avatar'] ?? '';
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            if (!empty($avatarPath) && strpos($avatarPath, 'uploads/avatars/') === 0) {
                $avatarFile = __DIR__ . '/../' . $avatarPath;
                if (is_file($avatarFile)) {
                    @unlink($avatarFile);
                }
            }

            session_destroy();
            jsonResponse(['success' => true, 'message' => 'Account deleted successfully']);
        }

        jsonResponse(['success' => false, 'message' => 'Failed to delete account']);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action']);
}