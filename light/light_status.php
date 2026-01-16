<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arduino Light Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 20px;
        }
        
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-width: 350px;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            font-size: 2.5em;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .status-display {
            margin: 30px 0;
        }
        
        .light-bulb {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            transition: all 0.5s ease;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 3em;
        }
        
        .light-on {
            background: radial-gradient(circle, #ffeb3b, #ffc107);
            box-shadow: 0 0 50px #ffeb3b, 0 0 100px #ffeb3b, 0 0 150px #ffeb3b;
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        .light-off {
            background: radial-gradient(circle, #424242, #212121);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
        
        @keyframes glow {
            from {
                box-shadow: 0 0 30px #ffeb3b, 0 0 60px #ffeb3b, 0 0 90px #ffeb3b;
            }
            to {
                box-shadow: 0 0 50px #ffeb3b, 0 0 100px #ffeb3b, 0 0 150px #ffeb3b;
            }
        }
        
        .status-text {
            font-size: 2em;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .status-on {
            color: #ffeb3b;
            text-shadow: 0 0 10px #ffeb3b;
        }
        
        .status-off {
            color: #9e9e9e;
        }
        
        .last-update {
            font-size: 0.9em;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 20px;
        }
        
        .connection-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            z-index: 1000;
        }
        
        .connected {
            background: #4caf50;
            color: white;
        }
        
        .disconnected {
            background: #f44336;
            color: white;
        }
        
        .loading {
            background: #ff9800;
            color: white;
        }
        
        .data-log {
            margin-top: 30px;
            max-height: 250px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 15px;
        }
        
        .data-log h3 {
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .data-entry {
            font-family: monospace;
            font-size: 0.9em;
            margin: 8px 0;
            padding: 8px;
            border-left: 3px solid #667eea;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            color: #ffcdd2;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .light-bulb {
                width: 100px;
                height: 100px;
                font-size: 2.5em;
            }
            
            .status-text {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="connection-status loading" id="connectionStatus">Loading...</div>
    
    <div class="container">
        <h1>🏠 Arduino Light Monitor</h1>
        
        <div class="status-display">
            <div class="light-bulb light-off" id="lightBulb">💡</div>
            <div class="status-text status-off" id="statusText">Loading...</div>
            <div class="last-update" id="lastUpdate">Connecting to database...</div>
        </div>
        
        <div class="data-log">
            <h3>Recent Data:</h3>
            <div id="dataLog">Loading recent entries...</div>
        </div>
        
        <div id="errorMessage" class="error-message" style="display: none;"></div>
    </div>

    <script>
        let isConnected = false;
        let lastDataId = 0;
        let updateInterval;
        
        // Start monitoring when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateStatus();
            startAutoUpdate();
        });
        
        function startAutoUpdate() {
            // Update every 2 seconds
            updateInterval = setInterval(updateStatus, 2000);
        }
        
        function updateStatus() {
            fetch('light_monitor.php?action=get_status&last_id=' + lastDataId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showError(data.error);
                        setConnectionStatus(false);
                    } else {
                        hideError();
                        setConnectionStatus(true);
                        updateDisplay(data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to connect to server: ' + error.message);
                    setConnectionStatus(false);
                });
        }
        
        function updateDisplay(data) {
            const lightBulb = document.getElementById('lightBulb');
            const statusText = document.getElementById('statusText');
            const lastUpdate = document.getElementById('lastUpdate');
            const dataLog = document.getElementById('dataLog');
            
            // Update status display
            if (data.current_status) {
                const status = data.current_status.status;
                const rawData = data.current_status.raw_data;
                const timestamp = data.current_status.timestamp;
                
                // Update visual elements
                if (status === 'ON') {
                    lightBulb.className = 'light-bulb light-on';
                    statusText.className = 'status-text status-on';
                    statusText.textContent = 'Light is ON';
                } else if (status === 'OFF') {
                    lightBulb.className = 'light-bulb light-off';
                    statusText.className = 'status-text status-off';
                    statusText.textContent = 'Light is OFF';
                } else {
                    lightBulb.className = 'light-bulb light-off';
                    statusText.className = 'status-text status-off';
                    statusText.textContent = 'Unknown Status';
                }
                
                // Update timestamp
                const date = new Date(timestamp);
                lastUpdate.textContent = `Last update: ${date.toLocaleTimeString()}`;
            }
            
            // Update data log with new entries
            if (data.new_entries && data.new_entries.length > 0) {
                let logHtml = '';
                
                // Add new entries and existing ones
                data.recent_entries.forEach(entry => {
                    const date = new Date(entry.timestamp);
                    logHtml += `<div class="data-entry">
                        <strong>${date.toLocaleTimeString()}</strong>: ${entry.raw_data}
                    </div>`;
                });
                
                dataLog.innerHTML = logHtml || 'No data available';
                
                // Update last ID
                if (data.new_entries.length > 0) {
                    lastDataId = Math.max(...data.new_entries.map(entry => entry.id));
                }
            }
        }
        
        function setConnectionStatus(connected) {
            const statusElement = document.getElementById('connectionStatus');
            
            if (connected && !isConnected) {
                statusElement.textContent = 'Connected';
                statusElement.className = 'connection-status connected';
                isConnected = true;
            } else if (!connected && isConnected) {
                statusElement.textContent = 'Disconnected';
                statusElement.className = 'connection-status disconnected';
                isConnected = false;
            } else if (!connected && !isConnected) {
                statusElement.textContent = 'Connection Failed';
                statusElement.className = 'connection-status disconnected';
            }
        }
        
        function showError(message) {
            const errorElement = document.getElementById('errorMessage');
            errorElement.textContent = 'Error: ' + message;
            errorElement.style.display = 'block';
        }
        
        function hideError() {
            const errorElement = document.getElementById('errorMessage');
            errorElement.style.display = 'none';
        }
        
        // Handle page visibility changes to pause/resume updates
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (updateInterval) {
                    clearInterval(updateInterval);
                }
            } else {
                startAutoUpdate();
                updateStatus(); // Immediate update when page becomes visible
            }
        });
    </script>

    <?php
    // PHP Backend Code
    if (isset($_GET['action']) && $_GET['action'] === 'get_status') {
        header('Content-Type: application/json');
        
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
            
            if ($currentData) {
                $response['current_status'] = array(
                    'id' => $currentData['id'],
                    'raw_data' => $currentData['data'],
                    'timestamp' => $currentData['time'],
                    'status' => determineLightStatus($currentData['data'])
                );
            }
            
            // Get new entries since last check
            $stmt = $conn->prepare("SELECT id, data, time FROM Data WHERE id > ? ORDER BY id DESC");
            $stmt->execute([$lastId]);
            $newEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($newEntries) {
                $response['new_entries'] = array_map(function($entry) {
                    return array(
                        'id' => $entry['id'],
                        'raw_data' => $entry['data'],
                        'timestamp' => $entry['time'],
                        'status' => determineLightStatus($entry['data'])
                    );
                }, $newEntries);
            } else {
                $response['new_entries'] = array();
            }
            
            // Get recent entries for display (last 10)
            $stmt = $conn->prepare("SELECT id, data, time FROM Data ORDER BY id DESC LIMIT 10");
            $stmt->execute();
            $recentEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['recent_entries'] = array_map(function($entry) {
                return array(
                    'id' => $entry['id'],
                    'raw_data' => $entry['data'],
                    'timestamp' => $entry['time'],
                    'status' => determineLightStatus($entry['data'])
                );
            }, $recentEntries);
            
            echo json_encode($response);
            
        } catch(PDOException $e) {
            echo json_encode(array('error' => 'Database connection failed: ' . $e->getMessage()));
        }
        
        exit;
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
</body>
</html>