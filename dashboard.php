<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['user_name'];

// --- START: New Database Logic for Dashboard ---
include 'config.php'; 

// BARU: Ambil daftar unik PIC untuk filter
$pic_list = [];
try {
    $stmt_pic = $pdo->prepare("SELECT DISTINCT contact_person FROM pipeline_cards WHERE contact_person IS NOT NULL AND contact_person != '' ORDER BY contact_person ASC");
    $stmt_pic->execute();
    $pic_list = $stmt_pic->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // 
}

// BARU: Ambil filter PIC dari request
$filter_pic = $_GET['picFilter'] ?? '';

// Helper untuk membangun klausa WHERE yang fleksibel
function build_where_clause($conditions) {
    if (empty($conditions)) {
        return "";
    }
    return " WHERE " . implode(" AND ", $conditions);
}

// 1. Fetch Status Counts (FILTERED BY PIC)
$status_counts = [
    'daily_canvasing' => 0,
    'meeting' => 0,
    'verbal_agree' => 0,
    'signed' => 0,
];

try {
    $conditions = [];
    $execute_params = [];
    
    // HANYA STATUS CARDS YANG DIFILTER OLEH PIC
    if (!empty($filter_pic)) {
        $conditions[] = "contact_person = ?";
        $execute_params[] = $filter_pic;
    }
    
    $where_clause_counts = build_where_clause($conditions);

    $sql_count = "SELECT status, COUNT(*) as count FROM pipeline_cards {$where_clause_counts} GROUP BY status";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute($execute_params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']] = (int)$row['count'];
        }
    }
} catch (PDOException $e) {
    //
}

// 2. Fetch Chart Data (Kemitraan Baru - New Cards per Month for last 12 months) - GLOBAL
$new_partnerships_data = [];
$last_12_months_labels = [];
$current_year = date('Y');
$current_month = date('m');

// Generate month labels and initialize data array
for ($i = 11; $i >= 0; $i--) {
    $timestamp = strtotime("-$i months");
    $month_label = date('M', $timestamp); // e.g., 'Oct'
    $month_key = date('Y-m', $timestamp); // e.g., '2024-10'
    $last_12_months_labels[] = $month_label;
    $new_partnerships_data[$month_key] = 0;
}

try {
    // Query ini MENGAMBIL TOTAL GLOBAL (Mengabaikan $filter_pic)
    $chart_conditions = ["created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)"];
    $chart_execute_params = []; 
    $chart_where_clause = build_where_clause($chart_conditions);

    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month_year, 
            COUNT(*) as count
        FROM 
            pipeline_cards
        {$chart_where_clause}
        GROUP BY 
            month_year
        ORDER BY 
            month_year ASC
    ");
    $stmt->execute($chart_execute_params);
    $monthly_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($monthly_counts as $row) {
        $key = $row['month_year'];
        if (isset($new_partnerships_data[$key])) {
            $new_partnerships_data[$key] = (int)$row['count'];
        }
    }

} catch (PDOException $e) {
    // 
}

$new_partnerships_values = array_values($new_partnerships_data); 

// 3. Fetch Chart Data (Pertumbuhan Kemitraan - Cumulative count of Signed cards) - GLOBAL
$growth_partnerships_data = [];

try {
    // Part 1: Count before 12 months (GLOBAL)
    $before_conditions = ["created_at < DATE_SUB(NOW(), INTERVAL 12 MONTH)", "status = 'signed'"];
    $before_execute_params = []; 
    $before_where_clause = build_where_clause($before_conditions);
    
    $stmt_before = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM pipeline_cards 
        {$before_where_clause}
    ");
    $stmt_before->execute($before_execute_params);
    $current_cumulative_signed = (int)$stmt_before->fetchColumn();

    // Part 2: Signed cards within 12 months (GLOBAL)
    $signed_conditions = ["created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)", "status = 'signed'"];
    $signed_execute_params = []; 
    $signed_where_clause = build_where_clause($signed_conditions);

    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month_year
        FROM 
            pipeline_cards
        {$signed_where_clause}
        ORDER BY 
            created_at ASC
    ");
    $stmt->execute($signed_execute_params);
    $signed_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate cumulative growth month by month
    foreach(array_keys($new_partnerships_data) as $month_key) {
        $count_signed_in_month = 0;
        foreach ($signed_cards as $card) {
            // Check if the card was created in the current month_key
            if ($card['month_year'] === $month_key) {
                $count_signed_in_month++;
            }
        }
        $current_cumulative_signed += $count_signed_in_month;
        $growth_partnerships_data[] = $current_cumulative_signed;
    }

} catch (PDOException $e) {
    //
}

