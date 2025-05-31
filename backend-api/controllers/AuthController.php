<?php
require_once __DIR__ . '/../models/AuthModel.php';

class AuthController {
    private $authModel;

    public function __construct() {
        $this->authModel = new AuthModel();
        header('Content-Type: application/json');
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type");
    }

    public function register($data) {
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $full_name = $data['full_name'] ?? '';

        if (!$username || !$email || !$password || !$full_name) {
            http_response_code(400);
            echo json_encode(['message' => 'Vui lòng nhập đầy đủ thông tin.']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['message' => 'Email không hợp lệ.']);
            return;
        }

        if ($this->authModel->findByUsername($username)) {
            http_response_code(409);
            echo json_encode(['message' => 'Tên đăng nhập đã tồn tại.']);
            return;
        }

        if ($this->authModel->findByEmail($email)) {
            http_response_code(409);
            echo json_encode(['message' => 'Email đã tồn tại.']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $this->authModel->create([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'full_name' => $full_name
        ]);

        http_response_code(201);
        echo json_encode(['message' => 'Đăng ký thành công!']);
    }

    public function login($data) {
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
    
        // Tạo token tạm thời
        $token = base64_encode($user['username'] . ':' . time());
        
        // Lưu token vào database
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
}
?>