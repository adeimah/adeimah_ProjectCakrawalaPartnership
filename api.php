<?php
// File: project uas_patnership/api.php
session_start();

// Cek autentikasi
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    if (($_REQUEST['action'] ?? '') === 'move') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Silakan login kembali.']);
        exit();
    }
    header("Location: login.php");
    exit();
}

include 'config.php'; 

$action = $_REQUEST['action'] ?? '';
$current_user_id = $_SESSION['user_id'];
$redirect_url = 'pipeline_tracking.php';

// Fungsi untuk set notifikasi dan redirect
function set_notification_and_redirect($message, $type, $url = 'pipeline_tracking.php') {
    $_SESSION['notification'] = ['message' => $message, 'type' => $type];
    header("Location: $url");
    exit();
}

switch ($action) {
    case 'add':
    case 'edit':
        $is_edit = $action === 'edit';
        $id = $is_edit ? $_POST['id'] : null;
        $status = $_POST['status'] ?? 'daily_canvasing';
        $company_name = trim($_POST['company_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $category = trim($_POST['category'] ?? 'Local'); // Field Baru
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $created_by_user_id = $_POST['created_by_user_id'] ?? $current_user_id;

        if (empty($company_name) || empty($address) || empty($contact_person) || empty($phone)) {
            set_notification_and_redirect('Semua field wajib diisi!', 'error');
        }

        $document_path = null;
        $existing_document_path = null;

        try {
            if ($is_edit) {
                $stmt_old = $pdo->prepare("SELECT document_path FROM pipeline_cards WHERE id = ?");
                $stmt_old->execute([$id]);
                $old_card = $stmt_old->fetch(PDO::FETCH_ASSOC);
                $existing_document_path = $old_card['document_path'] ?? null;
            }
            $document_path = $existing_document_path;

            // Handle File Upload
            if ($status === 'signed' && isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['document'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if ($file_ext !== 'pdf') {
                     set_notification_and_redirect('Hanya file PDF yang diizinkan.', 'error');
                }
                
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_file_name = uniqid('doc_') . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $document_path = $destination;
                    
                    if ($is_edit && $existing_document_path && file_exists($existing_document_path)) {
                        unlink($existing_document_path);
                    }
                } else {
                    set_notification_and_redirect('Gagal mengupload file.', 'error');
                }
            }


            if ($is_edit) {
                $stmt = $pdo->prepare("
                    UPDATE pipeline_cards 
                    SET company_name = ?, address = ?, category = ?, contact_person = ?, phone = ?, email = ?, notes = ?, status = ?, document_path = ?, last_edited_by_user_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $company_name, $address, $category, $contact_person, $phone, $email, $notes, $status, $document_path, $current_user_id, $id
                ]);
                
                set_notification_and_redirect('Kartu berhasil diperbarui.', 'success');
                
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO pipeline_cards 
                    (company_name, address, category, contact_person, phone, email, notes, status, document_path, created_by_user_id, last_edited_by_user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $company_name, $address, $category, $contact_person, $phone, $email, $notes, $status, $document_path, $created_by_user_id, $current_user_id
                ]);
                
                set_notification_and_redirect('Kartu baru berhasil ditambahkan.', 'success');
            }
        } catch (PDOException $e) {
            set_notification_and_redirect('Database error: ' . $e->getMessage(), 'error');
        }
        break;

    case 'move':
        // Logika MOVE (AJAX: Mengembalikan JSON)
        $id = $_REQUEST['id'] ?? null;
        $status = $_REQUEST['status'] ?? null;
        
        header('Content-Type: application/json');

        if (!$id || !$status || !in_array($status, ['daily_canvasing', 'meeting', 'verbal_agree', 'signed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID kartu atau status tidak valid.']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE pipeline_cards 
                SET status = ?, last_edited_by_user_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $current_user_id, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Kartu berhasil dipindahkan ke status ' . ucfirst(str_replace('_', ' ', $status)) . '.']);
            exit();

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        }
        break;
        
    case 'delete':
    case 'delete_file':
        // Logika DELETE/DELETE_FILE (NON-AJAX: Tetap Redirect)
        $id = $_REQUEST['id'] ?? null;
        if (!$id) { set_notification_and_redirect('ID kartu tidak valid.', 'error'); }

        try {
            $stmt_old = $pdo->prepare("SELECT document_path FROM pipeline_cards WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_card = $stmt_old->fetch(PDO::FETCH_ASSOC);
            $existing_document_path = $old_card['document_path'] ?? null;

            if ($action === 'delete') {
                 if ($existing_document_path && file_exists($existing_document_path)) {
                    unlink($existing_document_path);
                }

                $stmt = $pdo->prepare("DELETE FROM pipeline_cards WHERE id = ?");
                $stmt->execute([$id]);
                set_notification_and_redirect('Kartu berhasil dihapus.', 'success');
            }
            
            if ($action === 'delete_file') {
                if ($existing_document_path && file_exists($existing_document_path)) {
                    unlink($existing_document_path);
                } else { set_notification_and_redirect('File tidak ditemukan di server.', 'error'); }

                $stmt = $pdo->prepare("
                    UPDATE pipeline_cards 
                    SET document_path = NULL, last_edited_by_user_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$current_user_id, $id]);
                set_notification_and_redirect('Dokumen berhasil dihapus dari kartu.', 'success');
            }
        } catch (PDOException $e) {
            set_notification_and_redirect('Database error: ' . $e->getMessage(), 'error');
        }
        break;

    default:
        set_notification_and_redirect('Action tidak dikenal.', 'error');
        break;
}
?>