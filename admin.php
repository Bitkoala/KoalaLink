<?php
/**
 * KoalaLink Lite Admin Dashboard
 * 功能：链接管理 (CRUD)、简单的登录验证、数据概览
 */
session_start();
date_default_timezone_set('Asia/Shanghai');
$db_path = __DIR__ . '/data/redirect.db';

// --- [ Database Helper ] ---
if (!function_exists('init_db')) {
    function init_db($db_path) {
        if (!file_exists(dirname($db_path))) {
            mkdir(dirname($db_path), 0755, true);
        }
        try {
            $pdo = new PDO("sqlite:$db_path");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create Tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS links (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, target_url TEXT NOT NULL, status INTEGER DEFAULT 1, password TEXT, max_clicks INTEGER DEFAULT 0, expire_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS stats (id INTEGER PRIMARY KEY AUTOINCREMENT, link_id INTEGER, target_url TEXT, click_time DATETIME DEFAULT CURRENT_TIMESTAMP, referer TEXT, ip_address TEXT, user_agent TEXT)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS options (key TEXT PRIMARY KEY, value TEXT)");
            
            // Migrations
            $res = $pdo->query("PRAGMA table_info(stats)")->fetchAll(PDO::FETCH_ASSOC);
            $has_target_url = false;
            foreach ($res as $col) { if ($col['name'] === 'target_url') { $has_target_url = true; break; } }
            if (!$has_target_url) $pdo->exec("ALTER TABLE stats ADD COLUMN target_url TEXT");

            // Seed Defaults
            $pdo->exec("INSERT OR IGNORE INTO options (key, value) VALUES ('admin_password', 'admin')");
            $pdo->exec("INSERT OR IGNORE INTO options (key, value) VALUES ('allowed_referers', '')");
            $pdo->exec("INSERT OR IGNORE INTO options (key, value) VALUES ('whitelist', 'bitekaola.com,bitkoala.net,github.com')");

            return $pdo;
        } catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
    }
}

if (!function_exists('get_option')) {
    function get_option($pdo, $key, $default = '') {
        $stmt = $pdo->prepare("SELECT value FROM options WHERE key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }
}

// --- [ 配置与安全 ] ---
// $admin_password = 'admin'; // 数据库驱动后此行作废
define('IS_LOGGED_IN', isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

$pdo = init_db($db_path);

// --- [ 1. 语言检测 (i18n) ] ---
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'zh' => [
        'login_title' => '登录 - KoalaLink Lite Admin',
        'brand_lite' => 'KoalaLink 轻量版',
        'admin_pwd' => '管理员密码',
        'btn_login' => '登录',
        'pwd_error' => '密码错误',
        'nav_links' => '外链管理',
        'nav_settings' => '全局设置',
        'nav_stats' => '统计概览',
        'nav_logout' => '退出',
        'nav_title' => '管理后台',
        'msg_deleted' => '链接已删除',
        'msg_updated' => '状态已更新',
        'msg_added' => '新链接已创建',
        'msg_add_failed' => '创建失败',
        'msg_saved' => '配置已保存',
        'card_add_title' => '创建新跳转',
        'form_slug' => '标识 (Slug)',
        'form_slug_ph' => '留空则随机生成',
        'form_target' => '目标 URL',
        'form_quick' => '快速生成 (免创建)',
        'form_quick_enc' => '加密',
        'form_quick_dir' => '直接',
        'form_quick_copy' => '复制',
        'form_pwd' => '访问密码 (可选)',
        'form_max_clicks' => '最大点击量 (0为不限)',
        'form_expire' => '过期时间 (可选)',
        'btn_create' => '创建外链',
        'card_list_title' => '现有外链',
        'table_slug' => '标识',
        'table_target' => '目标 URL / 统计',
        'table_status' => '状态',
        'table_actions' => '操作',
        'status_on' => '启用',
        'status_off' => '禁用',
        'table_empty' => '暂无数据',
        'confirm_delete' => '确定删除?',
        'set_title' => '全局安全设置',
        'set_referer_label' => 'Referer 白名单',
        'set_referer_desc' => '通过 Referer 限制访问来源，多个域名请用英文逗号 (,) 分开。留空则允许任何来源访问。',
        'set_whitelist_label' => '中转页信任域名白名单',
        'set_whitelist_desc' => '包含在此名单中的域名在中转页会显示“官方推荐”绿标，否则显示“安全警示”。用逗号分开。',
        'set_pwd_label' => '修改管理员密码',
        'set_pwd_ph' => '留空则不修改',
        'set_btn_save' => '保存全局配置',
        'set_msg_live' => '修改实时生效',
        'toast_copied' => '已复制到剪贴板',
        'toast_link_copied' => '别名链接已复制',
        'quick_title' => '快速工具',
        'quick_desc' => '生成即时跳转 (记录在全局统计)',
        'quick_ph' => '输入链接以加密...',
    ],
    'en' => [
        'login_title' => 'Login - KoalaLink Lite Admin',
        'brand_lite' => 'KoalaLink Lite',
        'admin_pwd' => 'Admin Password',
        'btn_login' => 'Login',
        'pwd_error' => 'Incorrect Password',
        'nav_links' => 'Link Manager',
        'nav_settings' => 'Settings',
        'nav_stats' => 'Global Stats',
        'nav_logout' => 'Logout',
        'nav_title' => 'Control Panel',
        'msg_deleted' => 'Link deleted',
        'msg_updated' => 'Status updated',
        'msg_added' => 'New link created',
        'msg_add_failed' => 'Creation failed',
        'msg_saved' => 'Settings saved',
        'card_add_title' => 'Create New Redirect',
        'form_slug' => 'Alias (Slug)',
        'form_slug_ph' => 'Leave blank for random',
        'form_target' => 'Target URL',
        'form_quick' => 'Quick Generate (No DB)',
        'form_quick_enc' => 'Encrypted',
        'form_quick_dir' => 'Direct',
        'form_quick_copy' => 'Copy',
        'form_pwd' => 'Password (Optional)',
        'form_max_clicks' => 'Max Clicks (0 = Unlimited)',
        'form_expire' => 'Expiry (Optional)',
        'btn_create' => 'Create Link',
        'card_list_title' => 'Existing Links',
        'table_slug' => 'Alias',
        'table_target' => 'Target URL / Stats',
        'table_status' => 'Status',
        'table_actions' => 'Actions',
        'status_on' => 'Active',
        'status_off' => 'Inactive',
        'table_empty' => 'No links found',
        'confirm_delete' => 'Are you sure you want to delete?',
        'set_title' => 'Global Security Settings',
        'set_referer_label' => 'Referer Whitelist',
        'set_referer_desc' => 'Restrict access source by Referer. Separate multiple domains with commas. Leave empty for all.',
        'set_whitelist_label' => 'Trusted Domains Whitelist',
        'set_whitelist_desc' => 'Domains listed here will show a "Verified Partner" badge on the redirect page. Separate with commas.',
        'set_pwd_label' => 'Change Admin Password',
        'set_pwd_ph' => 'Leave blank to keep current',
        'set_btn_save' => 'Save Settings',
        'set_msg_live' => 'Changes take effect immediately',
        'toast_copied' => 'Copied to clipboard',
        'toast_link_copied' => 'Alias link copied',
        'quick_title' => 'Quick Tools',
        'quick_desc' => 'Generate Instant Link (Tracked in Global Stats)',
        'quick_ph' => 'Enter URL to encrypt...',
    ]
];
$text = $t[$lang];

// --- [ 2. 登录处理 ] ---
$admin_password = get_option($pdo, 'admin_password', 'admin');

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (($_POST['password'] ?? '') === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = $text['pwd_error'];
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- [ 2. 权限校验 ] ---
if (!IS_LOGGED_IN):
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $text['login_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #2563eb; 
            --bg: #f8fafc; 
        }
        body { 
            background: var(--bg); 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-family: 'Outfit', sans-serif;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(at 0% 0%, hsla(221, 83%, 53%, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, hsla(262, 83%, 58%, 0.1) 0px, transparent 50%);
            filter: blur(100px);
            z-index: -1;
        }
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 3rem; 
            border-radius: 2rem; 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 0.8rem;
            border-radius: 1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <img src="logo.png" alt="Logo" style="height:45px; margin-bottom:15px;" onerror="this.src='saas/logo.png'">
            <h4 class="fw-bold"><?php echo $text['brand_lite']; ?></h4>
            <p class="text-muted small">Control Panel Authentication</p>
        </div>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label class="form-label small fw-bold"><?php echo $text['admin_pwd']; ?></label>
                <input type="password" name="password" class="form-control rounded-3" style="background: rgba(255,255,255,0.5);" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?php echo $text['btn_login']; ?></button>
        </form>
    </div>
</body>
</html>
<?php 
exit; 
endif; 

// --- [ 3. CRUD 逻辑处理 ] ---
$message = '';

// A. 删除链接
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = $text['msg_deleted'];
}

// B. 切换状态
if (isset($_GET['toggle'])) {
    $stmt = $pdo->prepare("UPDATE links SET status = 1 - status WHERE id = ?");
    $stmt->execute([$_GET['toggle']]);
    $message = $text['msg_updated'];
}

// C. 新增链接
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $slug = $_POST['slug'] ?: substr(md5(uniqid()), 0, 6);
    $target_url = $_POST['target_url'];
    $password = $_POST['password'] ?: null;
    $expire_at = $_POST['expire_at'] ?: null;
    $max_clicks = (int)$_POST['max_clicks'] ?: 0;

    try {
        $stmt = $pdo->prepare("INSERT INTO links (slug, target_url, password, expire_at, max_clicks) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$slug, $target_url, $password, $expire_at, $max_clicks]);
        $message = $text['msg_added'] . ": $slug";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "创建失败：标识 '$slug' 已存在，请更换或留空自动生成。";
        } else {
            $message = $text['msg_add_failed'] . ": " . $e->getMessage();
        }
    }
}

// D. 更新配置
if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $allowed_referers = $_POST['allowed_referers'] ?? '';
    $whitelist = $_POST['whitelist'] ?? '';
    
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO options (key, value) VALUES ('allowed_referers', ?)");
    $stmt->execute([$allowed_referers]);
    
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO options (key, value) VALUES ('whitelist', ?)");
    $stmt->execute([$whitelist]);
    
    // 更新密码 (如果提供了新密码)
    if (!empty($_POST['new_password'])) {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO options (key, value) VALUES ('admin_password', ?)");
        $stmt->execute([$_POST['new_password']]);
    }

    $message = $text['msg_saved'];
}

// --- [ 4. 获取数据 ] ---
$links = $pdo->query("SELECT l.*, (SELECT COUNT(*) FROM stats s WHERE s.link_id = l.id) as clicks FROM links l ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$opt_referers = get_option($pdo, 'allowed_referers');
$opt_whitelist = get_option($pdo, 'whitelist');

$page = $_GET['page'] ?? 'links';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $text['nav_title']; ?> - KoalaLink Lite</title>
    <link rel="icon" type="image/png" href="Favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #2563eb; 
            --primary-light: rgba(37, 99, 235, 0.1);
            --bg: #f8fafc; 
        }
        body { 
            background-color: var(--bg); 
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
        }
        
        .navbar { 
            background: rgba(255, 255, 255, 0.8); 
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 0;
        }
        
        .card { 
            border: 1px solid rgba(0,0,0,0.05); 
            border-radius: 1.5rem; 
            background: white;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); 
            transition: transform 0.2s;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .nav-link {
            font-weight: 600;
            padding: 0.5rem 1rem !important;
            border-radius: 0.75rem;
            transition: all 0.2s;
            color: #64748b !important;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-light);
        }

        .table thead th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-top: none;
            padding: 1rem;
        }
        .table tbody td { padding: 1.25rem 1rem; }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 4px;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .bg-active { background: #dcfce7; color: #166534; }
        .bg-inactive { background: #fee2e2; color: #991b1b; }
        
        .form-control {
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px var(--primary-light);
            border-color: var(--primary);
        }
        
        .btn-primary { 
            background: var(--primary); 
            border: none; 
            padding: 0.75rem 1.5rem; 
            border-radius: 0.75rem; 
            font-weight: 600;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top mb-5">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="admin.php">
            <img src="logo.png" alt="KoalaLink" height="35" class="me-2" onerror="this.src='saas/logo.png'">
            <span class="fw-bold text-primary"><?php echo $text['brand_lite']; ?></span>
        </a>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item mx-1">
                    <a class="nav-link <?php echo $page=='links'?'active':''; ?>" href="?page=links">
                        <i class="bi bi-link-45deg"></i> <?php echo $text['nav_links']; ?>
                    </a>
                </li>
                <li class="nav-item mx-1">
                    <a class="nav-link <?php echo $page=='settings'?'active':''; ?>" href="?page=settings">
                        <i class="bi bi-gear-fill"></i> <?php echo $text['nav_settings']; ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="d-flex align-items-center">
            <a href="analytics.php" class="btn btn-primary-light text-primary fw-bold me-2 px-3 py-2 btn-sm rounded-3">
                <i class="bi bi-bar-chart-fill me-1"></i> <?php echo $text['nav_stats']; ?>
            </a>
            <a href="?action=logout" class="btn btn-outline-danger btn-sm rounded-3 px-3">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>

<div class="container mb-3">
    <?php if($message): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if($page === 'links'): ?>
    <div class="row">
        <!-- Create Link Card -->
        <div class="col-md-4">
            <div class="card p-4 mb-4 glass-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle-fill text-primary"></i> <?php echo $text['card_add_title']; ?></h5>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label small fw-bold opacity-75"><?php echo $text['form_slug']; ?></label>
                        <input type="text" name="slug" class="form-control" placeholder="<?php echo $text['form_slug_ph']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold opacity-75"><?php echo $text['form_target']; ?></label>
                        <input type="url" name="target_url" class="form-control" placeholder="https://..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold opacity-75"><?php echo $text['form_pwd']; ?></label>
                        <input type="text" name="password" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold opacity-75"><?php echo $text['form_max_clicks']; ?></label>
                            <input type="number" name="max_clicks" class="form-control" value="0">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold opacity-75"><?php echo $text['form_expire']; ?></label>
                            <input type="datetime-local" name="expire_at" class="form-control px-2 small">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-2"><?php echo $text['btn_create']; ?></button>
                </form>
            </div>

            <!-- Quick Tools Card -->
            <div class="card p-4 glass-card">
                <h5 class="mb-3 fw-bold"><i class="bi bi-lightning-charge-fill text-warning"></i> <?php echo $text['quick_title']; ?></h5>
                <div class="mb-3">
                    <label class="form-label small text-muted"><?php echo $text['quick_desc']; ?></label>
                    <input type="url" id="toolRawUrl" class="form-control form-control-sm border-0" style="background: rgba(0,0,0,0.05);" placeholder="<?php echo $text['quick_ph']; ?>" oninput="genAllLinks()">
                </div>
                
                <div id="quickLinks" class="d-none">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold"><?php echo $text['form_quick_enc']; ?></label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="linkBase64" class="form-control bg-light border-0" readonly>
                            <button type="button" class="btn btn-primary" onclick="copyToClipboard('linkBase64')"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small fw-bold"><?php echo $text['form_quick_dir']; ?></label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="linkDirect" class="form-control bg-light border-0" readonly>
                            <button type="button" class="btn btn-primary" onclick="copyToClipboard('linkDirect')"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Link List Card -->
        <div class="col-md-8">
            <div class="card overflow-hidden">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><?php echo $text['card_list_title']; ?></h5>
                    <span class="badge bg-primary-light text-primary rounded-pill"><?php echo count($links); ?> Total</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?php echo $text['table_slug']; ?></th>
                                <th><?php echo $text['table_target']; ?></th>
                                <th><?php echo $text['table_status']; ?></th>
                                <th class="text-end pe-4"><?php echo $text['table_actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($links as $link): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">/<?php echo htmlspecialchars($link['slug']); ?></div>
                                    <small class="text-muted opacity-75" style="font-size: 0.7rem;"><?php echo date('Y-m-d H:i', strtotime($link['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="text-truncate mb-1" style="max-width: 280px;">
                                        <a href="<?php echo htmlspecialchars($link['target_url']); ?>" target="_blank" class="text-primary text-decoration-none fw-medium small">
                                            <?php echo htmlspecialchars($link['target_url']); ?>
                                        </a>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <span class="badge bg-primary-light text-primary border-0" style="font-size: 0.7rem;">
                                            <i class="bi bi-bar-chart-fill"></i> <?php echo $link['clicks']; ?> / <?php echo $link['max_clicks'] ?: '∞'; ?>
                                        </span>
                                        <?php if($link['password']): ?>
                                            <span class="badge bg-warning-light text-warning" style="font-size: 0.7rem;"><i class="bi bi-shield-lock-fill"></i></span>
                                        <?php endif; ?>
                                        <?php if($link['expire_at']): ?>
                                            <span class="badge bg-danger-light text-danger" style="font-size: 0.7rem;"><i class="bi bi-clock-history"></i> <?php echo date('m-d', strtotime($link['expire_at'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $link['status'] ? 'bg-active' : 'bg-inactive'; ?>">
                                        <i class="bi <?php echo $link['status'] ? 'bi-patch-check-fill' : 'bi-pause-circle-fill'; ?> me-1"></i>
                                        <?php echo $link['status'] ? $text['status_on'] : $text['status_off']; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                        <button class="btn btn-white btn-action border text-primary" onclick="copyFullLink('<?php echo $link['slug']; ?>')" title="Copy"><i class="bi bi-link-45deg"></i></button>
                                        <a href="go.php?to=<?php echo $link['slug']; ?>" target="_blank" class="btn btn-white btn-action border text-secondary"><i class="bi bi-eye-fill"></i></a>
                                        <a href="analytics.php?id=<?php echo $link['id']; ?>" class="btn btn-white btn-action border text-info"><i class="bi bi-graph-up-arrow"></i></a>
                                        <a href="?toggle=<?php echo $link['id']; ?>" class="btn btn-white btn-action border text-warning"><i class="bi bi-power"></i></a>
                                        <a href="?delete=<?php echo $link['id']; ?>" class="btn btn-white btn-action border text-danger" onclick="return confirm('<?php echo $text['confirm_delete']; ?>')"><i class="bi bi-trash3-fill"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($links)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted opacity-50"><i class="bi bi-folder-x fs-1 d-block mb-2"></i><?php echo $text['table_empty']; ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php elseif($page === 'settings'): ?>
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card p-5 glass-card">
                <h4 class="mb-4 fw-bold"><i class="bi bi-shield-lock text-primary"></i> <?php echo $text['set_title']; ?></h4>
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold opacity-75 small uppercase"><?php echo $text['set_referer_label']; ?></label>
                        <p class="small text-muted mb-2"><?php echo $text['set_referer_desc']; ?></p>
                        <textarea name="allowed_referers" class="form-control" rows="3" placeholder="example.com, dsi.mom"><?php echo htmlspecialchars($opt_referers); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold opacity-75 small uppercase"><?php echo $text['set_whitelist_label']; ?></label>
                        <p class="small text-muted mb-2"><?php echo $text['set_whitelist_desc']; ?></p>
                        <textarea name="whitelist" class="form-control" rows="3" placeholder="github.com, bitekaola.com"><?php echo htmlspecialchars($opt_whitelist); ?></textarea>
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold opacity-75 small uppercase"><?php echo $text['set_pwd_label']; ?></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-key-fill text-muted"></i></span>
                            <input type="password" name="new_password" class="form-control border-0 bg-light" placeholder="<?php echo $text['set_pwd_ph']; ?>">
                        </div>
                    </div>
                    
                    <div class="pt-4 border-top d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="bi bi-info-circle-fill"></i> <?php echo $text['set_msg_live']; ?></span>
                        <button type="submit" class="btn btn-primary px-5 shadow-lg"><?php echo $text['set_btn_save']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function genAllLinks() {
        const rawUrl = document.getElementById('toolRawUrl').value.trim();
        const quickBox = document.getElementById('quickLinks');
        
        if (!rawUrl.startsWith('http')) {
            quickBox.classList.add('d-none');
            document.getElementById('linkBase64').value = '';
            document.getElementById('linkDirect').value = '';
            return;
        }
        
        quickBox.classList.remove('d-none');
        const baseUrl = window.location.origin + window.location.pathname.replace('admin.php', 'go.php');
        
        // Base64
        const encoded = btoa(unescape(encodeURIComponent(rawUrl)));
        document.getElementById('linkBase64').value = baseUrl + '?url=' + encoded;
        
        // Direct
        document.getElementById('linkDirect').value = baseUrl + '?url=' + rawUrl;
    }

    function copyFullLink(slug) {
        const baseUrl = window.location.origin + window.location.pathname.replace('admin.php', 'go.php');
        const fullUrl = baseUrl + '?to=' + slug;
        navigator.clipboard.writeText(fullUrl);
        alert("<?php echo $text['toast_link_copied']; ?>");
    }

    function copyToClipboard(id) {
        const copyText = document.getElementById(id);
        if (!copyText.value) return;
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value);
        alert("<?php echo $text['toast_copied']; ?>");
    }
</script>
</body>
</html>

