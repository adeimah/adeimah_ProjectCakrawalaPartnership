<?php
// File: project uas_patnership/register.php
session_start();
include 'config.php'; // Menggunakan file config untuk koneksi database

$error_message = null; // Contoh variabel untuk pesan error
$success_message = null; // Contoh variabel untuk pesan sukses

// Proses Pendaftaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validasi Input
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Semua kolom wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid!";
    } elseif ($password !== $confirm_password) {
        $error_message = "Password dan Konfirmasi Password tidak cocok!";
    } else {
        try {
            // 2. Cek apakah email sudah terdaftar
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Email ini sudah terdaftar. Silakan gunakan email lain.";
            } else {
                // 3. Hash Password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 4. Masukkan data ke database (asumsi nama tabel: users, kolom: full_name, email, password)
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$full_name, $email, $hashed_password])) {
                    // 5. Redirect ke login.php setelah berhasil mendaftar
                    // Menambahkan parameter success=1 untuk notifikasi di halaman login
                    header("Location: login.php?success=1"); 
                    exit();
                } else {
                    $error_message = "Pendaftaran gagal. Silakan coba lagi.";
                }
            }
        } catch(PDOException $e) {
            // Menampilkan pesan error database jika koneksi atau query bermasalah
            $error_message = "Terjadi kesalahan database. Pastikan tabel 'users' ada dan koneksi ke database berjalan. (" . $e->getMessage() . ")";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Cakrawala Partnership</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 850px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: flex;
            min-height: 580px;
        }
        
        .left-panel {
            flex: 1;
            background: linear-gradient(135deg, #0096C7, #00A896);
            color: white;
            padding: 35px 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        
        .welcome-content {
            max-width: 380px;
        }
        
        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 18px;
            line-height: 1.2;
        }
        
        .welcome-description {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 25px;
            opacity: 0.95;
        }
        
        .divider {
            height: 2px;
            background-color: rgba(255, 255, 255, 0.3);
            margin: 20px 0;
            width: 80px;
        }
        
        .person-illustration {
            margin-top: 25px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .person-image {
            width: 360px;
            height: 280px;
            object-fit: cover;
        }
        
        .right-panel {
            flex: 1;
            padding: 35px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo-section {
            margin-bottom: -5px;
            text-align: center;
        }
        
        .logo-container {
            width: 220px;
            height: 220px;
            margin: 0 auto -8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .register-title-container {
            margin-bottom: 22px;
            text-align: center;
        }
        
        .register-title-main {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .register-title-sub {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .input-container {
            position: relative;
        }
        
        /* CSS INPUT KONSISTENSI */
        .input-container input {
            width: 100%;
            padding: 9px 14px; 
            height: 40px; 
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }
        
        .input-container input:focus {
            outline: none;
            border-color: #0096C7;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(0, 150, 199, 0.1);
        }
        
        .input-container input::placeholder {
            color: #a0a0a0;
        }
        
        .show-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #0096C7;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
        }
        
        .register-btn {
            width: 100%;
            padding: 13px;
            background-color: #0096C7;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 8px;
        }
        
        .register-btn:hover {
            background-color: #0080aa;
        }
        
        .login-link {
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .login-link a {
            color: #0096C7;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 8px;
            text-align: center;
            padding: 9px;
            background-color: #fdf2f2;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            color: #27ae60;
            font-size: 12px;
            margin-top: 8px;
            text-align: center;
            padding: 9px;
            background-color: #f2fdf2;
            border-radius: 6px;
            border: 1px solid #c6f5c6;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .left-panel, .right-panel {
                padding: 30px 25px;
            }
            
            .person-image {
                width: 320px;
                height: 250px;
            }
            
            .welcome-title {
                font-size: 26px;
            }
            
            .logo-container {
                width: 200px;
                height: 200px;
            }
            
            .register-title-container {
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .left-panel, .right-panel {
                padding: 25px 20px;
            }
            
            .person-image {
                width: 280px;
                height: 220px;
            }
            
            .welcome-title {
                font-size: 24px;
            }
            
            .register-title-main {
                font-size: 18px;
            }
            
            .register-title-sub {
                font-size: 16px;
            }
            
            .logo-container {
                width: 180px;
                height: 180px;
            }
            
            .register-title-container {
                margin-bottom: 18px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="welcome-content">
                <h1 class="welcome-title">Selamat Datang di Portal Partnership</h1>
                <p class="welcome-description">Wadah kolaborasi antara kampus dan dunia industri login untuk mengakses data kemitraan, kegiatan pengembangan karier dan kerja sama strategis</p>
                <div class="divider"></div>
            </div>
            
            <div class="person-illustration">
                <img src="gambar_org.png" alt="Person Illustration" class="person-image">
            </div>
        </div>
        
        <div class="right-panel">
            <div class="logo-section">
                <div class="logo-container">
                    <img src="logo_cdc.png" alt="Cakrawala Partnership" id="custom-logo">
                </div>
            </div>
            
            <div class="register-title-container">
                <div class="register-title-main">Daftar ke</div>
                <div class="register-title-sub">Cakrawala Partnership</div>
            </div>
            
            <?php if(!empty($error_message)): ?>
                <div class='error-message'>
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success_message)): ?>
                <div class='success-message'>
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="full_name">Nama Lengkap</label>
                    <div class="input-container">
                        <input type="text" id="full_name" name="full_name" placeholder="Masukkan nama lengkap" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-container">
                        <input type="email" id="email" name="email" placeholder="example@gmail.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" required>
                        <button type="button" class="show-password" id="showPassword">Show</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <div class="input-container">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Konfirmasi kata sandi" required>
                        <button type="button" class="show-password" id="showConfirmPassword">Show</button>
                    </div>
                </div>
                
                <button type="submit" class="register-btn">Daftar</button>
                
                <div class="login-link">
                    Sudah punya akun? <a href="login.php">Klik disini untuk masuk</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle tampilan password
        document.getElementById('showPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                this.textContent = 'Show';
            }
        });
        
        // Toggle tampilan konfirmasi password
        document.getElementById('showConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                this.textContent = 'Hide';
            } else {
                confirmPasswordInput.type = 'password';
                this.textContent = 'Show';
            }
        });

        // Validasi form (Client-side, Server-side validation tetap diperlukan)
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                // Biarkan server-side PHP yang menangani pencegahan submit jika tidak cocok
                // Untuk client side, kita bisa biarkan agar error message dari PHP muncul
            }
        });

        // Function untuk menampilkan popup
        function showPopup(message, type = 'success') {
            const popup = document.createElement('div');
            popup.className = `popup-notification ${type}`;
            popup.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(popup);
            
            // Hapus popup setelah 3 detik
            setTimeout(() => {
                popup.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (popup.parentNode) {
                        popup.parentNode.removeChild(popup);
                    }
                }, 300);
            }, 3000);
        }

        // Cek jika ada parameter success di URL (Fungsi ini tidak akan terpicu jika redirect sukses)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            showPopup('Pendaftaran berhasil!', 'success');
        }
    </script>
</body>
</html>