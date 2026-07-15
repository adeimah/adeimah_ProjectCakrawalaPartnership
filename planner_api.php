<?php
// File: project uas_patnership/planner_api.php
session_start();

header('Content-Type: application/json');

// Cek autentikasi
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login kembali.']);
    exit();
}

include 'config.php'; 

$action = $_POST['action'] ?? '';
$current_user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'add':
            $title = trim($_POST['title'] ?? '');
            $event_date = $_POST['event_date'] ?? null;
            $description = trim($_POST['description'] ?? '');
            
            if (empty($title) || empty($event_date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Judul dan Tanggal harus diisi!']);
                exit();
            }

            // INSERT menggunakan ID pengguna saat ini
            $stmt = $pdo->prepare("
                INSERT INTO planner_events 
                (title, description, event_date, created_by_user_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $description, $event_date, $current_user_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dibuat!', 'id' => $pdo->lastInsertId()]);
            break;

        case 'edit':
            $id = $_POST['id'] ?? null;
            $title = trim($_POST['title'] ?? '');
            $event_date = $_POST['event_date'] ?? null;
            $description = trim($_POST['description'] ?? '');

            if (!$id || empty($title) || empty($event_date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID, Judul, dan Tanggal harus valid!']);
                exit();
            }

            // UPDATE HANYA jika ID event dan ID pengguna saat ini cocok
            $stmt = $pdo->prepare("
                UPDATE planner_events 
                SET title = ?, description = ?, event_date = ?
                WHERE id = ? AND created_by_user_id = ?
            ");
            $stmt->execute([
                $title, $description, $event_date, $id, $current_user_id
            ]);
            
            // Cek apakah ada baris yang terpengaruh (memastikan pengguna adalah pemilik)
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui. Jadwal tidak ditemukan atau Anda tidak memiliki izin.']);
                exit();
            }
            
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil diperbarui!']);
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID Jadwal tidak valid.']);
                exit();
            }
            
            // DELETE HANYA jika ID event dan ID pengguna saat ini cocok
            $stmt = $pdo->prepare("
                DELETE FROM planner_events 
                WHERE id = ? AND created_by_user_id = ?
            ");
            $stmt->execute([$id, $current_user_id]);

            // Cek apakah ada baris yang terpengaruh (memastikan pengguna adalah pemilik)
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus. Jadwal tidak ditemukan atau Anda tidak memiliki izin.']);
                exit();
            }
            
            echo json_encode(['success' => true, 'message' => 'Jadwal berhasil dihapus.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenal.']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>