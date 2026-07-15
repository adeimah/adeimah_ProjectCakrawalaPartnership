<?php
// File: project uas_patnership/config.php

$host = 'localhost';
$dbname = 'db_partnership';
$username = 'root'; // Ganti dengan username DB Anda
$password = ''; // Ganti dengan password DB Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Pastikan user_id di set di login.php
} catch(PDOException $e) {
    die("Gagal koneksi database: " . $e->getMessage());
}
?>