<?php
/**
 * KoalaLink SaaS - Redirection Engine
 */
require_once __DIR__ . '/db.php';

// Hide Warnings
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set('Asia/Shanghai');


$pdo = init_saas_db($db_path);

// --- [ 1. 语言检测 ] ---
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'zh' => [
        'title' => '安全跳转 - BitkoalaLink',
        'heading_exit' => '即将离开 BitkoalaLab',
        'heading_safe' => '安全验证通过',
        'tip_warn' => '<strong>安全警示：</strong> 您正在访问外部链接，请注意核实身份并保护个人隐私。如有风险请中止访问。',
        'tip_safe' => '<strong>官方推荐：</strong> 此链接指向受信任的合作伙伴或比特考拉Bitekaola官方项目，通过安全验证，请放心访问。',
        'going_to' => '正在前往：',
        'countdown' => '将在 <span id="timer">5</span> 秒后自动安全跳转',
        'btn_go' => '立即前往',
        'invalid' => '无效的跳转链接',
        'pwd_protected' => '此链接受密码保护',
        'pwd_placeholder' => '输入密码',
        'btn_verify' => '验证并跳转'
    ],
    'en' => [
        'title' => 'Security Redirect - BitkoalaLink',
        'heading_exit' => 'Leaving BitkoalaLab',
        'heading_safe' => 'Security Verified',
        'tip_warn' => '<strong>Security Warning:</strong> You are visiting an external link. Please verify the identity and protect your privacy.',
        'tip_safe' => '<strong>Trusted Link:</strong> This URL points to a trusted partner or official project. It is safe to proceed.',
        'going_to' => 'Destination:',
        'countdown' => 'Redirecting in <span id="timer">5</span> seconds',
        'btn_go' => 'Proceed Now',
        'invalid' => 'Invalid Destination URL',
        'pwd_protected' => 'This link is password protected',
        'pwd_placeholder' => 'Enter password',
        'btn_verify' => 'Verify & Go'
    ]
];
$text = $t[$lang];

// --- [ 2. 核心逻辑 ] ---
$current_host = $_SERVER['HTTP_HOST'];
$user_ip = $_SERVER['REMOTE_ADDR'];

// X. 访问限速 (Rate Limiting)
$rate_limit = (int)get_user_option($pdo, 0, 'rate_limit');
if ($rate_limit > 0) {
    if (DB_DRIVER === 'sqlite') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stats WHERE ip_address = ? AND click_time > DATETIME('now', '-1 minute')");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stats WHERE ip_address = ? AND click_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    }
    $stmt->execute([$user_ip]);
    if ($stmt->fetchColumn() >= $rate_limit) {
        header("HTTP/1.1 429 Too Many Requests");
        header("Retry-After: 60");
        exit("Rate limit exceeded. Please try again in 1 minute.");
    }
}

$slug = $_GET['to'] ?? '';
$target_url = '';
$link_id = 0;
$user_id = 0;
$scoped_user_id = 0; // 域名锁定的用户ID
$mode = ''; // 初始化 Mode

// X. Redirect Empty Request to Landing Page
if (empty($_GET['to']) && empty($_GET['url']) && empty($_SERVER['QUERY_STRING'])) {
    header("Location: index_pro.php");
    exit;
}

// A. 域名识别：判断是系统主域还是自定义域名
if ($current_host !== PRIMARY_DOMAIN) {
    $stmt = $pdo->prepare("SELECT user_id FROM custom_domains WHERE domain = ? AND status = 1 LIMIT 1");
    $stmt->execute([$current_host]);
    $scoped_user_id = $stmt->fetchColumn();
    
    // 如果是未知的自定义域名且不是主域，可以选择拦截或放行（这里选择拦截以安全）
    if (!$scoped_user_id && $current_host !== 'localhost' && $current_host !== '127.0.0.1') {
        header("HTTP/1.1 403 Forbidden");
        exit("Invalid Access: Domain not linked or verified.");
    }
}

