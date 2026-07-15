<?php
// File: project uas_patnership/pipeline_tracking.php (FINAL REVISION - Stage Implementation)
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'] ?? 0;

include 'config.php';

// --- LOGIC PHP UNTUK FILTER DAN PENCARIAN ---

$filter_pic = $_GET['picFilter'] ?? '';
$filter_category = $_GET['categoryFilter'] ?? '';
$search_query = $_GET['searchCompany'] ?? '';
// REVISI: Filter Stage
$filter_stage = $_GET['stageFilter'] ?? ''; // Mengubah nama filter dari progressFilter menjadi stageFilter

$where_clauses = [];
$execute_params = [];

// 1. Filter PIC
if (!empty($filter_pic)) {
    $where_clauses[] = "pc.contact_person = ?";
    $execute_params[] = $filter_pic;
}
// 2. Filter Kategori
if (!empty($filter_category)) {
    $where_clauses[] = "pc.category = ?";
    $execute_params[] = $filter_category;
}
// 3. Pencarian Nama Perusahaan (Company Name)
if (!empty($search_query)) {
    $where_clauses[] = "pc.company_name LIKE ?";
    $execute_params[] = '%' . $search_query . '%';
}
// REVISI: 4. Filter Stage
if (!empty($filter_stage)) {
    // Logika mapping:
    // On Progress = daily_canvasing, meeting, verbal_agree
    // Completed = signed
    if ($filter_stage == 'On Progress') {
        $where_clauses[] = "pc.status IN ('daily_canvasing', 'meeting', 'verbal_agree')";
    } elseif ($filter_stage == 'Completed') {
        $where_clauses[] = "pc.status = 'signed'";
    }
}


$where_clause = '';
if (!empty($where_clauses)) {
    $where_clause = "WHERE " . implode(" AND ", $where_clauses);
}


// Data status dan fetch kartu
$status_data = [
    'daily_canvasing' => ['title' => 'Daily Canvasing', 'color' => '#ff9f43', 'cards' => []],
    'meeting' => ['title' => 'Meeting', 'color' => '#54a0ff', 'cards' => []],
    'verbal_agree' => ['title' => 'Verbal Agree', 'color' => '#5f27cd', 'cards' => []],
    'signed' => ['title' => 'Signed', 'color' => '#1dd1a1', 'cards' => []],
];