// Prepare final PHP variables to be injected into JS
$status_counts_json = json_encode(array_values($status_counts));
$status_labels_json = json_encode(['Daily Canvasing', 'Meeting', 'Verbal Agree', 'Signed']);

$last_12_months_labels_json = json_encode($last_12_months_labels);
$new_partnerships_values_json = json_encode($new_partnerships_values);
$growth_partnerships_data_json = json_encode($growth_partnerships_data);

// --- END: New Database Logic for Dashboard ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cakrawala Partnership Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f72585;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
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

        /* Popup Notification */
        .popup-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid var(--success);
        }

        .popup-notification.success {
            border-left-color: var(--success);
        }

        .popup-notification i {
            font-size: 20px;
            color: var(--success);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            padding: 0 20px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            position: relative;
        }

        .logo-image {
            width: 75px;
            height: 75px;
            border-radius: 8px;
            margin-right: 12px;
            object-fit: contain;
            background-color: white;
            padding: 5px;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.0;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .logo .subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-top: 2px;
        }

        .nav-links {
            list-style: none;
            padding: 0 15px;
        }

        .nav-links li {
            margin-bottom: 10px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .nav-links i {
            font-size: 18px;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }

        /* Logout Button Styles */
        .logout-section {
            margin-top: auto;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .logout-btn i {
            font-size: 18px;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
        }

        /* Status Tracking Section - DIPERLEBAR */
        .status-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-top: 4px solid;
            transform: translateY(0);
            transition: all 0.3s ease;
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            display: flex;
            flex-direction: column;
            height: 140px;
            justify-content: space-between;
        }

        .status-card:nth-child(1) { animation-delay: 0.1s; }
        .status-card:nth-child(2) { animation-delay: 0.2s; }
        .status-card:nth-child(3) { animation-delay: 0.3s; }
        .status-card:nth-child(4) { animation-delay: 0.4s; }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .status-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .status-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin-right: 15px;
            transition: transform 0.3s ease;
        }

        .status-card:hover .status-icon {
            transform: scale(1.1);
        }

        .status-title {
            font-size: 16px;
            font-weight: 600;
        }

        .status-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            text-align: right;
        }

        .status-card:hover .status-value {
            color: var(--primary);
        }

        /* Charts Section */
        .charts-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            opacity: 0;
            animation: fadeIn 0.8s ease forwards;
            animation-delay: 0.5s;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
        }

        .chart-actions {
            display: flex;
            gap: 10px;
        }

        .chart-actions button {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: 5px;
        }

        .chart-actions button:hover, .chart-actions button.active {
            color: var(--primary);
            background-color: rgba(67, 97, 238, 0.1);
            font-weight: 600;
        }
        
        /* BARU: Style untuk elemen filter agar konsisten */
        .form-select-filter {
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            width: 250px; 
            cursor: pointer;
            font-size: 14px;
            height: 40px; 
            background-color: var(--light);
            color: var(--dark);
        }


        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .status-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .nav-links {
                display: flex;
                overflow-x: auto;
            }
            
            .nav-links li {
                flex: 0 0 auto;
                margin-right: 10px;
            }
            
            .nav-links a {
                white-space: nowrap;
            }
            
            .status-container {
                grid-template-columns: 1fr;
            }
            
            .logout-section {
                position: static;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="popup-notification success" id="welcomePopup">
        <i class="fas fa-check-circle"></i>
        <span>Login berhasil! Selamat datang, <?php echo htmlspecialchars($user_name); ?>!</span>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="logo">
                <img src="logo cdc .png" alt="Logo Cakrawala Partnership" class="logo-image" id="logoImage">
                <div class="logo-text">
                    <h1>Cakrawala</h1>
                    <div class="subtitle">Partnership</div>
                </div>
            </div>
         <ul class="nav-links">
    <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
    <li><a href="pipeline_tracking.php"><i class="fas fa-chart-bar"></i> Pipeline Tracking</a></li>
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
                <h2>Dashboard Cakrawala Partnership</h2>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>

            <form method="GET" action="dashboard.php" style="margin-bottom: 30px; display: flex; align-items: center; gap: 15px;">
                <label for="picFilter" style="font-weight: 600; color: var(--dark);">Status Berdasarkan PIC:</label>
                <select id="picFilter" name="picFilter" onchange="this.form.submit()" class="form-select-filter">
                    <option value="">Total Keseluruhan</option>
                    <?php foreach ($pic_list as $pic): ?>
                        <option value="<?php echo htmlspecialchars($pic); ?>" <?php echo $filter_pic == $pic ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pic); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="status-container">
                <div class="status-card" style="border-color: #ff9f43;">
                    <div class="status-header">
                        <div class="status-icon" style="background-color: #ff9f43;">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="status-title">Daily Canvasing</div>
                    </div>
                    <div class="status-value"><?php echo $status_counts['daily_canvasing']; ?></div>
                </div>

                <div class="status-card" style="border-color: #54a0ff;">
                    <div class="status-header">
                        <div class="status-icon" style="background-color: #54a0ff;">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="status-title">Meeting</div>
                    </div>
                    <div class="status-value"><?php echo $status_counts['meeting']; ?></div>
                </div>

                <div class="status-card" style="border-color: #5f27cd;">
                    <div class="status-header">
                        <div class="status-icon" style="background-color: #5f27cd;">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="status-title">Verbal Agree</div>
                    </div>
                    <div class="status-value"><?php echo $status_counts['verbal_agree']; ?></div>
                </div>

                <div class="status-card" style="border-color: #1dd1a1;">
                    <div class="status-header">
                        <div class="status-icon" style="background-color: #1dd1a1;">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="status-title">Signed</div>
                    </div>
                    <div class="status-value"><?php echo $status_counts['signed']; ?></div>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Partnership Progress </div>
                        <div class="chart-actions">
                            <button class="active">1 Tahun</button>
                            <button>6 Bulan</button>
                            <button>3 Bulan</button>
                        </div>
                    </div>
                    <canvas id="performanceChart"></canvas>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Status Distribution </div>
                    </div>
                    <canvas id="pipelineChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide popup notification setelah 5 detik
        setTimeout(() => {
            const popup = document.getElementById('welcomePopup');
            if (popup) {
                popup.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 300);
            }
        }, 5000);

        // Confirm logout function