// 安全校验：全局 Referer (由超管配置)
// 安全校验：Referer (用户配置优先，系统默认兜底)
// 注意：此时可能还未获取到 $link，故先使用系统默认进行初步校验
// 真正严格的用户级校验需在获取 link 后进行
$allowed_referers = array_filter(array_map('trim', explode(',', get_user_option($pdo, 0, 'allowed_referers'))));
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer) && !empty($allowed_referers)) {
    $ref_host = parse_url($referer, PHP_URL_HOST);
    $match = false;
    foreach ($allowed_referers as $allowed) {
        if ($ref_host === $allowed) { $match = true; break; }
    }
    if (!$match) {
        // Disable strict blocking for now to allow testing
        // header("HTTP/1.1 403 Forbidden");
        // exit("Access Denied: Unauthorized Referer.");
    }
}

if ($slug) {
    // 如果锁定了用户ID，则只在该用户下查找 Slug（防止 Slug 冲突）
    if ($scoped_user_id) {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE slug = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$slug, $scoped_user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM links WHERE slug = ? ORDER BY created_at ASC LIMIT 1");
        $stmt->execute([$slug]);
    }
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        $link_id = $link['id'];
        $user_id = $link['user_id'];
        
        // Initialize mode to redirect by default if link is found
        $mode = 'redirect';

        // 状态检查
        if ($link['status'] == 0) { $mode = 'error'; }

        // 过期检查 & Fallback
        if ($link['expire_at'] && strtotime($link['expire_at']) < time()) {
             if (!empty($link['fallback_url'])) {
                 header("Location: " . $link['fallback_url']);
                 exit;
             }
             $mode = 'error'; 
        }

        // 点击次数检查
        if ($link['max_clicks'] > 0) {
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM stats WHERE link_id = ?");
            $count_stmt->execute([$link_id]);
            if ($count_stmt->fetchColumn() >= $link['max_clicks']) { $mode = 'error'; }
        }

        // 密码检查
        if ($mode !== 'error' && !empty($link['password'])) {
            $input_pwd = $_POST['auth_pwd'] ?? '';
            if ($input_pwd !== $link['password']) {
                $mode = 'password';
            }
        }

        // B. 用户级 Referer 校验 (如果用户配置了)
        // 获取用户自定义 Referer 白名单，如果用户未设置，get_user_option 会回退到系统默认(user_id=0)
        // 特殊值 "*" 表示不限制任何来源
        $user_referers_str = get_user_option($pdo, $user_id, 'allowed_referers');
        if (!empty($user_referers_str)) {
            // 检查是否为通配符 "*"，表示允许所有来源
            if (trim($user_referers_str) === '*') {
                // 不限制，跳过 Referer 检查
            } else {
                $user_referers = array_filter(array_map('trim', explode(',', $user_referers_str)));
                if (!empty($user_referers) && !empty($referer)) {
                    $ref_host = parse_url($referer, PHP_URL_HOST);
                    $match = false;
                    foreach ($user_referers as $allowed) {
                        if ($ref_host === $allowed) { $match = true; break; }
                    }
                    if (!$match) {
                        // header("HTTP/1.1 403 Forbidden");
                        // exit("Access Denied: Referer not allowed by user policy.");
                    }
                }
            }
        }

        if ($mode === 'redirect') {
            $target_url = $link['target_url'];
            
            // --- [ Smart Routing Logic ] ---
            // Priority: Device > Geo > A/B
            if (!empty($link['routing_rules'])) {
                $rules = json_decode($link['routing_rules'], true);
                if (is_array($rules)) {
                    // 1. Device Split
                    if (isset($rules['device'])) {
                        $ua_router = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        if (stripos($ua_router, 'iPhone') !== false || stripos($ua_router, 'iPad') !== false) {
                            if (!empty($rules['device']['ios'])) $target_url = $rules['device']['ios'];
                        } elseif (stripos($ua_router, 'Android') !== false) {
                             if (!empty($rules['device']['android'])) $target_url = $rules['device']['android'];
                        }
                    }

                    // 2. Geo/Language Split
                    // Fallback to Language if IP geo not available
                    if (isset($rules['geo'])) {
                        $geo_code = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''; // Cloudflare
                        if (empty($geo_code)) {
                            $accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
                            $geo_code = strtoupper(substr($accept_lang, 0, 2));
                        }
                        if (!empty($rules['geo'][$geo_code])) {
                            $target_url = $rules['geo'][$geo_code];
                        }
                    }

                    // 3. A/B Testing
                    if (isset($rules['ab']) && !empty($rules['ab']['url']) && isset($rules['ab']['ratio'])) {
                        // Ratio is percent (0-100) to go to variant
                        if (mt_rand(1, 100) <= (int)$rules['ab']['ratio']) {
                            $target_url = $rules['ab']['url'];
                        }
                    }
                }
            }
        }
    } else {
        $mode = 'error';
    }
} elseif (!empty($_GET['url'])) {
    $mode = 'redirect';
    $encoded_url = $_GET['url'];
    // Base64 Decode
    $decoded = base64_decode(str_replace(' ', '+', $encoded_url), true);
    if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
        $target_url = $decoded;
    } elseif (filter_var($encoded_url, FILTER_VALIDATE_URL)) {
        $target_url = $encoded_url;
    } else {
        $mode = 'error';
    }
} else {
    $mode = 'error';
}

