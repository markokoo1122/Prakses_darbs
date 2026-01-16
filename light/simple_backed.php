<?php
// simple_backend.php - Save this as a separate file
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_GET['action']) && $_GET['action'] === 'get_status') {
    
    // Database configuration
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "Arduino";
    
    try {
        // Create connection
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        
        // Get current status (latest entry)
        $stmt = $conn->prepare("SELECT id, data, time FROM Data ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $response = array();
        $response['debug'] = 'Backend working!';
        $response['last_id_received'] = $lastId;
        
        if ($currentData) {
            $response['current_status'] = array(
                'id' => $currentData['id'],
                'raw_data' => $currentData['data'],
                'timestamp' => $currentData['time'],
                'status' => determineLightStatus($currentData['data'])
            );
        } else {
            $response['current_status'] = null;
            $response['message'] = 'No data found in database';
        }
        
        // Get new entries since last check
        $stmt = $conn->prepare("SELECT id, data, time FROM Data WHERE id > ? ORDER BY id DESC LIMIT 5");
        $stmt->execute([$lastId]);
        $newEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['new_entries'] = array();
        $response['recent_entries'] = array();
        
        if ($newEntries) {
            foreach ($newEntries as $entry) {
                $response['new_entries'][] = array(
                    'id' => $entry['id'],
                    'raw_data' => $entry['data'],
                    'timestamp' => $entry['time'],
                    'status' => determineLightStatus($entry['data'])
                );
            }
        }
        
        // Get recent entries for display (last 10)
        $stmt = $conn->prepare("SELECT id, data, time FROM Data ORDER BY id DESC LIMIT 10");
        $stmt->execute();
        $recentEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($recentEntries) {
            foreach ($recentEntries as $entry) {
                $response['recent_entries'][] = array(
                    'id' => $entry['id'],
                    'raw_data' => $entry['data'],
                    'timestamp' => $entry['time'],
                    'status' => determineLightStatus($entry['data'])
                );
            }
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
    } catch(PDOException $e) {
        echo json_encode(array(
            'error' => 'Database connection failed: ' . $e->getMessage(),
            'debug' => 'Backend reached but database failed'
        ));
    }
    
} else {
    echo json_encode(array(
        'error' => 'Invalid action or no action specified',
        'debug' => 'Backend reached but wrong parameters',
        'received_get' => $_GET
    ));
}

function determineLightStatus($data) {
    $dataStr = strtolower(trim($data));
    
    // Check for common light status indicators
    $onKeywords = ['on', '1', 'true', 'high', 'light on', 'yes'];
    $offKeywords = ['off', '0', 'false', 'low', 'light off', 'no'];
    
    foreach ($onKeywords as $keyword) {
        if (strpos($dataStr, $keyword) !== false) {
            return 'ON';
        }
    }
    
    foreach ($offKeywords as $keyword) {
        if (strpos($dataStr, $keyword) !== false) {
            return 'OFF';
        }
    }
    
    // Try to interpret as numeric value
    if (is_numeric($dataStr)) {
        return floatval($dataStr) > 0 ? 'ON' : 'OFF';
    }
    
    return 'UNKNOWN';
}
?>