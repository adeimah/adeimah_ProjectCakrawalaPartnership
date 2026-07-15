<?php
// File: project uas_patnership/reports.php (FINAL MODIFICATION FOR EXCEL STRUCTURE AND COLUMN MATCH)
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
$user_name = $_SESSION['user_name'];
include 'config.php'; 

/**
 * Helper function to determine the Stage based on status.
 */
function getStageOverview($status) {
    // Normalisasi status untuk stage
    $status = strtolower(str_replace(' ', '_', $status));
    if ($status === 'signed') {
        return 'Completed';
    }
    return 'On Progress';
}

// --- FILTER & SEARCH LOGIC ---
$search_query = $_GET['searchCompany'] ?? '';
$filter_category = $_GET['categoryFilter'] ?? '';
$action = $_GET['action'] ?? '';

// REVISI: Mengganti opsi limit data dengan opsi filter waktu
$limit_options = ['all' => 'Semua Data', '1y' => '1 Tahun Terakhir', '6m' => '6 Bulan Terakhir', '3m' => '3 Bulan Terakhir'];
$limit_data = $_GET['limitData'] ?? 'all'; 
// Validasi $limit_data
if (!isset($limit_options[$limit_data])) { 
    $limit_data = 'all'; 
}

$where_clauses = [];
$execute_params = [];

if (!empty($search_query)) {
    // Menggunakan LIKE untuk pencarian Nama Perusahaan
    $where_clauses[] = "pc.company_name LIKE ?";
    $execute_params[] = '%' . $search_query . '%';
}

if (!empty($filter_category)) {
    $where_clauses[] = "pc.category = ?";
    $execute_params[] = $filter_category;
}

// BARU: Logika untuk Filter Waktu
if ($limit_data !== 'all') {
    $date_filter_clause = '';
    switch ($limit_data) {
        case '1y':
            $date_filter_clause = "pc.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case '6m':
            $date_filter_clause = "pc.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case '3m':
            $date_filter_clause = "pc.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
    }
    if (!empty($date_filter_clause)) {
        $where_clauses[] = $date_filter_clause;
    }
}

$where_clause = '';
if (!empty($where_clauses)) {
    $where_clause = "WHERE " . implode(" AND ", $where_clauses);
}

// Hapus logika LIMIT baris ($limit_clause) karena sudah diganti filter tanggal

// Opsi Kategori (Sama dengan pipeline_tracking.php)
$category_options = ['Local', 'International School', 'Oversize'];