if ($mode !== 'error') {
    // 解析 User-Agent
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $device = 'PC';
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) $device = 'Tablet';
    elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $ua)) $device = 'Mobile';

    $os = 'Unknown OS';
    $os_array = [
        '/iphone/i' => 'iPhone', '/ipad/i' => 'iPad', '/android/i' => 'Android',
        '/windows nt 10/i' => 'Windows 10', '/windows nt 6.1/i' => 'Windows 7',
        '/macintosh|mac os x/i' => 'Mac OS X', '/linux/i' => 'Linux'
    ];
    foreach ($os_array as $regex => $value) { if (preg_match($regex, $ua)) { $os = $value; break; } }

    $browser = 'Unknown Browser';
    if (preg_match('/micromessenger/i', $ua)) $browser = 'WeChat';
    elseif (preg_match('/edge/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';

    // 获取国家代码 (Geo)
    $country_code = 'ZZ'; // Unknown
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $country_code = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY']);
    } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang_code = strtoupper(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
        $country_code = ($lang_code === 'ZH') ? 'CN' : $lang_code; // Map ZH => CN for compatibility
    }

    // 记录统计 (仅在最终跳转或密码验证页记录)
    try {
        $pdo->prepare("INSERT INTO stats (link_id, target_url, referer, ip_address, user_agent, device_type, os_name, browser_name, country_code, click_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$link_id, $target_url ?: $link['target_url'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['REMOTE_ADDR'], $ua, $device, $os, $browser, $country_code, date('Y-m-d H:i:s')]);
    } catch (Exception $e) {}

    // 如果安全跳转，检查白名单
    $is_safe = false;
    $host = '';
    if ($target_url) {
        $whitelist = array_filter(array_map('trim', explode(',', get_user_option($pdo, $link['user_id'] ?? 0, 'whitelist'))));
        $host = parse_url($target_url, PHP_URL_HOST);
        foreach ($whitelist as $domain) {
            if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -(strlen($domain) + 1)) === ".$domain")) {
                $is_safe = true; break;
            }
        }
    }

    render_saas_ui($mode, $target_url, $text, $is_safe, $host, $link, $pdo);
} else {
    render_saas_ui('error', null, $text, false, '', null, $pdo);
}

