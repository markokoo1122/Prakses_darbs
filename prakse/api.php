<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Helper to check login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

if ($method === 'GET') {
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;

    if ($id > 0) {
        // Fetch specific design
        // Allow if public OR if owned by user
        $sql = "SELECT * FROM designs WHERE id = $id AND (is_public = 1 OR user_id = $userId)";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['grid_data'] = json_decode($row['grid_data']);
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Design not found']);
        }
        exit;
    }

    if ($type === 'my') {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        $sql = "SELECT * FROM designs WHERE user_id = $userId ORDER BY created_at DESC";
    } elseif ($type === 'favorites') {
        if (!isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        $sql = "SELECT d.* FROM designs d 
                JOIN favorites f ON d.id = f.design_id 
                WHERE f.user_id = $userId ORDER BY f.created_at DESC";
    } else {
        // Public designs
        $sql = "SELECT * FROM designs WHERE is_public = 1 ORDER BY created_at DESC";
    }

    $result = $conn->query($sql);
    
    $designs = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $row['grid_data'] = json_decode($row['grid_data']);
            $designs[] = $row;
        }
    }
    echo json_encode($designs);

} elseif ($method === 'POST') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $_SESSION['user_id'];
    
    // Check if this is a favorite toggle
    if (isset($data['action']) && $data['action'] === 'favorite') {
        $designId = intval($data['design_id']);
        // Check if already favorite
        $check = $conn->query("SELECT id FROM favorites WHERE user_id = $userId AND design_id = $designId");
        if ($check->num_rows > 0) {
            // Remove
            $conn->query("DELETE FROM favorites WHERE user_id = $userId AND design_id = $designId");
            echo json_encode(['status' => 'success', 'message' => 'Removed from favorites']);
        } else {
            // Add
            $conn->query("INSERT INTO favorites (user_id, design_id) VALUES ($userId, $designId)");
            echo json_encode(['status' => 'success', 'message' => 'Added to favorites']);
        }
        exit;
    }

    // Save Design
    if (isset($data['name']) && isset($data['grid_data'])) {
        $name = $conn->real_escape_string($data['name']);
        $gridData = json_encode($data['grid_data']); 
        $width = isset($data['width']) ? intval($data['width']) : 16;
        $height = isset($data['height']) ? intval($data['height']) : 16;
        $isPublic = isset($data['is_public']) ? intval($data['is_public']) : 0;
        
        $sql = "INSERT INTO designs (user_id, name, grid_data, width, height, is_public) 
                VALUES ($userId, '$name', '$gridData', $width, $height, $isPublic)";
        
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    }
}

$conn->close();
?>
