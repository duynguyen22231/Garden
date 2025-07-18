<?php
require_once __DIR__ . '/../models/StatsModel.php';

class StatsController {
    private $model;

    public function __construct($db) {
        $this->model = new StatsModel($db);
    }

    public function getAccessibleGardens($userId, $isAdmin) {
        try {
            $data = $this->model->getAccessibleGardens($userId, $isAdmin);
            return [
                'success' => true,
                'data' => $data,
                'message' => 'Danh sách vườn đã được tải'
            ];
        } catch (Exception $e) {
            error_log("Error in getAccessibleGardens for user $userId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi khi tải danh sách vườn: ' . $e->getMessage()
            ];
        }
    }

    public function getGardenStats($gardenIds, $userId, $isAdmin, $timeRange = '7d') {
        try {
            // Kiểm tra quyền truy cập
            $accessibleGardens = $this->model->getAccessibleGardens($userId, $isAdmin);
            $accessibleGardenIds = array_column($accessibleGardens, 'garden_id');
            
            $allowedGardenIds = array_filter($gardenIds, function($id) use ($accessibleGardenIds) {
                return in_array($id, $accessibleGardenIds);
            });
            
            if (empty($allowedGardenIds)) {
                return [
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập bất kỳ vườn nào trong danh sách'
                ];
            }

            // Tính toán khoảng thời gian
            $dateRange = $this->calculateDateRange($timeRange);
            
            $stats = $this->model->getGardenStats($allowedGardenIds, $userId, $isAdmin, $dateRange['start'], $dateRange['end']);
            
            return [
                'success' => true,
                'data' => $stats,
                'message' => 'Dữ liệu thống kê đã được tải'
            ];
        } catch (Exception $e) {
            error_log("Error in getGardenStats for user $userId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi khi tải thống kê: ' . $e->getMessage()
            ];
        }
    }

    private function calculateDateRange($timeRange) {
        $now = new DateTime();
        $start = clone $now;
        
        switch ($timeRange) {
            case '24h':
                $start->modify('-24 hours');
                break;
            case '7d':
                $start->modify('-7 days');
                break;
            case '30d':
                $start->modify('-30 days');
                break;
            case '90d':
                $start->modify('-90 days');
                break;
            default:
                $start->modify('-7 days');
        }
        
        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $now->format('Y-m-d H:i:s')
        ];
    }
}
?>