<?php
/**
 * KoalaLink Pro - Unified Authentication System
 * Supports Local Auth, Google OAuth2, and Linux.do OAuth2
 */
session_start();
date_default_timezone_set('Asia/Shanghai');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = init_saas_db($db_path);
$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

// --- [ Helper Functions ] ---

function sync_user($pdo, $username, $email, $provider = 'local') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 1)");
        $stmt->execute([$username, $email, $provider . '_external']);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($u) {
        if ((int)($u['status'] ?? 1) === 0) {
            return "Your account has been blocked.";
        }
        $_SESSION['saas_user_id'] = $u['id'];
        $_SESSION['saas_username'] = $u['username'];
        $_SESSION['saas_is_admin'] = (int)($u['is_admin'] ?? 0);
        $_SESSION['saas_user_tier'] = $u['user_tier'] ?? 'free';
        return true;
    }
    return "User synchronization failed.";
}

// --- [ Action Handlers ] ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$user]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($u && password_verify($pass, $u['password'])) {
            if ((int)($u['status'] ?? 1) === 0) {
                $error = "Account blocked.";
            } else {
                $_SESSION['saas_user_id'] = $u['id'];
                $_SESSION['saas_username'] = $u['username'];
                $_SESSION['saas_is_admin'] = (int)($u['is_admin'] ?? 0);
                $_SESSION['saas_user_tier'] = $u['user_tier'] ?? 'free';
                header("Location: dashboard_pro.php");
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
    
    if ($action === 'register') {
        $user = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        
        if (strlen($user) < 3) $error = "Username too short.";
        else if (strlen($pass) < 6) $error = "Password too short.";
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$user]);
            if ($stmt->fetch()) {
                $error = "Username already exists.";
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, status) VALUES (?, ?, ?, 1)");
                $stmt->execute([$user, $email, $hashed]);
                $success = "Registration successful! Please login.";
                $action = 'login';
            }
        }
    }
}

// --- [ OAuth2 Flows ] ---

if ($action === 'login_google') {
    $url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'google'
    ]);
    header("Location: $url");
    exit;
}

if ($action === 'callback_google' && isset($_GET['code'])) {
    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'grant_type' => 'authorization_code'
    ]));
    $res = json_decode(curl_exec($ch), true);
    
    if (isset($res['access_token'])) {
        $chUser = curl_init("https://www.googleapis.com/oauth2/v3/userinfo");
        curl_setopt($chUser, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $res['access_token']]);
        curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
        $userInfo = json_decode(curl_exec($chUser), true);
        
        $username = $userInfo['name'] ?? $userInfo['given_name'] ?? explode('@', $userInfo['email'])[0];
        $resSync = sync_user($pdo, $username, $userInfo['email'], 'google');
        if ($resSync === true) {
            header("Location: dashboard_pro.php");
            exit;
        } else {
            $error = $resSync;
        }
    } else {
        $error = "Google login failed.";
    }
}

if ($action === 'login_linuxdo') {
    $url = "https://connect.linux.do/oauth2/authorize?" . http_build_query([
        'client_id' => LINUXDO_CLIENT_ID,
        'redirect_uri' => LINUXDO_REDIRECT_URL,
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'linuxdo'
    ]);
    header("Location: $url");
    exit;
}

if ($action === 'callback_linuxdo' && isset($_GET['code'])) {
    $ch = curl_init("https://connect.linux.do/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'client_id' => LINUXDO_CLIENT_ID,
        'client_secret' => LINUXDO_CLIENT_SECRET,
        'redirect_uri' => LINUXDO_REDIRECT_URL,
        'grant_type' => 'authorization_code'
    ]));
    $res = json_decode(curl_exec($ch), true);
    
    if (isset($res['access_token'])) {
        $chUser = curl_init("https://connect.linux.do/api/user");
        curl_setopt($chUser, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $res['access_token']]);
        curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
        $userInfo = json_decode(curl_exec($chUser), true);
        
        $username = $userInfo['username'] ?? $userInfo['name'] ?? '';
        $email = $userInfo['email'] ?? '';
        
        $resSync = sync_user($pdo, $username, $email, 'linuxdo');
        if ($resSync === true) {
            header("Location: dashboard_pro.php");
            exit;
        } else {
            $error = $resSync;
        }
    } else {
        $error = "Linux.do login failed.";
    }
}

if ($action === 'login_github') {
    $url = "https://github.com/login/oauth/authorize?" . http_build_query([
        'client_id' => GITHUB_CLIENT_ID,
        'redirect_uri' => GITHUB_REDIRECT_URL,
        'scope' => 'user:email',
        'state' => 'github'
    ]);
    header("Location: $url");
    exit;
}

