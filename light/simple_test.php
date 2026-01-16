<!DOCTYPE html>
<html>
<head>
    <title>Simple Test</title>
</head>
<body>
    <h1>Testing Light Monitor</h1>
    <button onclick="testConnection()">Test Database Connection</button>
    <button onclick="testAjax()">Test AJAX Call</button>
    
    <div id="results" style="margin-top: 20px; padding: 20px; background: #f0f0f0; font-family: monospace; white-space: pre-wrap;"></div>

    <script>
        function testConnection() {
            document.getElementById('results').innerHTML = 'Testing database connection...';
            
            fetch('debug_test.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('results').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = 'Error: ' + error;
                });
        }
        
        function testAjax() {
            document.getElementById('results').innerHTML = 'Testing AJAX call to light_monitor.php...';
            
            fetch('light_monitor.php?action=get_status&last_id=0')
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text();
                })
                .then(data => {
                    document.getElementById('results').innerHTML = 'Raw response:\n' + data;
                    
                    // Try to parse as JSON
                    try {
                        const jsonData = JSON.parse(data);
                        document.getElementById('results').innerHTML += '\n\nParsed JSON:\n' + JSON.stringify(jsonData, null, 2);
                    } catch (e) {
                        document.getElementById('results').innerHTML += '\n\nNot valid JSON: ' + e.message;
                    }
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = 'AJAX Error: ' + error;
                });
        }
    </script>
</body>
</html>