function confirmLogout() {
    if (confirm('Apakah Anda yakin ingin logout?')) {
        window.location.href = 'logout.php';
    }
}

        // Data dari PHP
        const months = <?php echo $last_12_months_labels_json; ?>;
        const partnershipsNew = <?php echo $new_partnerships_values_json; ?>;
        const partnershipsGrowth = <?php echo $growth_partnerships_data_json; ?>;
        const statusLabels = <?php echo $status_labels_json; ?>;
        const statusCounts = <?php echo $status_counts_json; ?>;
        
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Kemitraan Baru',
                    data: partnershipsNew,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Pertumbuhan Kemitraan',
                    data: partnershipsGrowth,
                    borderColor: '#f72585',
                    backgroundColor: 'rgba(247, 37, 133, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah Kemitraan'
                        }
                    }
                }
            }
        });

        // Pipeline Chart
        const pipelineCtx = document.getElementById('pipelineChart').getContext('2d');
        const pipelineChart = new Chart(pipelineCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: [
                        '#ff9f43',
                        '#54a0ff',
                        '#5f27cd',
                        '#1dd1a1'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Update data slicing logic for 6-month and 3-month buttons in JS
        document.querySelectorAll('.chart-actions button').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.chart-actions button').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update chart data based on selected timeframe
                if (this.textContent === '1 Tahun') {
                    performanceChart.data.labels = months;
                    performanceChart.data.datasets[0].data = partnershipsNew;
                    performanceChart.data.datasets[1].data = partnershipsGrowth;
                } else if (this.textContent === '6 Bulan') {
                    const start_index = months.length - 6;
                    performanceChart.data.labels = months.slice(start_index);
                    performanceChart.data.datasets[0].data = partnershipsNew.slice(start_index);
                    performanceChart.data.datasets[1].data = partnershipsGrowth.slice(start_index);
                } else if (this.textContent === '3 Bulan') {
                    const start_index = months.length - 3;
                    performanceChart.data.labels = months.slice(start_index);
                    performanceChart.data.datasets[0].data = partnershipsNew.slice(start_index);
                    performanceChart.data.datasets[1].data = partnershipsGrowth.slice(start_index);
                }
                
                performanceChart.update();
            });
        });

        // Fungsi untuk menambahkan logo
        function setLogo(imageUrl) {
            const logoImage = document.getElementById('logoImage');
            if (imageUrl) {
                logoImage.src = imageUrl;
            }
        }

        // Contoh penggunaan: setLogo('path/to/your/logo.png');
        // Untuk mencoba, Anda bisa uncomment baris di bawah ini dan ganti URL dengan path logo Anda
        // setLogo('https://via.placeholder.com/40x40/ffffff/4361ee?text=CP');
    </script>
</body>
</html>