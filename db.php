<?php
// Fetch database credentials from environment variables
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$db   = getenv('DB_NAME'); // Or DB_DATABASE, be consistent

// Check if environment variables are set (optional but good for debugging)
if (empty($host) || empty($user) || empty($pass) || empty($db)) {
    die("Database connection details are not fully configured. Please set DB_HOST, DB_USER, DB_PASSWORD, and DB_NAME environment variables.");
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: You can add a success message for testing if needed
// echo "Connected successfully to the database!";
?>