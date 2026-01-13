<?php
/**
 * KoalaLink Lite Core V2.1
 * 功能：数据库映射、Base64解析、点击统计、安全校验、过期/次数控制、i18n、品牌化中转页
 */

// --- [ 配置区 ] ---
$db_path = __DIR__ . '/data/redirect.db'; // 数据库路径
$allow_empty_referer = true; // 是否允许直接输入 URL 访问

// 屏蔽非必要警告 (Added Fix)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('Asia/Shanghai');

// --- [ 1. 语言检测 (i18n) ] ---
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'en' => [
        'title' => 'Security Redirect - BitKoalaLink Lite',
        'generator_title' => 'KoalaLink Lite Link Encryptor',
        'heading_exit' => 'Leaving BitkoalaLab',
        'heading_safe' => 'Security Verified',
        'tip_warn' => '<strong>Security Warning:</strong> You are visiting an external link. Please verify the identity and protect your privacy.',
        'tip_safe' => '<strong>Trusted Link:</strong> This URL points to a trusted partner or official project. It is safe to proceed.',
        'going_to' => 'Destination:',
        'countdown' => 'Redirecting in <span id="timer">5</span> seconds',
        'btn_go' => 'Proceed Now',
        'btn_gen' => 'Generate',
        'invalid' => 'Invalid Destination URL',
        'gen_placeholder' => 'Enter original URL',
        'gen_res' => 'Result:',
        'pwd_protected' => 'This link is password protected',
        'pwd_placeholder' => 'Enter password',
        'btn_verify' => 'Verify & Go',
        // Intro Page
        'intro_title' => 'Professional Link Solutions',
        'intro_slogan' => 'Simple . Secure . Private',
        'intro_f1_title' => 'Encrypted Redirection',
        'intro_f1_desc' => 'Hide destination URLs with Base64 or Alias security.',
        'intro_f2_title' => 'Safe Bridge',
        'intro_f2_desc' => 'A security-aware middle page to prevent fishing.',
        'intro_f3_title' => 'Real-time Stats',
        'intro_f3_desc' => 'Track every click with detailed referer insights.',
        'intro_f4_title' => 'Global Speed',
        'intro_f4_desc' => 'Ultra-lightweight PHP/SQLite architecture for speed.',
        'btn_admin' => 'Admin Panel'
    ],
    'zh' => [
        'title' => '安全跳转 - BitKoalaLink Lite',
        'generator_title' => 'KoalaLink Lite 链接加密生成器',
        'heading_exit' => '即将离开 BitkoalaLab',
        'heading_safe' => '安全验证通过',
        'tip_warn' => '<strong>安全警示：</strong>您当前访问的是外部链接，请核实对方身份，保护好个人隐私。',
        'tip_safe' => '<strong>官方推荐：</strong> 此链接指向受信任的合作伙伴或比特考拉Bitekaola官方项目，通过安全验证，请放心访问。',
        'going_to' => '正在前往：',
        'countdown' => '将在 <span id="timer">5</span> 秒后自动安全跳转',
        'btn_go' => '立即前往',
        'btn_gen' => '立即生成',
        'invalid' => '无效的跳转链接',
        'gen_placeholder' => '输入原始网址',
        'gen_res' => '生成结果：',
        'pwd_protected' => '此链接受密码保护',
        'pwd_placeholder' => '输入密码',
        'btn_verify' => '验证并跳转',
        // Intro Page
        'intro_title' => '专业短链接中转方案',
        'intro_slogan' => '纯净 · 安全 · 私密',
        'intro_f1_title' => '多模式跳转',
        'intro_f1_desc' => '支持别名 (Alias) 或 Base64 加密，隐藏原始目标。',
        'intro_f2_title' => '品牌安全桥',
        'intro_f2_desc' => '智能识别恶意链接，提供品牌化的跳转中转页。',
        'intro_f3_title' => '实时流量审计',
        'intro_f3_desc' => '多维度记录访问来源、IP、UA 和目标分布。',
        'intro_f4_title' => '极致性能',
        'intro_f4_desc' => '原生 PHP + SQLite 架构，响应时间低至毫秒级。',
        'btn_admin' => '管理后台'
    ]
];
$text = $t[$lang];

