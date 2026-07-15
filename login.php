<?php
session_start();

$error_message = '';

// Koneksi ke database
$host = 'localhost';
$dbname = 'db_partnership';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_message = "Koneksi database gagal: " . $e->getMessage();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validasi
    if (empty($email) || empty($password)) {
        $error_message = "Email dan password harus diisi!";
    } else {
        try {
            // Cek apakah email terdaftar
            $stmt = $pdo->prepare("SELECT id, full_name, email, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verifikasi password
                if (password_verify($password, $user['password'])) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect ke dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error_message = "Password salah!";
                }
            } else {
                $error_message = "Email tidak terdaftar!";
            }
        } catch(PDOException $e) {
            $error_message = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cakrawala Partnership</title>
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
        
        /* Popup Notification (Copied from dashboard.php for consistency) */
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
            border-left: 4px solid #28a745; /* Success color */
            display: none; /* Hide by default */
        }

        .popup-notification.error {
            border-left-color: #dc3545; 
        }

        .popup-notification i {
            font-size: 20px;
            color: #28a745;
        }
        
        .popup-notification.error i {
            color: #dc3545;
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
        
        .login-title-container {
            margin-bottom: 22px;
            text-align: center;
        }
        
        .login-title-main {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .login-title-sub {
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
        
        .login-btn {
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
        
        .login-btn:hover {
            background-color: #0080aa;
        }
        
        .register-link {
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #7f8c8d;
        }
        
        .register-link a {
            color: #0096C7;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
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
            
            .login-title-container {
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
            
            .login-title-main {
                font-size: 18px;
            }
            
            .login-title-sub {
                font-size: 16px;
            }
            
            .logo-container {
                width: 180px;
                height: 180px;
            }
            
            .login-title-container {
                margin-bottom: 18px;
            }
        }
    </style>
    <link rel="stylesheet" href="auth_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="popup-notification" id="statusPopup">
        <i class="fas fa-check-circle"></i>
        <span></span>
    </div>

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
            
            <div class="login-title-container">
                <div class="login-title-main">Masuk ke</div>
                <div class="login-title-sub">Cakrawala Partnership</div>
            </div>
            
            <form method="POST" action="">
                <?php if (!empty($error_message)): ?>
                    <div class='error-message'><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Masukkan email</label>
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
                
                <button type="submit" class="login-btn">Login</button>
                
                <div class="register-link">
                    Belum punya akun? <a href="register.php">Klik disini untuk daftar</a>
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

        // --- NEW: Handle Popup Notification from Redirect ---
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const statusPopup = document.getElementById('statusPopup');
            let message = '';
            let type = 'success';
            
            if (urlParams.get('success') === '1') {
                message = 'Pendaftaran berhasil! Silakan masuk dengan akun Anda.';
            } else if (urlParams.get('logout') === '1') {
                message = 'Anda telah berhasil logout.';
            }

            if (message) {
                statusPopup.querySelector('span').textContent = message;
                statusPopup.className = `popup-notification ${type}`; 
                statusPopup.style.display = 'flex'; 
                
                // Auto-hide popup notification setelah 5 detik
                setTimeout(() => {
                    statusPopup.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        if (statusPopup.parentNode) {
                            statusPopup.parentNode.removeChild(statusPopup);
                        }
                    }, 300);
                }, 5000);

                // Hapus parameter dari URL setelah ditampilkan (HTML5 History API)
                history.replaceState({}, document.title, window.location.pathname);
            }
        });
        
    </script>
</body>
</html>