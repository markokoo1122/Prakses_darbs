<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "led_matrix_db";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->select_db($dbname);
} else {
    die("Error creating database: " . $conn->error);
}

// --- Users Table ---
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql);

// --- Designs Table (Updated) ---
// We check if columns exist to avoid errors on re-run, or just Create if not exists
$sql = "CREATE TABLE IF NOT EXISTS designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(255) NOT NULL,
    grid_data MEDIUMTEXT NOT NULL, 
    width INT DEFAULT 16,
    height INT DEFAULT 16,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";
$conn->query($sql);

// Add columns if they don't exist (Migration for existing setup)
$columns = $conn->query("SHOW COLUMNS FROM designs");
$existing_cols = [];
while($row = $columns->fetch_assoc()) {
    $existing_cols[] = $row['Field'];
}

if (!in_array('user_id', $existing_cols)) {
    $conn->query("ALTER TABLE designs ADD COLUMN user_id INT AFTER id");
    $conn->query("ALTER TABLE designs ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
}
if (!in_array('width', $existing_cols)) {
    $conn->query("ALTER TABLE designs ADD COLUMN width INT DEFAULT 16 AFTER grid_data");
}
if (!in_array('height', $existing_cols)) {
    $conn->query("ALTER TABLE designs ADD COLUMN height INT DEFAULT 16 AFTER width");
}
if (!in_array('is_public', $existing_cols)) {
    $conn->query("ALTER TABLE designs ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER height");
}
// Modify grid_data to be MEDIUMTEXT to support larger grids (64x64 is large JSON)
$conn->query("ALTER TABLE designs MODIFY grid_data MEDIUMTEXT NOT NULL");


// --- Favorites Table ---
$sql = "CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    design_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (design_id) REFERENCES designs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fav (user_id, design_id)
)";
$conn->query($sql);

?>
