<?php
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

try {
    if (!file_exists('../config/database.php')) {
        throw new Exception('File database.php không tồn tại');
    }

    require_once '../config/database.php';

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dữ liệu JSON không hợp lệ: ' . json_last_error_msg());
    }

    if (!isset($input['action'])) {
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        exit;
    }

    $action = $input['action'];

    switch ($action) {
        case 'login':
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';

            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Thiếu tên đăng nhập hoặc mật khẩu']);
                exit;
            }

            $stmt = $conn->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Tên đăng nhập không tồn tại']);
                exit;
            }

            if (password_verify($password, $user['password'])) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = $conn->prepare('INSERT INTO tokens (user_id, token, created_at, expiry) VALUES (?, ?, NOW(), ?)');
                $stmt->execute([$user['id'], $token, $expiry]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Đăng nhập thành công',
                    'data' => [
                        'token' => $token,
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'full_name' => $user['full_name'],
                            'administrator_rights' => $user['administrator_rights']
                        ]
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng']);
            }
            break;

        case 'register':
            $full_name = $input['full_name'] ?? '';
            $username = $input['username'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';

            if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
                exit;
            }

            $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Tên đăng nhập hoặc email đã tồn tại']);
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $administrator_rights = ($username === 'admin' && $password === '123') ? 1 : 0;
            $stmt = $conn->prepare('INSERT INTO users (full_name, username, email, password, administrator_rights) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$full_name, $username, $email, $hashed_password, $administrator_rights]);
            $user_id = $conn->lastInsertId();

            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $conn->prepare('INSERT INTO tokens (user_id, token, created_at, expiry) VALUES (?, ?, NOW(), ?)');
            $stmt->execute([$user_id, $token, $expiry]);

            echo json_encode([
                'success' => true,
                'message' => 'Đăng ký thành công',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => (int)$user_id,
                        'username' => $username,
                        'email' => $email,
                        'full_name' => $full_name,
                        'administrator_rights' => $administrator_rights
                    ]
                ]
            ]);
            break;

        case 'check':
            $token = $input['token'] ?? '';
            if (empty($token)) {
                echo json_encode(['success' => false, 'message' => 'Thiếu token']);
                exit;
            }

            $stmt = $conn->prepare('SELECT u.* FROM users u JOIN tokens t ON u.id = t.user_id WHERE t.token = ? AND t.expiry > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Token hợp lệ',
                    'data' => [
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'full_name' => $user['full_name'],
                            'administrator_rights' => $user['administrator_rights']
                        ]
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
            exit;
    }
} catch (Exception $e) {
    error_log('Lỗi trong auth.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}

ob_end_flush();
?>