try {
    $stmt = $pdo->prepare("
        SELECT 
            pc.*, 
            u.full_name as last_editor_name
        FROM 
            pipeline_cards pc
        LEFT JOIN 
            users u ON pc.last_edited_by_user_id = u.id
        {$where_clause}
        ORDER BY 
            pc.updated_at DESC
    ");
    $stmt->execute($execute_params);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cards = [];
}

foreach ($cards as $card) {
    if (isset($status_data[$card['status']])) {
        $status_data[$card['status']]['cards'][] = $card;
    }
}

// Fetch unique PICs
$pic_list = [];
try {
    $stmt_pic = $pdo->prepare("SELECT DISTINCT contact_person FROM pipeline_cards WHERE contact_person IS NOT NULL AND contact_person != '' ORDER BY contact_person ASC");
    $stmt_pic->execute();
    $pic_list = $stmt_pic->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    //
}

$category_options = ['Local', 'International School', 'Oversize'];
// REVISI: Mengubah nama variabel dan isinya
$stage_options = ['On Progress', 'Completed']; 

$notification_message = '';
$notification_type = '';
if (isset($_SESSION['notification'])) {
    $notification_message = $_SESSION['notification']['message'];
    $notification_type = $_SESSION['notification']['type'];
    unset($_SESSION['notification']);
}

/**
 * Helper function untuk menentukan Stage dari status card
 * @param string $status
 * @return string
 */
function getStageOverview($status) { // Mengubah nama fungsi
    if ($status === 'signed') {
        return 'Completed';
    }
    return 'On Progress';
}

/**
 * Helper function untuk mendapatkan warna Stage
 * @param string $overview
 * @return string
 */
function getStageColor($overview) {
    if ($overview === 'Completed') {
        return '#1dd1a1'; // success
    }
    return '#4361ee'; // primary
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline Tracking - Cakrawala Partnership</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #1dd1a1;
            --warning: #ff9f43;
            --info: #54a0ff;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --light: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles (Logo REVISI) */
        .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), var(--secondary)); color: white; padding: 20px 0; box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); z-index: 100; flex-shrink: 0; }
        .logo { display: flex; align-items: center; padding: 0 20px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 20px; }
        .logo-image { 
            width: 75px; /* Ukuran logo diperbesar */
            height: 75px; /* Ukuran logo diperbesar */
            border-radius: 8px; margin-right: 12px; object-fit: contain; background-color: white; padding: 5px; 
        }
        .logo-text { display: flex; flex-direction: column; line-height: 1.0; }
        .logo h1 { font-size: 24px; font-weight: 600; }
        .logo .subtitle { font-size: 18px; opacity: 0.9; margin-top: 2px; }
        .nav-links { list-style: none; padding: 0 15px; }
        .nav-links li { margin-bottom: 10px; }
        .nav-links a { display: flex; align-items: center; padding: 12px 15px; color: rgba(255, 255, 255, 0.8); text-decoration: none; border-radius: 8px; transition: all 0.3s ease; }
        .nav-links a:hover, .nav-links a.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
        .nav-links i { font-size: 18px; margin-right: 12px; width: 24px; text-align: center; }
        .logout-section { margin-top: auto; padding: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; padding: 12px 15px; color: rgba(255, 255, 255, 0.8); text-decoration: none; border-radius: 8px; transition: all 0.3s ease; background: none; border: none; width: 100%; text-align: left; cursor: pointer; font-size: 14px; }
        .logout-btn:hover { background-color: rgba(255, 255, 255, 0.1); color: white; }
        .logout-btn i { font-size: 18px; margin-right: 12px; width: 24px; text-align: center; }

        /* Main Content Styles */
        .main-content { flex: 1; padding: 25px; overflow-x: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h2 { font-size: 24px; font-weight: 600; color: var(--dark); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .user-name { font-weight: 600; color: var(--dark); }
        
        /* Filter Section */
        .filter-container-row {
            display: flex;
            justify-content: space-between; 
            align-items: flex-end; 
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
            flex-wrap: wrap; 
            gap: 15px; 
        }

        .search-container-left {
            display: flex;
            align-items: center;
        }

        .action-container-right {
            display: flex;
            gap: 8px; 
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group-form {
            display: flex; 
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-section {
            position: relative;
        }
        
        /* Konsistensi ukuran kotak */
        .form-select, .form-input-search {
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            width: 180px; 
            cursor: pointer;
            font-size: 14px;
            height: 40px; 
        }

        .form-input-search {
            width: 650px; /* Diperkecil kembali agar tidak terlalu lebar */
            padding-left: 35px; 
            background: var(--light) url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="%236c757d" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>') no-repeat 10px center;
            background-size: 18px 18px;
        }
        
        .btn-primary {
            padding: 8px 15px; 
            height: 40px;
        }

        /* REVISI: Stage Section Style */
        .card-progress {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            margin-bottom: 8px;
            width: fit-content;
        }
        /* Menggunakan kelas progress lama agar style tidak berubah */
        .progress-on-progress {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        .progress-completed {
            background: rgba(29, 209, 161, 0.1);
            color: var(--success);
        }

        /* Pipeline Styles (No Change) */
        .pipeline-container {
            display: grid;
            grid-template-columns: repeat(4, minmax(320px, 1fr)); 
            gap: 22px;
            margin-top: 22px;
            width: fit-content;
        }

        @media (max-width: 1400px) {
            .pipeline-container {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }
        
        .pipeline-column {
            background: #e9ecef;
            border-radius: 10px;
            padding: 15px;
            min-height: 100px; 
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .column-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ced4da;
        }

        .column-title { font-weight: 700; font-size: 18px; color: var(--dark); }
        .column-count { background: var(--primary); color: white; border-radius: 20px; padding: 4px 10px; font-size: 14px; font-weight: 600; }

        .pipeline-cards {
            min-height: 250px; 
            padding: 10px;
        }
        
        /* SortableJS Styles */
        .sortable-ghost {
            opacity: 0.4;
            background-color: var(--primary);
            color: white;
            border-left-color: white !important;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: scale(1.05);
        }

        .pipeline-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: move; 
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .pipeline-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .card-title { font-weight: 700; font-size: 15px; margin-bottom: 5px; color: var(--dark); }
        .card-details { font-size: 12px; color: var(--gray); margin-bottom: 8px; }
        
        /* Desain "Terakhir Diubah" */
        .card-editor { 
            font-size: 11px;
            color: var(--gray); 
            text-align: right; 
            margin-top: 5px; 
            padding: 5px 8px;
            background: var(--light-gray);
            border-radius: 4px;
            width: fit-content;
            margin-left: auto;
        }
        
        .file-attachment { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--success); margin-top: 8px; padding: 4px 8px; background: rgba(29, 209, 161, 0.1); border-radius: 4px; width: fit-content; }

        /* Modal Styles (No Change) */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto; }
        .modal.is-open { display: flex; }
        .modal-content { background: white; border-radius: 12px; padding: 25px; width: 90%; max-width: 600px; max-height: 90vh; margin: 20px auto; overflow-y: auto; transition: all 0.3s ease; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--light-gray); }
        .modal-title { font-size: 22px; font-weight: 700; color: var(--dark); }
        .close-modal { background: none; border: none; font-size: 30px; cursor: pointer; color: var(--gray); line-height: 1; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; color: var(--dark); font-size: 14px; }
        .form-input, .form-textarea, .form-select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-input:focus, .form-textarea:focus, .form-select:focus { border-color: var(--primary); outline: none; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .file-upload-info { font-size: 12px; color: var(--gray); margin-top: 5px; }
        .file-input-container { border: 2px dashed var(--light-gray); border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; }
        .file-input-container:hover { border-color: var(--primary); background: rgba(67, 97, 238, 0.05); }
        .file-input { display: none; }
        .current-file { margin-top: 10px; font-size: 12px; color: #28a745; display: flex; align-items: center; }
        .delete-file-btn { background: none; border: none; color: #dc3545; margin-left: 10px; cursor: pointer; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 14px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--secondary); }
        .btn-secondary { background: var(--light-gray); color: var(--dark); }
        .btn-secondary:hover { background: #dee2e6; }
        .btn-danger { background: #dc3545; color: white; margin-right: auto; display: none; }
        .btn-danger:hover { background: #c82333; }
        .card-detail-view { padding: 15px; background: var(--light); border-radius: 8px; margin-top: 15px; border: 1px solid var(--light-gray); }
        .detail-item { margin-bottom: 8px; font-size: 14px; }
        .detail-item strong { display: inline-block; width: 120px; font-weight: 600; color: var(--dark); }
        .detail-link { color: var(--primary); text-decoration: none; }
        
        /* Notifikasi Interaktif */
        #notification { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            padding: 10px 15px; 
            border-radius: 5px; 
            z-index: 1001; 
            color: white; 
            opacity: 0; 
            transition: opacity 0.3s ease-in-out; 
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notif-success { background: #28a745; }
        .notif-error { background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <img src="logo cdc .png" alt="Logo Cakrawala Partnership" class="logo-image">
                <div class="logo-text">
                    <h1>Cakrawala</h1>
                    <div class="subtitle">Partnership</div>
                </div>
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="pipeline_tracking.php" class="active"><i class="fas fa-chart-bar"></i> Pipeline Tracking</a></li>
                <li><a href="reports.php"><i class="fas fa-search-location"></i> Reports</a></li>
                <li><a href="planner.php"><i class="fas fa-calendar-alt"></i> Planner</a></li>
            </ul>
            
            <div class="logout-section">
                <button class="logout-btn" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>

        <div class="main-content">
            <div class="header">
                <h2>Pipeline Tracking</h2>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>

            <div class="filter-container-row">
                
                <div class="search-container-left">
                    <form method="GET" action="pipeline_tracking.php" class="filter-group-form" style="margin: 0; padding: 0;">
                        <input type="hidden" name="categoryFilter" value="<?php echo htmlspecialchars($filter_category); ?>">
                        <input type="hidden" name="picFilter" value="<?php echo htmlspecialchars($filter_pic); ?>">
                        <input type="hidden" name="stageFilter" value="<?php echo htmlspecialchars($filter_stage); ?>"> <div class="filter-section">
                            <input type="text" id="searchCompany" name="searchCompany" class="form-input-search" placeholder="Cari Perusahaan..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </form>
                </div>
                
                <div class="action-container-right">
                    <button class="btn btn-primary" onclick="openModal('daily_canvasing')">
                        <i class="fas fa-plus"></i> Tambah Baru
                    </button>
                    
                    <form method="GET" action="pipeline_tracking.php" class="filter-group-form">
                        <input type="hidden" name="searchCompany" value="<?php echo htmlspecialchars($search_query); ?>">

                        <div class="filter-section">
                            <select id="categoryFilter" name="categoryFilter" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($category_options as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category == $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-section">
                            <select id="stageFilter" name="stageFilter" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Stage</option>
                                <?php foreach ($stage_options as $stage): // Menggunakan stage_options ?>
                                    <option value="<?php echo htmlspecialchars($stage); ?>" <?php echo $filter_stage == $stage ? 'selected' : ''; ?>><?php echo htmlspecialchars($stage); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-section">
                            <select id="picFilter" name="picFilter" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua PIC</option>
                                <?php foreach ($pic_list as $pic): ?>
                                    <option value="<?php echo htmlspecialchars($pic); ?>" <?php echo $filter_pic == $pic ? 'selected' : ''; ?>><?php echo htmlspecialchars($pic); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            <div id="notification" class="notif-<?php echo $notification_type; ?>" style="opacity: <?php echo !empty($notification_message) ? 1 : 0; ?>">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($notification_message); ?></span>
            </div>

            <div class="pipeline-container">
                <?php foreach ($status_data as $key => $status): ?>
                <?php
                    // Logika BARU untuk menampilkan PIC di judul kolom
                    $current_count = count($status['cards']);
                    $display_title = $status['title'];
                    if (!empty($filter_pic)) {
                        $display_title .= ' - ' . htmlspecialchars($filter_pic) . ' (' . $current_count . ')';
                    }
                ?>
                <div class="pipeline-column" data-status="<?php echo $key; ?>">
                    <div class="column-header">
                        <div class="column-title"><?php echo $display_title; ?></div>
                        <span class="column-count" id="count-<?php echo $key; ?>"><?php echo $current_count; ?></span>
                    </div>
                    <div class="pipeline-cards" id="<?php echo $key; ?>-cards" data-color="<?php echo $status['color']; ?>">
                        <?php foreach ($status['cards'] as $card): ?>
                            <?php
                                $json_card = json_encode($card, JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
                                $safe_json_card = htmlspecialchars($json_card, ENT_QUOTES, 'UTF-8');
                                $overview = getStageOverview($card['status']); // Menggunakan fungsi getStageOverview
                                $overview_class = strtolower(str_replace(' ', '-', $overview));
                            ?>
                            <div class="pipeline-card" 
                                 data-id="<?php echo $card['id']; ?>" 
                                 data-card='<?php echo $safe_json_card; ?>'
                                 style="border-left-color: <?php echo $status['color']; ?>;"
                                 onclick="openModal(null, this.dataset.card)">
                                 
                                <div class="card-title"><?php echo htmlspecialchars($card['company_name']); ?></div>
                                <div class="card-details">
                                    PIC: <?php echo htmlspecialchars($card['contact_person']); ?>
                                </div>
                                <div class="card-progress progress-<?php echo $overview_class; ?>">
                                    Stage: <?php echo $overview; ?>
                                </div>
                                
                                <?php if ($card['document_path'] && $card['status'] === 'signed'): ?>
                                    <div class="file-attachment">
                                        <i class="fas fa-file-pdf"></i> Dokumen Tersedia
                                    </div>
                                <?php endif; ?>
                                <div class="card-editor">
                                    Diubah: <?php echo htmlspecialchars($card['last_editor_name'] ?? 'N/A'); ?> (<?php echo date('d M H:i', strtotime($card['updated_at'])); ?>)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="cardModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Tambah Pipeline Baru</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            
            <div id="detailView" style="display: none;">
                <div class="card-detail-view">
                    </div>
                <div class="modal-actions" style="margin-top: 30px;">
                    <button type="button" class="btn btn-danger" onclick="deleteCard()" id="deleteCardBtn">Hapus</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="switchToEditMode()">Edit</button>
                </div>
            </div>
            
            <form id="cardForm" method="POST" action="api.php" enctype="multipart/form-data" style="display: block;">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="cardId">
                <input type="hidden" name="status" id="cardStatus"> 
                <input type="hidden" name="last_edited_by_user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="created_by_user_id" id="createdUserId" value="<?php echo $user_id; ?>">
                
                <div class="form-group">
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" class="form-input" id="companyName" name="company_name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea class="form-textarea" id="address" name="address" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select id="category" name="category" class="form-select">
                        <?php foreach ($category_options as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Penanggung Jawab (PIC)</label>
                    <input type="text" class="form-input" id="contactPerson" name="contact_person" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Telepon</label>
                    <input type="text" class="form-input" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="email" name="email">
                </div>

                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea class="form-textarea" id="notes" name="notes"></textarea>
                </div>
                
                <div class="form-group" id="statusSelectGroup" style="display: none;">
                     <label class="form-label">Ubah Status</label>
                     <select id="statusSelect" class="form-select"> 
                         <option value="daily_canvasing">Daily Canvasing</option>
                         <option value="meeting">Meeting</option>
                         <option value="verbal_agree">Verbal Agree</option>
                         <option value="signed">Signed</option>
                    </select>
                </div>
                
                <div class="form-group" id="initialStatusSelectGroup">
                     <label class="form-label">Status</label>
                     <select id="initialStatusSelect" class="form-select"> 
                         <option value="daily_canvasing">Daily Canvasing</option>
                         <option value="meeting">Meeting</option>
                         <option value="verbal_agree">Verbal Agree</option>
                         <option value="signed">Signed</option>
                    </select>
                </div>

                <div class="form-group" id="fileUploadSection" style="display: none;">
                    <label class="form-label">Upload Dokumen (PDF)</label>
                    <div class="file-upload-info">Hanya aktif di status **Signed**. Dokumen lama akan diganti.</div>
                    
                    <div class="current-file" id="currentFileDisplay" style="display:none;">
                        File saat ini: 
                        <span id="currentFileName"></span> 
                        (<a href="#" id="currentFileLink" target="_blank">Lihat</a>)
                        <button type="button" class="delete-file-btn" onclick="deleteFileConfirmation()">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="file-input-container" onclick="document.getElementById('fileInput').click()">
                        <input type="file" class="file-input" id="fileInput" name="document" accept=".pdf">
                        <div class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>Klik untuk upload PDF</div>
                        </div>
                        <div class="file-name" id="fileName">Belum ada file dipilih</div>
                    </div>
                    <input type="hidden" name="existing_document_path" id="existingDocumentPath">
                </div>

                <div class="modal-actions" id="formActions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ID user dari PHP Session
        const currentUserId = <?php echo $user_id; ?>;
        const categoryOptions = <?php echo json_encode($category_options); ?>;
        
        // --- Helper Functions ---
        function confirmLogout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        function deleteFileConfirmation() {
            if (confirm('Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini akan me-reload halaman.')) {
                const cardId = document.getElementById('cardId').value;
                window.location.href = `api.php?action=delete_file&id=${cardId}`;
            }
        }
        
        function deleteCard() {
             if (confirm('Apakah Anda yakin ingin menghapus kartu ini secara permanen? Tindakan ini akan me-reload halaman.')) {
                const cardId = document.getElementById('cardId').value;
                window.location.href = `api.php?action=delete&id=${cardId}`;
            }
        }

        // Menampilkan Notifikasi Interaktif
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.className = `notif-${type}`;
            notification.querySelector('span').textContent = message;
            notification.querySelector('i').className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            notification.style.opacity = 1;
            
            setTimeout(() => {
                notification.style.opacity = 0;
            }, 3000);
        }

        // Fungsi untuk mengirim status update via AJAX dan memperbarui UI
        function updateCardStatus(cardId, newStatus, cardElement) {
            const formData = new URLSearchParams();
            formData.append('action', 'move');
            formData.append('id', cardId);
            formData.append('status', newStatus);
            formData.append('last_edited_by_user_id', currentUserId);

            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // 1. Update count
                    updateColumnCounts();
                    
                    // 2. Update Stage display pada kartu
                    const stage = newStatus === 'signed' ? 'Completed' : 'On Progress';
                    const stageClass = stage.toLowerCase().replace(' ', '-');
                    const cardProgress = cardElement.querySelector('.card-progress');
                    
                    if (cardProgress) {
                        cardProgress.textContent = `Stage: ${stage}`;
                        cardProgress.className = `card-progress progress-${stageClass}`;
                    }
                    
                    // 3. Update data-card attribute (optional, for detail view consistency without reload)
                    try {
                        let cardData = JSON.parse(cardElement.dataset.card);
                        cardData.status = newStatus;
                        // Update last edited info
                        // Anda mungkin perlu endpoint AJAX lain untuk mendapatkan data last_editor_name dan updated_at yang baru
                        // Untuk saat ini, kita hanya update status
                        cardElement.dataset.card = JSON.stringify(cardData);
                    } catch (e) {
                        console.error('Gagal parse card data setelah move:', e);
                    }

                } else {
                    showNotification(data.message, 'error');
                    // Jika gagal, disarankan reload untuk mengembalikan kartu ke posisi/kolom semula 
                    setTimeout(() => window.location.reload(), 1000); 
                }
            })
            .catch(error => {
                console.error('Error saat memindahkan kartu:', error);
                showNotification('Kesalahan koneksi saat memindahkan kartu. Coba lagi.', 'error');
                setTimeout(() => window.location.reload(), 1000); 
            });
        }
        
        // FUNGSI INI AKAN BERJALAN SAAT DRAG & DROP DAN HANYA MENGHITUNG KARTU YANG ADA
        // PERUBAHAN JUDUL DENGAN NAMA PIC HANYA DI PHP PADA LOAD AWAL
        function updateColumnCounts() {
            document.querySelectorAll('.pipeline-column').forEach(column => {
                const statusKey = column.dataset.status;
                const cardCount = column.querySelectorAll('.pipeline-card').length;
                document.getElementById(`count-${statusKey}`).textContent = cardCount;
                
                // Jika Anda ingin mengubah judul kolom di JS setelah drag/drop, 
                // Anda perlu data PIC aktif yang saat ini hanya tersedia di PHP.
                // Untuk konsistensi, pembaruan judul PIC akan mengandalkan refresh halaman penuh setelah CRUD/Move.
            });
        }
        
        // --- Modal Control Functions ---
        
        const cardModal = document.getElementById('cardModal');
        const form = document.getElementById('cardForm');
        const detailView = document.getElementById('detailView');
        const deleteBtn = document.getElementById('deleteCardBtn');
        const fileUploadSection = document.getElementById('fileUploadSection');
        const statusSelectGroup = document.getElementById('statusSelectGroup');
        const initialStatusSelectGroup = document.getElementById('initialStatusSelectGroup');
        const formActionInput = document.getElementById('formAction');
        const modalTitle = document.getElementById('modalTitle');
        const statusSelect = document.getElementById('statusSelect');
        const categorySelectForm = document.getElementById('category');
        const initialStatusSelect = document.getElementById('initialStatusSelect');
        const cardStatusHidden = document.getElementById('cardStatus'); 


        function openModal(statusKey, cardJson) {
            form.reset();
            document.getElementById('cardId').value = '';
            document.getElementById('fileName').textContent = 'Belum ada file dipilih';
            document.getElementById('fileInput').value = '';
            document.getElementById('currentFileDisplay').style.display = 'none';
            deleteBtn.style.display = 'none';
            statusSelectGroup.style.display = 'none'; 
            formActionInput.value = 'add'; 

            if (cardJson) {
                const card = JSON.parse(cardJson);
                document.getElementById('cardId').value = card.id;
                cardStatusHidden.value = card.status; 
                formActionInput.value = 'edit';
                
                document.getElementById('companyName').value = card.company_name;
                document.getElementById('address').value = card.address;
                document.getElementById('contactPerson').value = card.contact_person;
                document.getElementById('phone').value = card.phone;
                document.getElementById('email').value = card.email;
                document.getElementById('notes').value = card.notes;
                categorySelectForm.value = card.category;
                
                showDetailView(card); // Panggil untuk menampilkan detail dengan data terbaru
                deleteBtn.style.display = 'block';
                
                initialStatusSelectGroup.style.display = 'none';
                
                fileUploadSection.style.display = card.status === 'signed' ? 'block' : 'none';
                
                if (card.document_path) {
                    document.getElementById('currentFileName').textContent = card.document_path.split('/').pop();
                    document.getElementById('currentFileLink').href = card.document_path;
                    document.getElementById('currentFileDisplay').style.display = 'flex'; 
                } else {
                     document.getElementById('currentFileDisplay').style.display = 'none';
                }
                statusSelect.value = card.status;
                
            } else {
                modalTitle.textContent = 'Tambah Pipeline Baru';
                initialStatusSelectGroup.style.display = 'block';
                statusSelectGroup.style.display = 'none'; 

                cardStatusHidden.value = initialStatusSelect.value || 'daily_canvasing'; 
                
                showFormView();
                fileUploadSection.style.display = 'none'; 
                categorySelectForm.value = categoryOptions[0]; 
            }
            cardModal.classList.add('is-open');
        }

        function closeModal() {
            cardModal.classList.remove('is-open');
        }

        /**
         * Fungsi yang membuat dan menampilkan konten Detail View.
         * @param {object} card - Objek kartu dengan data terbaru.
         */
        function showDetailView(card) {
            modalTitle.textContent = `Detail: ${card.company_name}`;
            form.style.display = 'none';
            detailView.style.display = 'block';
            
            // Format updated_at. Jika data dari JS, pakai data yang ada. Jika dari PHP, format ulang.
            const dateObj = new Date(card.updated_at || Date.now());
            const updatedDate = dateObj.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            // Logika Stage/Status
            const statusUsed = card.status || cardStatusHidden.value; // Ambil status dari card atau hidden input
            const stageOverview = statusUsed === 'signed' ? 'Completed' : 'On Progress'; 
            
            const documentHtml = statusUsed === 'signed' && card.document_path
                ? `<div class="detail-item"><strong>Dokumen:</strong> <a href="${card.document_path}" target="_blank" class="detail-link"><i class="fas fa-file-pdf"></i> Lihat Dokumen</a></div>` 
                : '';
            
            const detailHtml = `
                <div class="detail-item"><strong>Stage:</strong> ${stageOverview}</div> <div class="detail-item"><strong>Status Detail:</strong> ${statusUsed.replace('_', ' ').toUpperCase()}</div>
                <div class="detail-item"><strong>Kategori:</strong> ${card.category || '-'}</div>
                <div class="detail-item"><strong>Nama Perusahaan:</strong> ${card.company_name}</div>
                <div class="detail-item"><strong>Alamat:</strong> ${card.address}</div>
                <div class="detail-item"><strong>PIC:</strong> ${card.contact_person}</div>
                <div class="detail-item"><strong>Telepon:</strong> ${card.phone}</div>
                <div class="detail-item"><strong>Email:</strong> ${card.email || '-'}</div>
                <div class="detail-item"><strong>Keterangan:</strong> ${card.notes || '-'}</div>
                ${documentHtml}
                <div class="detail-item"><strong>Terakhir Diedit:</strong> ${card.last_editor_name || 'N/A'} (${updatedDate})</div>
            `;
            detailView.querySelector('.card-detail-view').innerHTML = detailHtml;
        }

        function showFormView() {
            detailView.style.display = 'none';
            form.style.display = 'block';
        }

        function switchToEditMode() {
            modalTitle.textContent = `Edit: ${document.getElementById('companyName').value}`;
            showFormView();
            statusSelectGroup.style.display = 'block';
            initialStatusSelectGroup.style.display = 'none'; 
            
            const currentStatus = document.getElementById('statusSelect').value;
             if (currentStatus === 'signed') {
                fileUploadSection.style.display = 'block';
            } else {
                fileUploadSection.style.display = 'none';
            }
        }
        
        document.getElementById('fileInput').addEventListener('change', function() {
            const fileNameDisplay = document.getElementById('fileName');
            if (this.files.length > 0) {
                fileNameDisplay.textContent = this.files[0].name;
            } else {
                fileNameDisplay.textContent = 'Belum ada file dipilih';
            }
        });
        
        document.getElementById('statusSelect').addEventListener('change', function() {
            cardStatusHidden.value = this.value; 

            if (this.value === 'signed') {
                fileUploadSection.style.display = 'block';
            } else {
                fileUploadSection.style.display = 'none';
            }
        });
        
        initialStatusSelect.addEventListener('change', function() {
            cardStatusHidden.value = this.value;
        });

        // CardForm Listener untuk memastikan hidden status selalu terisi
        form.addEventListener('submit', function(e) {
            // Sebelum submit, pastikan cardStatusHidden memiliki nilai yang benar
            if (formActionInput.value === 'edit') {
                cardStatusHidden.value = statusSelect.value;
            } else {
                cardStatusHidden.value = initialStatusSelect.value;
            }
            // Karena form edit akan redirect/reload setelah berhasil, detail akan terupdate dari PHP.
        });

        // --- SortableJS Initialization ---
        document.addEventListener('DOMContentLoaded', () => {
            const columns = document.querySelectorAll('.pipeline-cards');
            columns.forEach(column => {
                new Sortable(column, {
                    group: 'shared', 
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: function (evt) {
                        const itemId = evt.item.dataset.id;
                        const newStatus = evt.to.closest('.pipeline-column').dataset.status;
                        const cardElement = evt.item; 
                        
                        updateCardStatus(itemId, newStatus, cardElement);
                    },
                });
            });
            
            updateColumnCounts();
            
            // Notifikasi (PHP Session Based)
            const notification = document.getElementById('notification');
            if (notification.style.opacity == 1) {
                setTimeout(() => {
                    notification.style.opacity = 0;
                }, 3000);
            }
        });
    </script>
</body>
</html>