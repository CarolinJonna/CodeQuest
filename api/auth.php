<?php
// ============================================================
//  CodeQuest — Auth API
//  File: api/auth.php
//  Handles: signup, login, logout, me
// ============================================================

require_once __DIR__ . '/../config.php';
startSession();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Parse JSON body ──────────────────────────────────────────
$body = [];
$raw  = file_get_contents('php://input');
if ($raw) {
    $body = json_decode($raw, true) ?? [];
}
$data = array_merge($_POST, $body);

switch ($action) {

    // ─────────────────────────────────────────────────────────
    // SIGN UP
    // ─────────────────────────────────────────────────────────
    case 'signup': {
        $full_name = trim($data['full_name'] ?? '');
        $email     = strtolower(trim($data['email'] ?? ''));
        $password  = $data['password'] ?? '';

        if (!$full_name || !$email || !$password) {
            jsonResponse(['success' => false, 'message' => 'All fields are required.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Invalid email address.']);
        }
        if (strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        }

        $db = getDB();

        // Check duplicate
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Email already registered.']);
        }

        $hash        = password_hash($password, PASSWORD_BCRYPT);
        $username    = 'user_' . substr(md5($email), 0, 8);
        $displayName = $full_name;

        $stmt = $db->prepare('
            INSERT INTO users (full_name, email, password, username, display_name, last_login)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$full_name, $email, $hash, $username, $displayName]);
        $userId = (int)$db->lastInsertId();

        // Log activity
        $db->prepare('INSERT INTO activity_log (user_id, action_type, description) VALUES (?, "login", "Account created")')
           ->execute([$userId]);

        // Set session
        $_SESSION['user_id'] = $userId;

        jsonResponse([
            'success'      => true,
            'message'      => 'Account created!',
            'user'         => [
                'id'           => $userId,
                'displayName'  => $displayName,
                'username'     => $username,
                'email'        => $email,
                'xp'           => 0,
                'streak'       => 0,
                'gems'         => 0,
                'lessons_done' => 0,
                'progress'     => 0,
                'selected_lang'=> 'Python',
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────────────
    case 'login': {
        $email    = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(['success' => false, 'message' => 'Email and password are required.']);
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid email or password.']);
        }

        // Update last login & streak logic
        $today     = date('Y-m-d');
        $lastLogin = $user['last_login'] ? date('Y-m-d', strtotime($user['last_login'])) : null;
        $streak    = (int)$user['streak'];

        if ($lastLogin !== $today) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($lastLogin === $yesterday) {
                $streak++; // consecutive day
            } elseif ($lastLogin !== null) {
                $streak = 1; // reset
            } else {
                $streak = 1;
            }
            $db->prepare('UPDATE users SET last_login = NOW(), streak = ? WHERE id = ?')
               ->execute([$streak, $user['id']]);
            $user['streak'] = $streak;
        }

        $_SESSION['user_id'] = (int)$user['id'];

        $db->prepare('INSERT INTO activity_log (user_id, action_type, description) VALUES (?, "login", "User logged in")')
           ->execute([$user['id']]);

        jsonResponse([
            'success' => true,
            'message' => 'Login successful!',
            'user'    => [
                'id'           => (int)$user['id'],
                'displayName'  => $user['display_name'] ?: $user['full_name'],
                'username'     => $user['username'],
                'email'        => $user['email'],
                'xp'           => (int)$user['xp'],
                'streak'       => (int)$user['streak'],
                'gems'         => (int)$user['gems'],
                'lessons_done' => (int)$user['lessons_done'],
                'progress'     => (int)$user['progress'],
                'selected_lang'=> $user['selected_lang'] ?: 'Python',
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────────────────
    case 'logout': {
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out.']);
    }

    // ─────────────────────────────────────────────────────────
    // ME — fetch current session user
    // ─────────────────────────────────────────────────────────
    case 'me': {
        $uid = getSessionUserId();
        if (!$uid) {
            jsonResponse(['success' => false, 'message' => 'Not logged in.'], 401);
        }

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            session_destroy();
            jsonResponse(['success' => false, 'message' => 'User not found.'], 404);
        }

        jsonResponse([
            'success' => true,
            'user'    => [
                'id'           => (int)$user['id'],
                'displayName'  => $user['display_name'] ?: $user['full_name'],
                'username'     => $user['username'],
                'email'        => $user['email'],
                'xp'           => (int)$user['xp'],
                'streak'       => (int)$user['streak'],
                'gems'         => (int)$user['gems'],
                'lessons_done' => (int)$user['lessons_done'],
                'progress'     => (int)$user['progress'],
                'selected_lang'=> $user['selected_lang'] ?: 'Python',
            ]
        ]);
    }

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
}
