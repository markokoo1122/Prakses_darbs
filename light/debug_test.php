<?php
// debug_test.php - Save this as a separate file to test your database connection

header('Content-Type: application/json');

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "Arduino";

echo "Testing database connection...\n";

try {
    // Create connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Database connected successfully!\n";
    
    // Test if Data table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'Data'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Table 'Data' found!\n";
        
        // Check table structure
        $stmt = $conn->prepare("DESCRIBE Data");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✓ Table structure:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        // Get sample data
        $stmt = $conn->prepare("SELECT * FROM Data ORDER BY id DESC LIMIT 3");
        $stmt->execute();
        $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✓ Sample data (last 3 rows):\n";
        foreach ($sampleData as $row) {
            echo "  ID: " . $row['id'] . ", Data: " . $row['data'] . ", Time: " . $row['time'] . "\n";
        }
        
        // Test the status function
        function determineLightStatus($data) {
            $dataStr = strtolower(trim($data));
            
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
            
            if (is_numeric($dataStr)) {
                return floatval($dataStr) > 0 ? 'ON' : 'OFF';
            }
            
            return 'UNKNOWN';
        }
        
        echo "✓ Status detection test:\n";
        foreach ($sampleData as $row) {
            $status = determineLightStatus($row['data']);
            echo "  '" . $row['data'] . "' -> " . $status . "\n";
        }
        
    } else {
        echo "✗ Table 'Data' not found!\n";
        echo "Available tables:\n";
        $stmt = $conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            echo "  - " . $table . "\n";
        }
    }
    
} catch(PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}
?>