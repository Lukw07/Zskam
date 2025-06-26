<?php
// SMTP Configuration for Forpsi
return [
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587, // TLS port (recommended by Forpsi)
        'encryption' => 'tls', // or 'ssl' for port 465
        'username' => 'webkamenicka@gmail.com', // Your full email address
        'password' => 'johk ifjj itgc sabu', // You need to set your email password here
        'from_email' => 'webkamenicka@gmail.com',
        'from_name' => 'IT - ZŠ Kamenická',
        'charset' => 'UTF-8'
    ],
    'admin' => [
        'email' => 'webkamenicka@gmail.com', // Change this to your admin email
        'name' => 'Administrátor'
    ],
    'site' => [
        'name' => 'IT - ZŠ Kamenická',
        'url' => 'https://it.zskamenicka.cz' // Update with your actual domain
    ],
    'recipients' => [
        'admin' => 'webkamenicka@gmail.com'
    ]
]; 