// --- [ 2. 数据库初始化 ] ---
function init_db($db_path) {
    if (!file_exists(dirname($db_path))) {
        mkdir(dirname($db_path), 0755, true);
    }
    try {
        $pdo = new PDO("sqlite:$db_path");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS links (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, target_url TEXT NOT NULL, status INTEGER DEFAULT 1, password TEXT, max_clicks INTEGER DEFAULT 0, expire_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS stats (id INTEGER PRIMARY KEY AUTOINCREMENT, link_id INTEGER, target_url TEXT, click_time DATETIME DEFAULT CURRENT_TIMESTAMP, referer TEXT, ip_address TEXT, user_agent TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS options (key TEXT PRIMARY KEY, value TEXT)");
        
        // 数据库迁移：检查 stats 表是否有 target_url 字段
        $res = $pdo->query("PRAGMA table_info(stats)")->fetchAll(PDO::FETCH_ASSOC);
        $has_target_url = false;
        foreach ($res as $col) { if ($col['name'] === 'target_url') { $has_target_url = true; break; } }
        if (!$has_target_url) {
            $pdo->exec("ALTER TABLE stats ADD COLUMN target_url TEXT");
        }

        // 初始配置（如果不存在）
        $pdo->exec("INSERT OR IGNORE INTO options (key, value) VALUES ('admin_password', 'admin')");
        $pdo->exec("INSERT OR IGNORE INTO options (key, value) VALUES ('allowed_referers', '')"); // Default empty to allow direct access
        $pdo->exec("INSERT OR IGNORE INTO options (key, value) VALUES ('whitelist', 'bitekaola.com,bitkoala.net,github.com,pickoala.com')");
        
        return $pdo;
    } catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
}



function get_option($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT value FROM options WHERE key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?: $default;
}

// --- [ 3. 核心逻辑处理 ] ---
if (basename($_SERVER['PHP_SELF']) == 'go.php') {
    $pdo = init_db($db_path);
    
    // 获取动态配置
    $allowed_referers_str = get_option($pdo, 'allowed_referers', '');
    $allowed_referers = array_filter(array_map('trim', explode(',', $allowed_referers_str)));
    $whitelist_str = get_option($pdo, 'whitelist', 'bitekaola.com,bitkoala.net,github.com,pickoala.com');
    $whitelist = array_filter(array_map('trim', explode(',', $whitelist_str)));

    // 安全校验：Referer (防盗链)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && !empty($allowed_referers)) {
        $ref_host = parse_url($referer, PHP_URL_HOST);
        $match = false;
        foreach ($allowed_referers as $allowed) {
            if ($ref_host === $allowed) { $match = true; break; }
        }
        if (!$match) {
            // header("HTTP/1.1 403 Forbidden");
            // exit("Access Denied: Unauthorized Referer.");
        }
    }

    $slug = $_GET['to'] ?? '';
    $encoded_url = $_GET['url'] ?? '';
    $target_url = '';
    $link_id = 0;
    $mode = '';

    if (!empty($slug)) {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE slug = :slug AND status = 1 LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link) {
            $mode = 'redirect';
            if ($link['expire_at'] && strtotime($link['expire_at']) < time()) $mode = 'error';
            if ($link['max_clicks'] > 0) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM stats WHERE link_id = :id");
                $countStmt->execute([':id' => $link['id']]);
                if ($countStmt->fetchColumn() >= $link['max_clicks']) $mode = 'error';
            }
            if (!empty($link['password'])) {
                if (!isset($_POST['pwd']) || $_POST['pwd'] !== $link['password']) {
                    $mode = 'password';
                }
            }
            if ($mode === 'redirect') {
                $target_url = $link['target_url'];
                $link_id = $link['id'];
            }
        } else {
            $mode = 'error';
        }
    } elseif (!empty($encoded_url)) {
        $mode = 'redirect';
        $decoded = base64_decode(str_replace(' ', '+', $encoded_url), true);
        if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
            $target_url = $decoded;
        } elseif (filter_var($encoded_url, FILTER_VALIDATE_URL)) {
            $target_url = $encoded_url;
        } else {
            $mode = 'error';
        }
    }

    if ($mode === 'redirect' && $target_url && filter_var($target_url, FILTER_VALIDATE_URL)) {
        try {
            $pdo->prepare("INSERT INTO stats (link_id, target_url, referer, ip_address, user_agent, click_time) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$link_id, $target_url, $referer, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], date('Y-m-d H:i:s')]);
        } catch (Exception $e) {}

        $host = parse_url($target_url, PHP_URL_HOST);
        $is_safe = false;
        foreach ($whitelist as $domain) {
            if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -(strlen($domain) + 1)) === ".$domain")) {
                $is_safe = true;
                break;
            }
        }
        render_page('redirect', $target_url, $text, $is_safe, $host);
    } elseif (!empty($slug) || !empty($encoded_url)) {
        render_page('error', null, $text);
    } else {
        render_page('intro', null, $text);
    }
}

