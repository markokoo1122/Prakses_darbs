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
    $data = json_decode(file_get_contents('php://input'), true);
    $matrixIpFile = __DIR__ . '/matrix_ip.txt';
    $action = '';
    if (isset($data['action'])) {
        $action = $data['action'];
    } elseif (isset($_GET['action'])) {
        $action = $_GET['action'];
    }
    if (!isLoggedIn() && !in_array($action, ['proxy_health','proxy_binary','proxy_to_matrix','save_matrix_ip'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_health') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        if (!$targetIp) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing IP']);
            exit;
        }
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $url = "http://$targetIp/health";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (($error || $httpCode >= 400) && $targetIp !== '192.168.4.1') {
            $url2 = "http://192.168.4.1/health";
            $ch2 = curl_init($url2);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'used_ip' => '192.168.4.1', 'health' => json_decode($response2, true)]);
                exit;
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code(200);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'health' => json_decode($response, true)]);
        }
        exit;
    }
    $userId = isLoggedIn() ? $_SESSION['user_id'] : 0;
    
    if (isset($_GET['action']) && $_GET['action'] === 'upload_media') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No image uploaded']);
            exit;
        }
        
        $tmpPath = $_FILES['image']['tmp_name'];
        $originalName = basename($_FILES['image']['name']);
        $originalNameEscaped = $conn->real_escape_string($originalName);
        
        $scriptPath = realpath(__DIR__ . '/../image_to_led_converter.py');
        if (!$scriptPath || !file_exists($scriptPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Converter script not found']);
            exit;
        }
        
        $python = 'python';
        $cmd = escapeshellcmd($python) . ' ' . escapeshellarg($scriptPath) . ' --web ' . escapeshellarg($tmpPath) . ' --width 64 --height 64';
        if (isset($_POST['effect']) && $_POST['effect'] !== '') {
            $cmd .= ' --effect ' . escapeshellarg($_POST['effect']);
        }
        
        $output = [];
        $returnVar = 0;
        exec($cmd . ' 2>&1', $output, $returnVar);
        $outputStr = implode("\n", $output);
        
        $data = json_decode($outputStr, true);
        if (!$data || !isset($data['status'])) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to parse converter output', 'raw' => $outputStr]);
            exit;
        }
        
        if ($data['status'] !== 'ok') {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => isset($data['message']) ? $data['message'] : 'Conversion failed']);
            exit;
        }
        
        $isGif = !empty($data['is_gif']) ? 1 : 0;
        $width = isset($data['width']) ? intval($data['width']) : 64;
        $height = isset($data['height']) ? intval($data['height']) : 64;
        $frameCount = isset($data['frame_count']) ? intval($data['frame_count']) : 1;
        $frameDelay = isset($data['frame_delay']) && $data['frame_delay'] !== null ? intval($data['frame_delay']) : null;
        $code = isset($data['arduino_code']) ? $data['arduino_code'] : '';
        $codeEscaped = $conn->real_escape_string($code);
        
        if ($frameDelay === null) {
            $frameDelaySql = "NULL";
        } else {
            $frameDelaySql = intval($frameDelay);
        }
        
        $sql = "INSERT INTO media_conversions (user_id, original_filename, is_gif, width, height, frame_count, frame_delay, arduino_code) 
                VALUES ($userId, '$originalNameEscaped', $isGif, $width, $height, $frameCount, $frameDelaySql, '$codeEscaped')";
        
        if ($conn->query($sql) === TRUE) {
            $framesData = isset($data['frames']) ? $data['frames'] : [];
            // Log frame info for debugging
            error_log("upload_media: width=$width, height=$height, frameCount=" . count($framesData) . ", frameDelay=$frameDelay");
            
            echo json_encode([
                'status' => 'success',
                'conversion_id' => $conn->insert_id,
                'is_gif' => $isGif,
                'width' => $width,
                'height' => $height,
                'frame_delay' => $frameDelay,
                'frames' => $framesData
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }
    
    // $data and $matrixIpFile already initialized above

    // Save matrix IP from editor UI
    if (isset($data['action']) && $data['action'] === 'save_matrix_ip') {
        $ip = isset($data['ip']) ? trim($data['ip']) : '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        file_put_contents($matrixIpFile, $ip);
        echo json_encode(['status' => 'success', 'message' => 'Saved matrix IP']);
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'favorite') {
        $designId = intval($data['design_id']);
        $check = $conn->query("SELECT id FROM favorites WHERE user_id = $userId AND design_id = $designId");
        if ($check->num_rows > 0) {
            $conn->query("DELETE FROM favorites WHERE user_id = $userId AND design_id = $designId");
            echo json_encode(['status' => 'success', 'message' => 'Removed from favorites']);
        } else {
            $conn->query("INSERT INTO favorites (user_id, design_id) VALUES ($userId, $designId)");
            echo json_encode(['status' => 'success', 'message' => 'Added to favorites']);
        }
        exit;
    }

    // Proxy to Matrix
    if (isset($data['action']) && $data['action'] === 'proxy_to_matrix') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        // if caller didn't provide an IP, try saved one
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        $payload = isset($data['payload']) ? $data['payload'] : null;

        if (!$targetIp || !$payload) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing IP or payload']);
            exit;
        }

        // Validate IP (basic check)
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }

        $url = "http://$targetIp/display";
        $jsonData = json_encode($payload);
        
        // Log payload size and structure for debugging
        error_log("proxy_to_matrix: target_ip=$targetIp, payload_size=" . strlen($jsonData) . " bytes, width=" . ($payload['width'] ?? 'N/A') . ", height=" . ($payload['height'] ?? 'N/A') . ", frames=" . (is_array($payload['frames']) ? count($payload['frames']) : 'N/A'));

        // Helper to POST JSON and return array(error,httpCode,response)
        $doPost = function($url, $jsonData) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            return [$error, $httpCode, $response];
        };

        list($error, $httpCode, $response) = $doPost($url, $jsonData);

        // If first attempt failed or returned an error code, try fallback to AP IP (192.168.4.1)
        if ($error || ($httpCode >= 400)) {
            error_log("proxy_to_matrix: primary target_ip=$targetIp failed (err=" . $error . ", code=" . $httpCode . ") - trying fallback 192.168.4.1");
            $fallback = '192.168.4.1';
            if ($fallback !== $targetIp) {
                $url2 = "http://$fallback/display";
                list($error2, $httpCode2, $response2) = $doPost($url2, $jsonData);
                if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                    error_log("proxy_to_matrix: fallback target_ip=$fallback succeeded, http_code=$httpCode2");
                    http_response_code($httpCode2);
                    echo json_encode(['status' => 'success', 'used_ip' => $fallback, 'response' => $response2]);
                    exit;
                } else {
                    error_log("proxy_to_matrix: fallback target_ip=$fallback also failed (err=" . $error2 . ", code=" . $httpCode2 . ")");
                }
            }
        }

        // Final response (either success from primary, or failure)
        if ($error) {
            error_log("proxy_to_matrix: target_ip=$targetIp, curl_error=" . $error);
            http_response_code(502); // Bad Gateway
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            error_log("proxy_to_matrix: target_ip=$targetIp, http_code=" . $httpCode . ", resp_len=" . strlen($response));
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'message' => 'Matrix responded with ' . $httpCode, 'response' => $response]);
        }
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_binary') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        $b64 = isset($data['data']) ? $data['data'] : '';
        $width = isset($data['width']) ? intval($data['width']) : 64;
        $height = isset($data['height']) ? intval($data['height']) : 64;
        if (!$targetIp || !$b64) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing IP or data']);
            exit;
        }
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid base64']);
            exit;
        }
        $url = "http://$targetIp/upload_bin?width=$width&height=$height";
        $doPostBin = function($url, $raw) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/octet-stream',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            return [$error, $httpCode, $response];
        };
        list($error, $httpCode, $response) = $doPostBin($url, $raw);
        if ($error || ($httpCode >= 400)) {
            $fallback = '192.168.4.1';
            if ($fallback !== $targetIp) {
                $url2 = "http://$fallback/upload_bin?width=$width&height=$height";
                list($error2, $httpCode2, $response2) = $doPostBin($url2, $raw);
                if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                    http_response_code($httpCode2);
                    echo json_encode(['status' => 'success', 'used_ip' => $fallback, 'response' => $response2]);
                    exit;
                }
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'response' => $response]);
        }
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_b64') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        $b64 = isset($data['data']) ? $data['data'] : '';
        $width = isset($data['width']) ? intval($data['width']) : 64;
        $height = isset($data['height']) ? intval($data['height']) : 64;
        $overlay = isset($data['overlay']) ? intval($data['overlay']) : 0;
        if (!$targetIp || !$b64) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing IP or data']);
            exit;
        }
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $url = "http://$targetIp/upload_b64?width=$width&height=$height" . ($overlay ? "&overlay=1" : "");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $b64);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain',
            'Connection: close',
            'Expect:'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (($error || $httpCode >= 400) && $targetIp !== '192.168.4.1') {
            $url2 = "http://192.168.4.1/upload_b64?width=$width&height=$height" . ($overlay ? "&overlay=1" : "");
            $ch2 = curl_init($url2);
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $b64);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Content-Type: text/plain',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                http_response_code($httpCode2);
                echo json_encode(['status' => 'success', 'used_ip' => '192.168.4.1', 'response' => $response2]);
                exit;
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'response' => $response]);
        }
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_begin_frame') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        $width = isset($data['width']) ? intval($data['width']) : 64;
        $height = isset($data['height']) ? intval($data['height']) : 64;
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $url = "http://$targetIp/begin_frame?width=$width&height=$height";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain',
            'Connection: close',
            'Expect:'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (($error || $httpCode >= 400) && $targetIp !== '192.168.4.1') {
            $url2 = "http://192.168.4.1/begin_frame?width=$width&height=$height";
            $ch2 = curl_init($url2);
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Content-Type: text/plain',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                http_response_code($httpCode2);
                echo json_encode(['status' => 'success', 'used_ip' => '192.168.4.1', 'response' => $response2]);
                exit;
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'response' => $response]);
        }
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_b64_row') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        $b64 = isset($data['data']) ? $data['data'] : '';
        $width = isset($data['width']) ? intval($data['width']) : 64;
        $height = isset($data['height']) ? intval($data['height']) : 64;
        $row = isset($data['row']) ? intval($data['row']) : -1;
        if (!$targetIp || !$b64 || $row < 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing IP, data, or row']);
            exit;
        }
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $url = "http://$targetIp/row_b64?width=$width&height=$height&row=$row";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $b64);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain',
            'Connection: close',
            'Expect:'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (($error || $httpCode >= 400) && $targetIp !== '192.168.4.1') {
            $url2 = "http://192.168.4.1/row_b64?width=$width&height=$height&row=$row";
            $ch2 = curl_init($url2);
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $b64);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Content-Type: text/plain',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                http_response_code($httpCode2);
                echo json_encode(['status' => 'success', 'used_ip' => '192.168.4.1', 'response' => $response2]);
                exit;
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'response' => $response]);
        }
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_b64_rows') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        $b64 = isset($data['data']) ? $data['data'] : '';
        $width = isset($data['width']) ? intval($data['width']) : 64;
        $height = isset($data['height']) ? intval($data['height']) : 64;
        $start = isset($data['start']) ? intval($data['start']) : -1;
        $count = isset($data['count']) ? intval($data['count']) : -1;
        if (!$targetIp || !$b64 || $start < 0 || $count <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing IP, data, start or count']);
            exit;
        }
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $url = "http://$targetIp/rows_b64?width=$width&height=$height&start=$start&count=$count";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $b64);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain',
            'Connection: close',
            'Expect:'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (($error || $httpCode >= 400) && $targetIp !== '192.168.4.1') {
            $url2 = "http://192.168.4.1/rows_b64?width=$width&height=$height&start=$start&count=$count";
            $ch2 = curl_init($url2);
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $b64);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Content-Type: text/plain',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                http_response_code($httpCode2);
                echo json_encode(['status' => 'success', 'used_ip' => '192.168.4.1', 'response' => $response2]);
                exit;
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'response' => $response]);
        }
        exit;
    }
    
    if (isset($data['action']) && $data['action'] === 'proxy_end_frame') {
        $targetIp = isset($data['ip']) ? $data['ip'] : '';
        if (!$targetIp && file_exists($matrixIpFile)) {
            $t = trim(file_get_contents($matrixIpFile));
            if ($t) $targetIp = $t;
        }
        if (!filter_var($targetIp, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP address']);
            exit;
        }
        $url = "http://$targetIp/end_frame";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/plain',
            'Connection: close',
            'Expect:'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (($error || $httpCode >= 400) && $targetIp !== '192.168.4.1') {
            $url2 = "http://192.168.4.1/end_frame";
            $ch2 = curl_init($url2);
            curl_setopt($ch2, CURLOPT_POST, 1);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Content-Type: text/plain',
                'Connection: close',
                'Expect:'
            ]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $error2 = curl_error($ch2);
            curl_close($ch2);
            if (!$error2 && $httpCode2 >= 200 && $httpCode2 < 300) {
                http_response_code($httpCode2);
                echo json_encode(['status' => 'success', 'used_ip' => '192.168.4.1', 'response' => $response2]);
                exit;
            }
        }
        if ($error) {
            http_response_code(502);
            echo json_encode(['status' => 'error', 'message' => 'Failed to reach matrix: ' . $error]);
        } else {
            http_response_code($httpCode);
            echo json_encode(['status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error', 'used_ip' => $targetIp, 'response' => $response]);
        }
        exit;
    }
    
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
