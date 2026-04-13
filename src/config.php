<?php
// ============================================================
//  config.php — Database connection
//  Place this file in your project root folder
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        
define('DB_PASS', 'root');           
define('DB_NAME', 'comp1044_irms');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8');
    return $conn;
}
?>
