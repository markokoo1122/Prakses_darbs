<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($action === 'register') {
        $username = $conn->real_escape_string($data['username']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);

        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        } else {
            $sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
            if ($conn->query($sql) === TRUE) {
                echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => $conn->error]);
            }
        }
    } elseif ($action === 'login') {
        $username = $conn->real_escape_string($data['username']);
        $password = $data['password'];

        $result = $conn->query("SELECT id, password FROM users WHERE username = '$username'");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                echo json_encode(['status' => 'success', 'message' => 'Login successful', 'user' => ['id' => $row['id'], 'username' => $username]]);
            } else {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
        }
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out']);
    }
} elseif ($method === 'GET') {
    if ($action === 'check') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['logged_in' => true, 'user' => ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username']]]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
    }
}

$conn->close();
?>
