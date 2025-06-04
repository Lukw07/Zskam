<?php
require_once 'auth.php';
require_once 'db.php';
redirect_if_not_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    
    // Kontrola, zda uživatel má právo smazat rezervaci
    $stmt = $conn->prepare("SELECT user_id FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $reservation = $result->fetch_assoc();
        
        // Uživatel může smazat pouze své vlastní rezervace nebo admin může smazat všechny
        if (is_admin() || $reservation['user_id'] == $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Rezervace byla úspěšně smazána";
            } else {
                $_SESSION['error'] = "Chyba při mazání rezervace";
            }
        } else {
            $_SESSION['error'] = "Nemáte oprávnění smazat tuto rezervaci";
        }
    } else {
        $_SESSION['error'] = "Rezervace nebyla nalezena";
    }
}

// Přesměrování zpět na dashboard
header("Location: dashboard.php");
exit(); 