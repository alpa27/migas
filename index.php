<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

if (isLoggedIn()) {
    redirect($_SESSION['role'] === 'admin' ? BASE_URL.'/admin/dashboard.php' : BASE_URL.'/user/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['nama']          = $user['nama_lengkap'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['kode_kelompok'] = $user['kode_kelompok'];
            logActivity('LOGIN', 'Login berhasil sebagai ' . $user['role'], 'auth');
            redirect($user['role'] === 'admin' ? BASE_URL.'/admin/dashboard.php' : BASE_URL.'/user/dashboard.php');
        } else {
            // Log login gagal (tanpa session)
            try {
                $db2 = getDB();
                $ip2 = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $db2->query("INSERT INTO activity_log (username, action, description, module, ip_address) VALUES ('" . $db2->escape_string($username) . "', 'LOGIN_GAGAL', 'Percobaan login gagal', 'auth', '$ip2')");
            } catch(Throwable $e) {}
            $error = 'Username atau password salah.';
        }
    } else {
        $error = 'Mohon isi username dan password.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Sistem Monitoring Kinerja Ditjen Migas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --yellow: #F5C400;
            --yellow-dark: #D4A800;
            --black: #111111;
            --gray: #6B7280;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow: hidden;
        }

        body {
            display: flex;
            min-height: 100vh;
        }

        /* LEFT PANEL 55% KUNING */
        .left-panel {
            width: 55%;
            background: linear-gradient(145deg, #F5C400 0%, #E0A800 55%, #C89400 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .left-panel .deco {
            position: absolute;
            border-radius: 50%;
            border: 2px solid rgba(0,0,0,.07);
            pointer-events: none;
        }

        .left-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 40px 48px;
        }

        .left-logo {
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin-bottom: 28px;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,.2));
            animation: floatLogo 3.5s ease-in-out infinite;
        }
        @keyframes floatLogo {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-10px); }
        }

        .left-title {
            font-size: 34px;
            font-weight: 900;
            color: var(--black);
            line-height: 1.18;
            margin-bottom: 14px;
        }
        .left-title span { color: #fff; }

        .left-sub {
            font-size: 13px;
            color: rgba(0,0,0,.52);
            font-weight: 500;
            line-height: 1.65;
        }

        .left-footer {
            position: absolute;
            bottom: 20px;
            left: 0; right: 0;
            text-align: center;
            font-size: 10.5px;
            color: rgba(0,0,0,.32);
            font-weight: 600;
            letter-spacing: .04em;
        }

        /* RIGHT PANEL 45% PUTIH */
        .right-panel {
            width: 45%;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 52px;
            position: relative;
            flex-shrink: 0;
        }

        .right-panel::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--yellow);
        }

        .login-box {
            width: 100%;
            max-width: 380px;
        }

        .system-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FFF7CC;
            border: 1px solid var(--yellow);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 10.5px;
            font-weight: 800;
            color: #B8860B;
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .login-box h2 {
            font-size: 28px;
            font-weight: 900;
            color: var(--black);
            margin-bottom: 6px;
            line-height: 1.2;
        }

        .login-box .sub {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 32px;
            font-weight: 500;
        }

        .form-label {
            font-size: 11px;
            font-weight: 800;
            color: #374151;
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-bottom: 7px;
            display: block;
        }

        .input-wrap {
            display: flex;
            align-items: center;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            overflow: hidden;
            transition: border-color .2s, box-shadow .2s;
            background: #FAFAFA;
            margin-bottom: 18px;
        }
        .input-wrap:focus-within {
            border-color: var(--yellow);
            box-shadow: 0 0 0 3px rgba(245,196,0,.2);
            background: #fff;
        }
        .input-wrap .iico {
            width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #CBD5E1;
            font-size: 16px;
            flex-shrink: 0;
        }
        .input-wrap input {
            flex: 1;
            border: none;
            outline: none;
            padding: 12px 10px 12px 0;
            font-size: 14px;
            font-family: inherit;
            color: var(--black);
            background: transparent;
        }
        .input-wrap .btn-eye {
            width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: #CBD5E1;
            cursor: pointer;
            font-size: 16px;
            transition: color .2s;
            flex-shrink: 0;
        }
        .input-wrap .btn-eye:hover { color: var(--black); }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--yellow);
            color: var(--black);
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            margin-top: 4px;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 16px rgba(245,196,0,.4);
            letter-spacing: .01em;
        }
        .btn-login:hover {
            background: var(--yellow-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(245,196,0,.45);
        }
        .btn-login:active { transform: none; }

        .error-msg {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12.5px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .right-footer-txt {
            margin-top: 28px;
            text-align: center;
            font-size: 11.5px;
            color: #9CA3AF;
        }

        @media (max-width: 768px) {
            html, body { overflow: auto; }
            body { flex-direction: column; }
            .left-panel  { width: 100%; min-height: 280px; }
            .right-panel { width: 100%; padding: 32px 24px; }
            .right-panel::before { width: 100%; height: 4px; top: 0; bottom: auto; }
        }
    </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="deco" style="width:340px;height:340px;bottom:-110px;left:-90px;"></div>
    <div class="deco" style="width:190px;height:190px;top:-55px;right:-55px;"></div>
    <div class="deco" style="width:90px;height:90px;top:33%;left:6%;"></div>
    <div class="deco" style="width:55px;height:55px;bottom:24%;right:7%;"></div>
    <div class="deco" style="width:130px;height:130px;top:10%;left:30%;opacity:.5;"></div>

    <div class="left-content">
        <img src="<?= LOGO_URL ?>" alt="Logo Ditjen Migas" class="left-logo"
             onerror="this.style.display='none'">

        <div class="left-title">
            Sistem Monitoring<br>
            <span>Indikator Kinerja</span>
        </div>
        <div class="left-sub">
            Direktorat Jenderal Minyak dan Gas Bumi<br>
            Kementerian Energi dan Sumber Daya Mineral
        </div>
    </div>

    <div class="left-footer">© <?= date('Y') ?> Ditjen Migas — Kementerian ESDM RI</div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
    <div class="login-box">

        <div class="system-badge">
            <i class="bi bi-shield-lock-fill"></i> Internal Only
        </div>

        <h2>Selamat Datang</h2>
        <p class="sub">Masuk dengan akun Ditjen Migas Anda</p>

        <?php if ($error): ?>
        <div class="error-msg">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div>
                <label class="form-label">Username</label>
                <div class="input-wrap">
                    <div class="iico"><i class="bi bi-person-fill"></i></div>
                    <input type="text" name="username"
                           placeholder="Masukkan username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required autocomplete="username" autofocus>
                </div>
            </div>

            <div>
                <label class="form-label">Password</label>
                <div class="input-wrap">
                    <div class="iico"><i class="bi bi-lock-fill"></i></div>
                    <input type="password" name="password" id="passField"
                           placeholder="Masukkan password"
                           required autocomplete="current-password">
                    <button type="button" class="btn-eye" onclick="togglePass()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i> Masuk
            </button>
        </form>

        <div class="right-footer-txt">
            <i class="bi bi-info-circle me-1"></i>
            Hubungi admin jika mengalami kendala login
        </div>

    </div>
</div>

<script>
function togglePass() {
    const f = document.getElementById('passField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') {
        f.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        i.className = 'bi bi-eye';
    }
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>