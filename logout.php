<?php
// Zabezpečení logoutu
session_start();

// Vymazání všech session proměnných
$_SESSION = array();

// Smazání session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Zničení session
session_destroy();

// Přesměrování na přihlašovací stránku
header("Location: index.php");
exit();
?>