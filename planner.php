<?php
// File: project uas_patnership/planner.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'] ?? 0;

include 'config.php'; 

// Fetch HANYA events yang dibuat oleh pengguna yang sedang login
$events = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            pe.id, 
            pe.title, 
            pe.description, 
            pe.event_date, 
            u.full_name as created_by_name
        FROM 
            planner_events pe
        LEFT JOIN 
            users u ON pe.created_by_user_id = u.id
        WHERE 
            pe.created_by_user_id = ? -- INI PEMBATASAN AKSES PRIBADI
        ORDER BY 
            pe.event_date ASC, pe.created_at ASC
    ");
    $stmt->execute([$user_id]); // Eksekusi dengan ID pengguna
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handling: Jika tabel belum dibuat, ini mungkin gagal
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planner - Cakrawala Partnership</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />

    <style>
        /* CSS DARI PIPELINE_TRACKING.PHP UNTUK KONSISTENSI DESAIN */
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

        /* Sidebar Styles */
        .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), var(--secondary)); color: white; padding: 20px 0; box-shadow: 0 0 15px rgba(0, 0, 0, 0.1); z-index: 100; flex-shrink: 0; }
        .logo { display: flex; align-items: center; padding: 0 20px 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 20px; }
        .logo-image { 
            width: 75px; 
            height: 75px; 
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
        .main-content { flex: 1; padding: 25px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h2 { font-size: 24px; font-weight: 600; color: var(--dark); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .user-name { font-weight: 600; color: var(--dark); }
        
        /* Modal Styles (Consistent with pipeline_tracking) */
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
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-size: 14px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--secondary); }
        .btn-secondary { background: var(--light-gray); color: var(--dark); }
        .btn-secondary:hover { background: #dee2e6; }
        .btn-danger { background: #dc3545; color: white; display: none; margin-right: auto;}
        .btn-danger:hover { background: #c82333; }
        
        /* Custom Planner Styles */
        .planner-grid {
            display: grid;
            grid-template-columns: 3fr 1fr; /* Calendar dan List View */
            gap: 25px;
        }
        
        #calendar {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .event-list-container {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            max-height: 700px;
            overflow-y: auto;
        }
        
        .event-list-container h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 18px;
        }

        .event-card {
            background: var(--light);
            border-left: 4px solid var(--primary);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .event-card:hover {
            background: #e2e6f8;
            border-left-color: var(--secondary);
        }

        .event-title {
            font-weight: 600;
            font-size: 15px;
            color: var(--dark);
        }

        .event-date {
            font-size: 12px;
            color: var(--gray);
            margin-top: 3px;
        }
        
        /* FullCalendar Customization */
        .fc-event {
            background-color: var(--primary) !important;
            border-color: var(--secondary) !important;
            font-size: 12px;
            padding: 2px 5px;
            border-radius: 4px;
        }

        .fc-event-title {
            white-space: normal; 
        }

        /* Responsive */
        @media (max-width: 992px) {
            .planner-grid {
                grid-template-columns: 1fr;
            }
            .event-list-container {
                max-height: 400px;
            }
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
                <li><a href="reports.php"><i class="fas fa-search-location"></i> Reports</a></li>
                <li><a href="planner.php" class="active"><i class="fas fa-calendar-alt"></i> Planner</a></li> 
            </ul>
            
            <div class="logout-section">
                <button class="logout-btn" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>

        <div class="main-content">
            <div class="header">
                <h2>Planner Kemitraan Pribadi</h2>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>
            
            <div class="planner-grid">
                <div id='calendar'></div>
                
                <div class="event-list-container">
                    <h3><i class="fas fa-tasks"></i> Jadwal Anda (<?php echo count($events); ?>)</h3>
                    <button class="btn btn-primary" style="width: 100%; margin-bottom: 15px;" onclick="openModal('add', null)">
                        <i class="fas fa-plus"></i> Buat Jadwal Baru
                    </button>
                    <div id="eventList">
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                            <?php 
                                // Format data untuk JS
                                $event_json = json_encode([
                                    'id' => $event['id'],
                                    'title' => $event['title'],
                                    'description' => $event['description'],
                                    'start' => $event['event_date'],
                                    'createdBy' => $event['created_by_name']
                                ]);
                            ?>
                            <div class="event-card" onclick="openModal('edit', <?php echo htmlspecialchars($event_json, ENT_QUOTES, 'UTF-8'); ?>)">
                                <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                <div class="event-date">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('d M Y', strtotime($event['event_date'])); ?> 
                                    (Oleh: <?php echo htmlspecialchars($event['created_by_name']); ?>)
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray); padding: 20px;">Belum ada jadwal pribadi yang dibuat.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Buat Jadwal Baru</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="eventForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="eventId">
                <input type="hidden" name="created_by_user_id" value="<?php echo $user_id; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="eventTitle">Judul/Topik Jadwal</label>
                    <input type="text" class="form-input" id="eventTitle" name="title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="eventDate">Tanggal Pelaksanaan</label>
                    <input type="date" class="form-input" id="eventDate" name="event_date" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="eventDescription">Keterangan/Detail</label>
                    <textarea class="form-textarea" id="eventDescription" name="description"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-danger" id="deleteEventBtn" onclick="deleteEvent()">Hapus</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script>
        // Data Events dari PHP untuk FullCalendar (Hanya event milik user)
        const rawEvents = <?php echo json_encode($events); ?>.map(event => ({
            id: event.id,
            title: event.title,
            start: event.event_date,
            description: event.description,
            createdBy: event.created_by_name
        }));
        
        // --- Modal Control Functions ---
        const eventModal = document.getElementById('eventModal');
        const form = document.getElementById('eventForm');
        const modalTitle = document.getElementById('modalTitle');
        const deleteBtn = document.getElementById('deleteEventBtn');
        const submitBtn = document.getElementById('submitBtn');

        function openModal(mode, eventData = null, selectedDate = null) {
            form.reset();
            deleteBtn.style.display = 'none';

            if (mode === 'add') {
                modalTitle.textContent = 'Buat Jadwal Baru';
                document.getElementById('formAction').value = 'add';
                document.getElementById('eventId').value = '';
                submitBtn.textContent = 'Simpan Jadwal';

                if (selectedDate) {
                    document.getElementById('eventDate').value = selectedDate;
                } else {
                    // Set default date ke 2025-01-01 agar konsisten dengan range kalender
                    document.getElementById('eventDate').value = '2025-01-01'; 
                }

            } else if (mode === 'edit' && eventData) {
                modalTitle.textContent = 'Edit Jadwal: ' + eventData.title;
                document.getElementById('formAction').value = 'edit';
                document.getElementById('eventId').value = eventData.id;
                document.getElementById('eventTitle').value = eventData.title;
                document.getElementById('eventDate').value = eventData.start; 
                document.getElementById('eventDescription').value = eventData.description || '';
                submitBtn.textContent = 'Simpan Perubahan';
                deleteBtn.style.display = 'block';
            }
            eventModal.classList.add('is-open');
        }

        function closeModal() {
            eventModal.classList.remove('is-open');
        }

        function confirmLogout() {
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // --- Calendar & CRUD Logic ---
        
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');

            // Tanggal Awal Disesuaikan untuk tahun 2025
            const initialDate = new Date();
            initialDate.setFullYear(2025);
            initialDate.setMonth(0);
            initialDate.setDate(1);

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialDate: initialDate,
                locale: 'id', // Set bahasa ke Indonesia
                
                // REVISI DESAIN: Hapus tombol view (Week, Month, List) di kanan
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth' // Pertahankan hanya view Bulan default
                },
                
                events: rawEvents,
                editable: false, 
                selectable: true, 
                select: function(info) {
                    // Tangkap tanggal yang dipilih dan buka modal Add
                    openModal('add', null, info.startStr);
                    calendar.unselect(); // Bersihkan seleksi
                },
                eventClick: function(info) {
                    // Klik event untuk Edit/Detail
                    const event = info.event;
                    const eventData = {
                        id: event.id,
                        title: event.title,
                        description: event.extendedProps.description,
                        start: event.startStr 
                    };
                    openModal('edit', eventData);
                },
                // Set rentang tahun 2025-2026
                validRange: {
                    start: '2025-01-01',
                    end: '2027-01-01' // Hingga akhir 2026
                }
            });

            calendar.render();
            
            // ----------------------------------------------------
            // 5. Submit Form (Tambah/Edit) via AJAX ke planner_api.php
            // ----------------------------------------------------
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const action = formData.get('action');
                
                fetch('planner_api.php', {
                    method: 'POST',
                    body: formData 
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(action === 'add' ? 'Jadwal berhasil ditambahkan!' : 'Jadwal berhasil diperbarui!');
                        closeModal();
                        window.location.reload(); 
                    } else {
                        alert(`Gagal menyimpan: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan koneksi saat menyimpan jadwal.');
                });
            });
            
            // 6. Delete Event via AJAX
            window.deleteEvent = function() {
                const eventId = document.getElementById('eventId').value;
                if (!confirm(`Apakah Anda yakin ingin menghapus jadwal ini?`)) return;
                
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', eventId);
                
                fetch('planner_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Jadwal berhasil dihapus!');
                        closeModal();
                        window.location.reload(); 
                    } else {
                        alert(`Gagal menghapus: ${data.message}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan koneksi saat menghapus jadwal.');
                });
            }

        });
    </script>
</body>
</html>