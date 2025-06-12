<?php
require_once 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Aktualizace stavu incidentu
    $stmt = $conn->prepare("UPDATE technical_issues SET status = 'přečteno' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "<!DOCTYPE html>
            <html lang='cs'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Rezervo - Incident označen jako přečtený</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
                <style>
                    body {
                        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .success-card {
                        background: white;
                        padding: 2rem;
                        border-radius: 15px;
                        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                        text-align: center;
                        max-width: 500px;
                        width: 90%;
                    }
                    .success-icon {
                        font-size: 4rem;
                        color: #10b981;
                        margin-bottom: 1rem;
                    }
                </style>
            </head>
            <body>
                <div class='success-card'>
                    <div class='success-icon'>✓</div>
                    <h2 class='mb-3'>Incident byl označen jako přečtený</h2>
                    <p class='text-muted'>Můžete zavřít toto okno.</p>
                    <p class='text-muted' style='margin-top: 15px;'>Rezervo by Kryštof Tůma</p>
                </div>
            </body>
            </html>";
        } else {
            echo "<!DOCTYPE html>
            <html lang='cs'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Rezervo - Chyba</title>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
                <style>
                    body {
                        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .error-card {
                        background: white;
                        padding: 2rem;
                        border-radius: 15px;
                        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                        text-align: center;
                        max-width: 500px;
                        width: 90%;
                    }
                    .error-icon {
                        font-size: 4rem;
                        color: #ef4444;
                        margin-bottom: 1rem;
                    }
                </style>
            </head>
            <body>
                <div class='error-card'>
                    <div class='error-icon'>✕</div>
                    <h2 class='mb-3'>Chyba</h2>
                    <p class='text-muted'>Incident nebyl nalezen nebo již byl označen jako přečtený.</p>
                    <p class='text-muted' style='margin-top: 15px;'>Rezervo by Kryštof Tůma</p>
                </div>
            </body>
            </html>";
        }
    } else {
        echo "Chyba při zpracování požadavku";
    }
} else {
    echo "Chybí ID incidentu";
}
?> 