// --- [ 4. UI 渲染引擎 ] ---
function render_page($mode, $target_url, $text_local, $is_safe = false, $host = '') {
    global $text;
    if (empty($text_local)) $text_local = $text;
    $timer = $is_safe ? 3 : 5;
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $lang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo ($mode == 'redirect') ? $text_local['title'] : ($mode == 'intro' ? 'KoalaLink Lite - ' . $text_local['intro_title'] : $text_local['pwd_protected']); ?></title>
        <link rel="icon" type="image/png" href="Favicon.png">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            :root { 
                --primary: #2563eb; 
                --primary-hsl: 221.2 83.2% 53.3%;
                --safe: #10b981; 
                --warn: #f59e0b; 
                --bg: #f8fafc; 
                --text: #1e293b; 
                --muted: #64748b; 
            }
            
            body { 
                margin: 0; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                min-height: 100vh; 
                background: var(--bg); 
                font-family: 'Outfit', sans-serif; 
                overflow: hidden;
                position: relative;
            }

            body::before {
                content: "";
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: 
                    radial-gradient(at 0% 0%, hsla(221, 83%, 53%, 0.1) 0px, transparent 50%),
                    radial-gradient(at 100% 0%, hsla(262, 83%, 58%, 0.1) 0px, transparent 50%),
                    radial-gradient(at 100% 100%, hsla(11, 90%, 71%, 0.1) 0px, transparent 50%),
                    radial-gradient(at 0% 100%, hsla(173, 80%, 40%, 0.1) 0px, transparent 50%);
                filter: blur(100px);
                z-index: -1;
                animation: mesh-gradient 20s ease infinite alternate;
            }
            @keyframes mesh-gradient {
                0% { transform: scale(1); }
                100% { transform: scale(1.2) translate(5%, 5%); }
            }

            .card { 
                padding: 3rem; 
                border-radius: 2rem; 
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.4);
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); 
                width: 90%; 
                max-width: 480px; 
                text-align: center; 
                transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1); 
                position: relative;
                z-index: 1;
            }
            
            .card:hover {
                transform: translateY(-5px);
                box-shadow: 0 30px 60px -12px rgba(0,0,0,0.15);
            }

            .logo-container { margin-bottom: 2rem; } 
            .logo-container img { max-width: 180px; max-height: 70px; object-fit: contain; }
            
            h2 { 
                font-size: 1.75rem; 
                font-weight: 700; 
                margin: 0 0 1rem; 
                line-height: 1.2; 
                letter-spacing: -0.02em;
                color: #1e293b;
            }
            
            .slogan { color: var(--muted); font-size: 0.95rem; margin-bottom: 2rem; font-weight: 500; }

            .tip-box { 
                padding: 1.2rem; 
                border-radius: 1.2rem; 
                margin-bottom: 2rem; 
                text-align: left; 
                font-size: 0.95rem; 
                line-height: 1.6; 
                border: 1px solid transparent; 
            }
            
            .tip-warn { 
                background: rgba(255, 251, 235, 0.6); 
                border-color: rgba(251, 191, 36, 0.2); 
                color: #92400e; 
            }
            
            .tip-safe { 
                background: rgba(236, 253, 245, 0.6); 
                border-color: rgba(52, 211, 153, 0.2); 
                color: #065f46; 
            }

            .feature-grid { text-align: left; display: grid; gap: 1rem; margin-top: 1rem; }
            .feature-item { 
                padding: 1.2rem; 
                background: rgba(255, 255, 255, 0.4); 
                border-radius: 1.2rem; 
                border: 1px solid rgba(255, 255, 255, 0.5); 
                transition: all 0.2s;
            }
            .feature-item:hover { background: rgba(255, 255, 255, 0.8); transform: scale(1.02); }
            .feature-title { font-weight: 700; color: var(--primary); font-size: 1rem; display: block; margin-bottom: 0.3rem; }
            .feature-desc { font-size: 0.875rem; color: var(--muted); line-height: 1.5; font-weight: 400; }

            .progress-container { 
                height: 6px; 
                background: rgba(0,0,0,0.05); 
                border-radius: 10px; 
                margin: 2rem 0 1rem; 
                overflow: hidden; 
            }
            
            .progress-bar { 
                height: 100%; 
                background: var(--primary); 
                width: 0%; 
                transition: width 1s linear; 
                border-radius: 10px;
            }
            .is-safe-bar { background: var(--safe); }

            .btn-direct { 
                display: block; 
                margin-top: 1.5rem; 
                padding: 1rem; 
                background: var(--primary); 
                color: white; 
                text-decoration: none; 
                border-radius: 1rem; 
                font-weight: 600; 
                text-align: center; 
                border: none; 
                width: 100%; 
                box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.3); 
                transition: all 0.3s ease;
                cursor: pointer;
            }
            .btn-safe { background: var(--safe); box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.3); }
            .btn-direct:hover { transform: scale(1.02); opacity: 0.95; }

            .input-field { 
                width: 100%; 
                padding: 1rem; 
                border: 1px solid rgba(0,0,0,0.1); 
                border-radius: 1rem; 
                box-sizing: border-box; 
                margin-top: 0.5rem; 
                margin-bottom: 1rem; 
                background: rgba(255,255,255,0.5);
                font-family: inherit;
                font-size: 1rem;
            }
            
            .host-info {
                margin-top: 0.8rem; 
                padding-top: 0.8rem; 
                border-top: 1px solid rgba(0,0,0,0.05); 
                font-weight: 600; 
                display: flex; 
                align-items: center; 
                gap: 0.5rem;
                font-size: 0.95rem;
            }
            .host-label { opacity: 0.6; font-weight: 400; font-size: 0.85rem; }
            .host-code { 
                background: rgba(0,0,0,0.05); 
                padding: 0.2rem 0.5rem; 
                border-radius: 0.4rem; 
                color: var(--primary); 
                word-break: break-all;
            }
        </style>
    </head>
    <body>
    <div class="card">
        <div class="logo-container"><img src="logo.png" alt="Logo" onerror="this.src='saas/logo.png'"></div>
        
        <?php if ($mode == 'redirect'): ?>
            <h2 class="<?php echo $is_safe ? 'safe-heading' : ''; ?>"><?php echo $is_safe ? $text_local['heading_safe'] : $text_local['heading_exit']; ?></h2>
            
            <div class="tip-box <?php echo $is_safe ? 'tip-safe' : 'tip-warn'; ?>">
                <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <i class="bi <?php echo $is_safe ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>" style="margin-top: 0.2rem;"></i>
                    <div><?php echo $is_safe ? $text_local['tip_safe'] : $text_local['tip_warn']; ?></div>
                </div>
                <?php if ($host): ?>
                    <div class="host-info">
                        <span class="host-label"><?php echo $text_local['going_to']; ?></span>
                        <span class="host-code"><?php echo htmlspecialchars($host); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="progress-container"><div class="progress-bar <?php echo $is_safe ? 'is-safe-bar' : ''; ?>" id="progressBar"></div></div>
            <p style="color:var(--muted); font-size:0.9rem; font-weight: 500;"><?php echo str_replace('5', '<span id="timer" style="font-weight: 700; color: var(--text);">'.$timer.'</span>', $text_local['countdown']); ?></p>
            
            <a href="<?php echo htmlspecialchars($target_url); ?>" class="btn-direct <?php echo $is_safe ? 'btn-safe' : ''; ?>"><?php echo $text_local['btn_go']; ?></a>
            
            <script>
                let timeLeft = <?php echo $timer; ?>;
                const totalTime = timeLeft;
                const progressEl = document.getElementById('progressBar');
                const countdown = setInterval(() => {
                    timeLeft--;
                    if (document.getElementById('timer')) document.getElementById('timer').textContent = timeLeft;
                    progressEl.style.width = ((totalTime - timeLeft) / totalTime * 100) + "%";
                    if (timeLeft <= 0) { 
                        clearInterval(countdown); 
                        window.location.href = "<?php echo $target_url; ?>"; 
                    }
                }, 1000);
                window.onload = () => setTimeout(() => progressEl.style.width = (100 / totalTime) + "%", 50);
            </script>

        <?php elseif ($mode == 'password'): ?>
            <h2><i class="bi bi-shield-lock-fill text-primary"></i> <?php echo $text_local['pwd_protected']; ?></h2>
            <form method="POST">
                <input type="password" name="pwd" class="input-field" placeholder="<?php echo $text_local['pwd_placeholder']; ?>" required autofocus>
                <button type="submit" class="btn-direct"><?php echo $text_local['btn_verify']; ?></button>
            </form>

        <?php elseif ($mode == 'intro'): ?>
            <h2><?php echo $text_local['intro_title']; ?></h2>
            <div class="slogan"><?php echo $text_local['intro_slogan']; ?></div>
            <div class="feature-grid">
                <div class="feature-item">
                    <span class="feature-title"><i class="bi bi-rocket-takeoff-fill"></i> <?php echo $text_local['intro_f1_title']; ?></span>
                    <span class="feature-desc"><?php echo $text_local['intro_f1_desc']; ?></span>
                </div>
                <div class="feature-item">
                    <span class="feature-title"><i class="bi bi-shield-check"></i> <?php echo $text_local['intro_f2_title']; ?></span>
                    <span class="feature-desc"><?php echo $text_local['intro_f2_desc']; ?></span>
                </div>
                <div class="feature-item">
                    <span class="feature-title"><i class="bi bi-bar-chart-fill"></i> <?php echo $text_local['intro_f3_title']; ?></span>
                    <span class="feature-desc"><?php echo $text_local['intro_f3_desc']; ?></span>
                </div>
                <div class="feature-item">
                    <span class="feature-title"><i class="bi bi-lightning-fill"></i> <?php echo $text_local['intro_f4_title']; ?></span>
                    <span class="feature-desc"><?php echo $text_local['intro_f4_desc']; ?></span>
                </div>
            </div>
            <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <p style="font-size: 0.8rem; color: #94a3b8; font-weight: 500;">© <?php echo date('Y'); ?> BitkoalaLab.</p>
                <a href="admin.php" style="font-size: 0.8rem; color: var(--primary); text-decoration: none; font-weight: 600;"><?php echo $text_local['btn_admin']; ?> <i class="bi bi-arrow-right-short"></i></a>
            </div>

        <?php elseif ($mode == 'error'): ?>
            <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
            <h2 style="color: var(--warn);"><?php echo $text_local['invalid']; ?></h2>
            <p class="slogan"><?php echo strip_tags($text_local['tip_warn']); ?></p>
            <a href="go.php" class="btn-direct" style="background: var(--muted); box-shadow: none;"><?php echo $text_local['intro_title']; ?></a>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
}

