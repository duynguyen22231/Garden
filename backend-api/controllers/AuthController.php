<?php
require_once __DIR__ . '/../models/AuthModel.php';

class AuthController {
    private $authModel;

    public function __construct() {
        error_log("Initializing AuthController");
        $this->authModel = new AuthModel();
        header('Content-Type: application/json');
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");
    }

    public function register($data) {
        error_log("Register action: " . json_encode($data));
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $full_name = $data['full_name'] ?? '';

        if (!$username || !$email || !$password || !$full_name) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email không hợp lệ.']);
            return;
        }

        if ($this->authModel->findByUsername($username)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Tên đăng nhập đã tồn tại.']);
            return;
        }

        if ($this->authModel->findByEmail($email)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email đã tồn tại.']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $result = $this->authModel->create([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'full_name' => $full_name
        ]);

        if ($result) {
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Đăng ký thành công!']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo tài khoản.']);
        }
    }

    public function login($data) {
        error_log("Login action: username=" . ($data['username'] ?? 'null'));
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
    
        if (!$username || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên đăng nhập và mật khẩu.']);
            return;
        }
    
        $user = $this->authModel->findByUsername($username);
    
        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sai tên đăng nhập hoặc mật khẩu.']);
            return;
        }
    
        $token = base64_encode($user['username'] . ':' . time());
        $this->authModel->saveToken($user['id'], $token);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ]
            ],
            'message' => 'Đăng nhập thành công'
        ]);
    }

    public function check($data) {
        error_log("Check action: token=" . substr($data['token'] ?? 'null', 0, 20) . "...");
        $token = $data['token'] ?? '';
        if (!$token) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Token không hợp lệ.']);
            return;
        }

        $tokenData = $this->authModel->findByToken($token);
        if ($tokenData) {
            $user = $this->authModel->findById($tokenData['user_id']);
            if ($user) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'user' => $user
                    ],
                    'message' => 'Token hợp lệ'
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng.']);
            }
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn.']);
        }
    }
}
?>