function render_saas_ui($mode, $target_url, $text, $is_safe = false, $host = '', $link = null, $pdo = null) {
    global $lang;
    $user_id = $link['user_id'] ?? 0;
    $remove_branding = get_user_option($pdo, $user_id, 'remove_branding') === '1';
    
    // 中转页与主题逻辑
    $transit_type = $link['transit_type'] ?? 'direct';
    $is_interstitial = ($mode === 'redirect' && ($transit_type === 'interstitial' || $transit_type === 'inter'));
    
    // 获取主题设置
    // Simplified Branding Logic (Global User Settings)
    $custom_logo = get_user_option($pdo, $user_id, 'custom_logo');
    $custom_heading = get_user_option($pdo, $user_id, 'custom_heading');
    
    $layout = 'minimal'; // Simplified to default
    $primary_color_hex = '#2563eb'; // Default Blue
    $bg_style = '';

    $logo = $custom_logo ?: '../logo.png';
    $heading = $custom_heading ?: ($is_safe ? $text['heading_safe'] : $text['heading_exit']);
    $desc = $link['transit_desc'] ?: '';
    
    // 逻辑：安全域名跳转时间为3秒，否则5秒
    $timer = $is_safe ? 3 : 5;
    // 逻辑：安全域名跳转时间为3秒，否则5秒
    $timer = $is_safe ? 3 : 5;

    // 背景处理
    $body_bg = "";
    if (strpos($bg_style, 'http') === 0) {
        $body_bg = "background: url('$bg_style') center/cover no-repeat fixed;";
    } elseif (strpos($bg_style, '#') === 0) {
        $body_bg = "background: $bg_style;";
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $lang; ?>">
    <head>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://pandax.mom/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-TX5CNWGV');</script>
        <!-- End Google Tag Manager -->
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php
        $page_title = ($mode == 'redirect') ? $text['title'] : ($lang === 'zh' ? '页面错误' : 'Page Error');
        $remove_branding = get_user_option($pdo, $user_id, 'remove_branding') === '1';
        if (!$remove_branding) $page_title .= ' - KoalaLink';
        ?>
        <title><?php echo $page_title; ?></title>
        <link rel="icon" type="image/png" href="../Favicon.png">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            * { box-sizing: border-box; }
            :root { 
                --primary: <?php echo $primary_color_hex; ?>; 
                --primary-hsl: 221.2 83.2% 53.3%; /* Default blue HSL */
                --safe: #10b981; 
                --warn: #f59e0b; 
                --muted: #64748b; 
            }
            
            body { 
                margin: 0; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                min-height: 100vh; 
                <?php echo $body_bg ?: 'background: #f8fafc;'; ?> 
                font-family: 'Outfit', sans-serif; 
                overflow: hidden;
                position: relative;
            }

            /* Animated Mesh Gradient Background (Only if no custom bg) */
            <?php if (!$body_bg): ?>
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
            <?php endif; ?>

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
            
            .host-badge {
                display: inline-block;
                background: rgba(0,0,0,0.05);
                padding: 0.4rem 1rem;
                border-radius: 2rem;
                font-size: 0.9rem;
                font-weight: 500;
                color: var(--muted);
                margin-bottom: 1.5rem;
            }

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
            }
            
            .btn-direct:hover {
                transform: scale(1.02);
                box-shadow: 0 15px 25px -5px rgba(37, 99, 235, 0.4);
            }

            .custom-desc { 
                font-size: 0.95rem; 
                color: var(--muted);
                background: rgba(0,0,0,0.02); 
                padding: 1.2rem; 
                border-radius: 1rem; 
                margin: 1.5rem 0; 
                border-left: 4px solid var(--primary); 
                text-align: left; 
            }

            /* Layout Overrides */
            <?php if ($layout === 'dark'): ?>
            .card { background: rgba(15, 23, 42, 0.8); border-color: rgba(255,255,255,0.1); color: #f8fafc; }
            h2 { color: #f8fafc; }
            .host-badge { background: rgba(255,255,255,0.1); color: #94a3b8; }
            .tip-box { background: rgba(30, 41, 59, 0.5); border-color: rgba(255,255,255,0.05); }
            .custom-desc { background: rgba(255,255,255,0.05); color: #cbd5e1; }
            .progress-container { background: rgba(255,255,255,0.1); }
            <?php elseif ($layout === 'vibrant'): ?>
            .card { border-radius: 2.5rem; box-shadow: 20px 20px 0px rgba(var(--primary-hsl), 0.1); }
            <?php endif; ?>
        </style>
    </head>
    <body <?php echo ($layout === 'dark') ? 'style="background: #0f172a;"' : ''; ?>>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://pandax.mom/ns.html?id=GTM-TX5CNWGV" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <div class="card <?php echo "layout-$layout"; ?>">
    <div class="logo-container">
            <?php if ($logo && !($remove_branding && strpos($logo, 'logo.png') !== false)): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" onerror="this.src='../logo.png'">
            <?php endif; ?>
        </div>
        
        <?php if ($mode == 'redirect'): ?>
            <h2><?php echo htmlspecialchars($heading); ?></h2>
            
            <!-- Always show tip box per user request -->
            <div class="tip-box <?php echo $is_safe ? 'tip-safe' : 'tip-warn'; ?>">
                <div class="d-flex align-items-start mb-2">
                    <i class="bi <?php echo $is_safe ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2 mt-1"></i>
                    <div><?php echo $is_safe ? $text['tip_safe'] : $text['tip_warn']; ?></div>
                </div>
                <?php if ($host): ?>
                    <div style="margin-top: 0.8rem; padding-top: 0.8rem; border-top: 1px solid rgba(0,0,0,0.05); font-weight: 600; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="opacity: 0.6; font-weight: 400; font-size: 0.85rem;"><?php echo $text['going_to']; ?></span>
                        <code style="background: rgba(0,0,0,0.05); padding: 0.2rem 0.5rem; border-radius: 0.4rem; color: var(--primary); word-break: break-all; font-family: inherit; font-size: 0.95rem;"><?php echo htmlspecialchars($host); ?></code>
                    </div>
                <?php endif; ?>
            </div>

            <div class="progress-container"><div class="progress-bar" id="progressBar"></div></div>
            <p style="font-size:0.9rem; color: var(--muted); font-weight: 500;">
                <?php echo str_replace('5', '<span id="timer" class="fw-bold">'.$timer.'</span>', $text['countdown']); ?>
            </p>

            <?php if ($desc): ?>
                <div class="custom-desc"><?php echo nl2br(htmlspecialchars($desc)); ?></div>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($target_url); ?>" class="btn-direct"><?php echo $text['btn_go']; ?></a>

            <script>
                let timeLeft = <?php echo $timer; ?>;
                const totalTime = timeLeft;
                const progressEl = document.getElementById('progressBar');
                const timerText = document.getElementById('timer');
                
                const countdown = setInterval(() => {
                    timeLeft--;
                    if (timerText) timerText.textContent = timeLeft;
                    progressEl.style.width = ((totalTime - timeLeft) / totalTime * 100) + "%";
                    if (timeLeft <= 0) { 
                        clearInterval(countdown); 
                        window.location.href = "<?php echo $target_url; ?>"; 
                    }
                }, 1000);
                
                // Initial kick
                window.onload = () => {
                    setTimeout(() => {
                        progressEl.style.width = (100 / totalTime) + "%";
                    }, 50);
                };
            </script>

        <?php elseif ($mode == 'password'): ?>
            <h2 style="color:var(--primary);"><i class="bi bi-shield-lock"></i> <?php echo $text['pwd_protected']; ?></h2>
            <form method="POST" class="mt-4">
                <?php if (isset($_POST['auth_pwd'])): ?>
                    <div style="color:red; font-size:0.8rem; margin-bottom:1rem;"><?php echo $text['err_auth']; ?></div>
                <?php endif; ?>
                <input type="password" name="auth_pwd" class="form-control mb-3" style="display:block; width:100%; padding:1rem; border-radius:1rem; border:1px solid #e2e8f0; background:#f8fafc; margin-top:1.5rem; text-align:center; outline:none;" placeholder="<?php echo $text['pwd_placeholder']; ?>" required autofocus>
                <button type="submit" class="btn-direct w-100 border-0" style="cursor:pointer;"><?php echo $text['btn_verify']; ?></button>
            </form>

        <?php else: ?>
            <h2 style="color:var(--warn);"><?php echo ($lang === 'zh' ? '发生错误' : 'Error'); ?></h2>
            <p style="color:var(--muted);"><?php echo $text['invalid']; ?></p>
            <a href="dashboard_pro.php" class="btn-direct" style="background:var(--muted);"><?php echo $text['btn_go']; ?></a>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
}


