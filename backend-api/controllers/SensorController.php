<?php
require_once '../models/SensorModel.php';
require_once '../config/database.php';

class SensorController {
    private $model;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->model = new SensorModel($this->db);
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function handleError(Exception $e, $statusCode = 400, $message = 'An error occurred') {
        error_log($message . ': ' . $e->getMessage());
        $this->sendResponse([
            'success' => false,
            'message' => $message . ': ' . $e->getMessage()
        ], $statusCode);
    }

    public function getReadings($input) {
        try {
            $garden_number = $input['garden_number'] ?? 0;
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $readings = $this->model->getSensorReadings($garden_number);
            $this->sendResponse([
                'success' => true,
                'data' => $readings ?: [
                    'soil_moisture' => '--',
                    'temperature' => '--',
                    'humidity' => '--',
                    'water_level_cm' => '--',
                    'is_raining' => 0
                ]
            ]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving sensor data');
        }
    }

    public function saveSensorData($input) {
        try {
            $requiredFields = ['garden_number', 'soil_moisture', 'temperature', 'humidity', 'water_level_cm', 'is_raining'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            $this->model->saveSensorData($input);
            $this->sendResponse(['success' => true, 'message' => 'Data saved successfully']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error saving sensor data');
        }
    }

    public function updateRelay($input) {
        try {
            $requiredFields = ['garden_number', 'device_name', 'status'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            $garden_number = (int)$input['garden_number'];
            $device_name = trim($input['device_name']);
            $status = (int)$input['status'];

            if (!in_array($garden_number, [1, 2])) {
                throw new Exception("Invalid garden number");
            }
            if (!in_array($status, [0, 1])) {
                throw new Exception("Invalid device status");
            }
            $validDevices = ['maybom', 'vantren', 'vanduoi', 'den1', 'quat1', 'den2', 'quat2'];
            if (!in_array($device_name, $validDevices)) {
                throw new Exception("Invalid device name");
            }

            $this->model->updateRelay($garden_number, $device_name, $status);
            $this->sendResponse(['success' => true, 'message' => 'Relay updated successfully']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error updating relay');
        }
    }

    public function getControlCommand($input) {
        try {
            $garden_number = isset($input['garden_number']) ? (int)$input['garden_number'] : 0;
            if (!in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden number');
            }
            $data = $this->model->getControlCommands($garden_number);
            $this->sendResponse(['success' => true, 'data' => $data ?: []]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving control commands');
        }
    }

    public function getGardenNumber($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            if ($garden_id <= 0) {
                throw new Exception('Invalid garden ID');
            }
            $result = $this->model->getGardenNumber($garden_id);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            $this->sendResponse(['success' => true, 'garden_number' => $result['garden_number']]);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error retrieving garden number');
        }
    }

    public function saveGardenAssignment($input) {
        try {
            $garden_id = $input['garden_id'] ?? 0;
            $garden_number = $input['garden_number'] ?? 0;
            if ($garden_id <= 0 || !in_array($garden_number, [1, 2])) {
                throw new Exception('Invalid garden ID or garden number');
            }
            $this->model->saveGardenAssignment($garden_id, $garden_number);
            $this->sendResponse(['success' => true, 'message' => 'Garden assignment saved successfully']);
        } catch (Exception $e) {
            $this->handleError($e, 400, 'Error saving garden assignment');
        }
    }
}