$all_partnerships = [];
try {
    // Fetch data DENGAN FILTER WAKTU yang ditentukan
    $stmt = $pdo->prepare("
        SELECT 
            pc.*, 
            u.full_name as last_editor_name,
            uc.full_name as creator_name
        FROM 
            pipeline_cards pc
        LEFT JOIN 
            users u ON pc.last_edited_by_user_id = u.id
        LEFT JOIN
            users uc ON pc.created_by_user_id = uc.id
        {$where_clause}
        ORDER BY 
            pc.created_at DESC 
    ");
    $stmt->execute($execute_params);
    $all_partnerships = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handling
}


// --- EXPORT LOGIC ---
if ($action === 'export_csv' || $action === 'export_xls') {
    // Logika ekspor menggunakan data yang sudah difetch (dan sudah difilter)
    $filename = "Laporan_Kemitraan_" . date('Ymd_His');
    
    // DELIMITER REVISION: Use semicolon for robust CSV and tab for XLS
    $delimiter = ($action === 'export_csv') ? ';' : "\t"; 
    $extension = ($action === 'export_csv') ? 'csv' : 'xls';

    // Headers untuk download
    if ($action === 'export_xls') {
         header('Content-Type: application/vnd.ms-excel; charset=utf-8'); 
    } else {
         header('Content-Type: text/csv; charset=utf-8'); 
    }

    header('Content-Disposition: attachment; filename="' . $filename . '.' . $extension . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // START: BOM IMPLEMENTATION (Crucial for UTF-8 in Excel)
    if ($action === 'export_csv') {
        // Menulis BOM untuk memaksa Excel mengenali encoding UTF-8
        fwrite($output, "\xEF\xBB\xBF");
    }
    // END: BOM IMPLEMENTATION
    
    // Header Kolom untuk Export (MATCHING DISPLAYED COLUMNS: 6 Kolom)
    $header = ['No', 'Nama Perusahaan', 'Kategori', 'Status', 'Stage', 'PIC'];
    
    // Menggunakan fputcsv yang akan menangani tab/semicolon delimiter
    fputcsv($output, $header, $delimiter);
    
    // Data Baris
    $no = 1;
    foreach ($all_partnerships as $partnership) {
        $status_label = ucwords(str_replace('_', ' ', $partnership['status']));
        $stage = getStageOverview($partnership['status']);

        // Data Row (Hanya 6 Kolom yang Diekspor)
        $row = [
            $no++,
            $partnership['company_name'],
            $partnership['category'],
            $partnership['status'], // Menggunakan status asli untuk konsistensi
            $stage,
            $partnership['contact_person'], // PIC (Contact Person)
        ];
        
        fputcsv($output, $row, $delimiter);
    }
    
    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Cakrawala Partnership</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... (CSS ASLI TETAP SAMA) ... */
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #1dd1a1; 
            --warning: #f72585;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        /* Style Body, Container, Sidebar, Main-Content, Header, User-Info */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fb; color: var(--dark); line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), var(--secondary)); color: white; padding: 20px 0; box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); z-index: 100; }
        .logo { display: flex; align-items: center; padding: 0 20px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 20px; }
        .logo-image { width: 75px; height: 75px; border-radius: 8px; margin-right: 12px; object-fit: contain; background-color: white; padding: 5px; }
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
        .main-content { flex: 1; padding: 25px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h2 { font-size: 24px; font-weight: 600; color: var(--dark); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .user-name { font-weight: 600; color: var(--dark); }
        
        /* Table Styles */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th, .reports-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 14px;
        }
        
        .reports-table th {
            background-color: var(--primary);
            color: white;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .reports-table tr:hover {
            background-color: #f6f6f6;
        }
        
        .status-signed {
            font-weight: 600;
            color: #1dd1a1;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--gray);
            font-style: italic;
        }

        /* Filter & Export Button Style */
        .form-input-search, .form-select-filter {
            padding: 8px 18px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            width: 180px; 
            cursor: pointer;
            font-size: 14px;
            height: 40px; 
        }

        .form-input-search {
            width: 300px; /* Diperbesar agar cukup */
            padding-left: 35px; 
            background: var(--light) url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="%236c757d" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>') no-repeat 10px center;
            background-size: 18px 18px;
        }
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
                <li><a href="pipeline_tracking.php"><i class="fas fa-chart-bar"></i> Pipeline Tracking</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-search-location"></i> Reports</a></li>
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
                <h2>Partnership Reports</h2>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>

            <div class="action-bar" style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <form method="GET" action="reports.php" style="display:flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="searchCompany" placeholder="Cari Nama Perusahaan..." class="form-input-search" value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <select id="categoryFilter" name="categoryFilter" class="form-select-filter">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($category_options as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category == $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="limitData" name="limitData" class="form-select-filter">
                        <?php foreach ($limit_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $limit_data == $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 15px; height: 40px; border: none; border-radius: 8px; background: var(--primary); color: white; cursor: pointer;">Terapkan Filter</button>
                    <a href="reports.php" class="btn btn-secondary" style="padding: 8px 15px; height: 40px; line-height: 24px; border-radius: 8px; background: var(--light-gray); color: var(--dark); text-decoration: none;">Reset</a>
                </form>

                <div class="export-buttons" style="display:flex; gap: 10px;">
                    <?php 
                        $export_params = http_build_query([
                            'searchCompany' => $search_query,
                            'categoryFilter' => $filter_category,
                            'limitData' => $limit_data // <-- Menggunakan filter waktu
                        ]);
                    ?>
                    <a href="reports.php?action=export_csv&<?php echo $export_params; ?>" class="btn" style="background: #28a745; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none;"><i class="fas fa-file-csv"></i> Export CSV</a>
                    <a href="reports.php?action=export_xls&<?php echo $export_params; ?>" class="btn" style="background: #17a2b8; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none;"><i class="fas fa-file-excel"></i> Export XLS</a>
                </div>
            </div>

            <div class="table-container">
                <?php if (count($all_partnerships) > 0): ?>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Perusahaan</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th>PIC</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($all_partnerships as $partnership): ?>
                        <?php 
                            $status_label = ucwords(str_replace('_', ' ', $partnership['status']));
                            $stage = getStageOverview($partnership['status']);
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($partnership['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($partnership['category']); ?></td>
                            <td><?php echo $status_label; ?></td>
                            <td><span style="font-weight: 600; color: <?php echo $stage === 'Completed' ? '#1dd1a1' : '#4361ee'; ?>;"><?php echo $stage; ?></span></td>
                            <td><?php echo htmlspecialchars($partnership['contact_person']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="no-data">Tidak ada data kemitraan yang ditemukan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmLogout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>