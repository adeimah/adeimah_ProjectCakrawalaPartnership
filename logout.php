<?php
session_start();
session_destroy();
// REVISI: Tambahkan parameter logout=1 untuk notifikasi di halaman login
header("Location: login.php?logout=1"); 
exit();
?>