if ($action === 'callback_github' && isset($_GET['code'])) {
    $ch = curl_init("https://github.com/login/oauth/access_token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'client_id' => GITHUB_CLIENT_ID,
        'client_secret' => GITHUB_CLIENT_SECRET,
        'redirect_uri' => GITHUB_REDIRECT_URL
    ]));
    $res = json_decode(curl_exec($ch), true);
    
    if (isset($res['access_token'])) {
        $chUser = curl_init("https://api.github.com/user");
        curl_setopt($chUser, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $res['access_token'],
            'User-Agent: KoalaLink-App'
        ]);
        curl_setopt($chUser, CURLOPT_RETURNTRANSFER, true);
        $userInfo = json_decode(curl_exec($chUser), true);
        
        // GitHub user info can be tricky, name might be null
        $username = $userInfo['login'] ?? $userInfo['name'] ?? '';
        $email = $userInfo['email'] ?? '';
        
        // If email is private, we might need to fetch it from /user/emails
        if (empty($email)) {
            $chEmail = curl_init("https://api.github.com/user/emails");
            curl_setopt($chEmail, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $res['access_token'],
                'User-Agent: KoalaLink-App'
            ]);
            curl_setopt($chEmail, CURLOPT_RETURNTRANSFER, true);
            $emails = json_decode(curl_exec($chEmail), true);
            foreach ($emails as $e) {
                if ($e['primary']) {
                    $email = $e['email'];
                    break;
                }
            }
        }
        
        $resSync = sync_user($pdo, $username, $email, 'github');
        if ($resSync === true) {
            header("Location: dashboard_pro.php");
            exit;
        } else {
            $error = $resSync;
        }
    } else {
        $error = "GitHub login failed.";
    }
}

if ($action === 'logout') {
    session_destroy();
    header("Location: index_pro.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'register' ? '注册' : '登录'; ?> - KoalaLink Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: 221, 83%, 53%;
            --primary-foreground: 210, 40%, 98%;
            --card: 0, 0%, 100%;
            --card-foreground: 222, 47%, 11%;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
        }
        .mesh-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            overflow: hidden;
            background: #f8fafc;
        }
        .mesh-ball {
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: meshMove 20s infinite alternate;
        }
        @keyframes meshMove {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(10%, 10%) rotate(40deg); }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            transition: all 0.3s ease;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        .form-control:focus {
            background: #fff;
            box-shadow: 0 0 0 4px hsla(var(--primary), 0.1);
            border-color: hsl(var(--primary));
        }
        .btn-primary-premium {
            background: hsl(var(--primary));
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px hsla(var(--primary), 0.4);
            opacity: 0.9;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0;
            color: #94a3b8;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
            margin-bottom: 12px;
        }
        .social-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
        }
        .social-btn i { font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="mesh-bg">
        <div class="mesh-ball" style="background: hsla(221, 83%, 53%, 0.4); top: -10%; left: -10%;"></div>
        <div class="mesh-ball" style="background: hsla(262, 83%, 58%, 0.4); bottom: -10%; right: -10%; animation-delay: -5s;"></div>
    </div>

    <div class="glass-card">
        <div class="text-center mb-4">
            <img src="../logo.png" alt="Logo" height="40" class="mb-3" onerror="this.style.display='none'">
            <h3 class="fw-bold"><?php echo $action === 'register' ? '创建账号' : '欢迎回来'; ?></h3>
            <p class="text-muted small"><?php echo $action === 'register' ? '加入 KoalaLink Pro 开启专业体验' : '登录以管理您的短链接'; ?></p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3"><i class="bi bi-exclamation-circle me-1"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="alert alert-success py-2 small border-0 rounded-3 mb-3"><i class="bi bi-check-circle me-1"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">用户名</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-person text-muted"></i></span>
                    <input type="text" name="username" class="form-control border-start-0 ps-0" placeholder="Username" required>
                </div>
            </div>
            <?php if($action === 'register'): ?>
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">邮箱</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="email@example.com" required>
                </div>
            </div>
            <?php endif; ?>
            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">密码</label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-premium mb-3">
                <?php echo $action === 'register' ? '注册' : '登录'; ?>
            </button>
        </form>

        <div class="text-center small">
            <?php if($action === 'register'): ?>
                已有账号? <a href="?action=login" class="text-primary text-decoration-none fw-bold">立即登录</a>
            <?php else: ?>
                还没有账号? <a href="?action=register" class="text-primary text-decoration-none fw-bold">从这里开始</a>
            <?php endif; ?>
        </div>

        <div class="divider">或者通过</div>

        <a href="?action=login_google" class="social-btn">
            <i class="bi bi-google text-danger"></i> <span>Google 登录</span>
        </a>
        <a href="?action=login_github" class="social-btn">
            <i class="bi bi-github text-dark"></i> <span>GitHub 登录</span>
        </a>
        <a href="?action=login_linuxdo" class="social-btn">
            <i class="bi bi-link-45deg text-primary"></i> <span>Linux.do 登录</span>
        </a>

        <div class="text-center mt-4 text-muted" style="font-size: 0.75rem">
            &copy; 2026 BitkoalaLab. All rights reserved.
        </div>
    </div>
</body>
</html>
