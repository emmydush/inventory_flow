<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getSettings($conn);
        break;
    case 'POST':
    case 'PUT':
        updateSettings($conn);
        break;
    default:
        echo json_encode(['error' => 'Method not allowed']);
}

function getSettings($conn) {
    try {
        $stmt = $conn->query("SELECT * FROM settings ORDER BY setting_key");
        $settings = $stmt->fetchAll();
        
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['setting_type']
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $formatted]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateSettings($conn) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $conn->beginTransaction();
        
        foreach ($data as $key => $value) {
            $stmt = $conn->prepare("UPDATE settings SET setting_value = :value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :key");
            $stmt->execute([':value' => $value, ':key' => $key]);
            
            if ($stmt->rowCount() === 0) {
                $insertStmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)");
                $insertStmt->execute([':key' => $key, ':value' => $value]);
            }
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    } catch(PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
