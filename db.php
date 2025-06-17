<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$db   = getenv('DB_NAME');

if (empty($host) || empty($user) || empty($pass) || empty($db)) {
    die("Chybí nastavení databázových údajů v environment proměnných.");
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Připojení k databázi selhalo: " . $conn->connect_error);
}
