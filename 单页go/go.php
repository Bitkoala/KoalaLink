<?php
/**
 * KoalaLink Nano (单页版 V2.2)
 * ---------------------------------------------------------
 * 功能特性：
 * 1. 混合解析：支持明文 URL 和 Base64 加密 URL 跳转。
 * 2. 国际化：根据浏览器语言自动切换中/英界面。
 * 3. 安全防御：内置引用来源 (Referer) 白名单，防止脚本被盗用。
 * 4. 信任机制：针对白名单域名显示绿色安全提示。
 * 5. 内置工具：直接访问脚本可进入外链生成器。
 * ---------------------------------------------------------
 */

/* =========================================================
   【配置区】在此处管理你的域名白名单和基础设置
   ========================================================= */

// [1] 目标域名白名单：跳转到这些域名时会显示绿色安全提示
$destination_whitelist = [
    'bitekaola.com',
    'bitkoala.net',
    'github.com',
    'pickoala.com'
];

// [2] 来源引用白名单：允许哪些网站调用本跳转脚本 (防止盗链)
// 脚本会自动允许当前服务器域名，无需在此重复添加
$referer_whitelist = [
    'trusted-partner.com',
    'my-other-blog.org'
];

// [3] 是否允许直接访问：如果设为 false，则必须从网页点击进入，直接在地址栏输入将报错
$allow_empty_referer = true;

// [4] 跳转延迟时间 (单位：秒)
// 逻辑：如果是白名单域名 3s，否则 5s
$is_safe_global = false; // 用于后续判断
$redirect_delay = 5; 


/* =========================================================
   【安全校验区】处理引用来源验证
   ========================================================= */

$current_host = $_SERVER['HTTP_HOST'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$referer_host = !empty($referer) ? parse_url($referer, PHP_URL_HOST) : '';

if (!empty($referer_host)) {
    // 判定逻辑：如果是本站域名，或者在白名单数组中，则通过
    $is_allowed_referer = ($referer_host === $current_host);
    
    if (!$is_allowed_referer) {
        foreach ($referer_whitelist as $allowed_ref) {
            if ($referer_host === $allowed_ref || (strlen($referer_host) > strlen($allowed_ref) && substr($referer_host, -(strlen($allowed_ref) + 1)) === ".$allowed_ref")) {
                $is_allowed_referer = true;
                break;
            }
        }
    }

    if (!$is_allowed_referer) {
        header('HTTP/1.1 403 Forbidden');
        exit("Access Denied: Your domain ($referer_host) is not allowed to use this redirector.");
    }
} elseif (!$allow_empty_referer) {
    header('HTTP/1.1 403 Forbidden');
    exit("Access Denied: Direct access is not allowed.");
}


/* =========================================================
   【语言包区】多语言文本管理 (i18n)
   ========================================================= */

$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'zh' => [
        'title'           => '安全跳转 - KoalaLink Nano',
        'generator_title' => 'KoalaLink Nano 加密生成器',
        'heading_exit'    => '即将离开 KoalaLink Nano',
        'heading_safe'    => '安全验证通过',
        'tip_warn'        => '<strong>安全警示：</strong>您当前访问的是外部链接，请核实对方身份，保护好个人隐私。',
        'tip_safe'        => '<strong>官方推荐：</strong> 此链接指向受信任的合作伙伴或比特考拉Bitekaola官方项目，通过安全验证，请放心访问。',
        'going_to'        => '正在前往：',
        'countdown'       => '将在 <span class="countdown-num" id="timer">'.$redirect_delay.'</span> 秒后自动安全跳转',
        'btn_go'          => '立即前往',
        'btn_gen'         => '立即生成',
        'invalid'         => '无效的跳转链接',
        'gen_placeholder' => '请输入原始网址 (例如 https://...)',
        'gen_res'         => '生成结果：'
    ],
    'en' => [
        'title'           => 'Security Redirect - KoalaLink Nano',
        'generator_title' => 'KoalaLink Nano Encryptor',
        'heading_exit'    => 'Leaving KoalaLink Nano',
        'heading_safe'    => 'Security Verified',
        'tip_warn'        => '<strong>Security Warning:</strong> You are visiting an external link. Please verify the identity and protect your privacy.',
        'tip_safe'        => '<strong>Trusted Link:</strong> This URL points to a trusted partner or official project. It is safe to proceed.',
        'going_to'        => 'Destination:',
        'countdown'       => 'Redirecting in <span class="countdown-num" id="timer">'.$redirect_delay.'</span> seconds',
        'btn_go'          => 'Proceed Now',
        'btn_gen'         => 'Generate',
        'invalid'         => 'Invalid Destination URL',
        'gen_placeholder' => 'Enter original URL (e.g., https://...)',
        'gen_res'         => 'Result:'
    ]
];
$text = $t[$lang];


/* =========================================================
   【核心逻辑区】解析输入参数并识别目标
   ========================================================= */

$input_url = $_GET['url'] ?? '';
$target_url = '';

if ($input_url) {
    // 逻辑：优先尝试 Base64 解码，失败则按原样处理
    $decoded = base64_decode(str_replace(' ', '+', $input_url), true);
    if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
        $target_url = $decoded;
    } 
    elseif (filter_var($input_url, FILTER_VALIDATE_URL)) {
        $target_url = $input_url;
    }
}

// 判定：是否有合法的跳转目标
$is_redirect = !empty($target_url);
$host = $is_redirect ? parse_url($target_url, PHP_URL_HOST) : '';

// 判定：目标是否在白名单中
$is_safe = false;
foreach ($destination_whitelist as $domain) {
    if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -(strlen($domain) + 1)) === ".$domain")) {
        $is_safe = true;
        break;
    }
}
$is_safe_global = $is_safe;
$redirect_delay = $is_safe ? 3 : 5;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_redirect ? $text['title'] : $text['generator_title']; ?></title>
    <link rel="icon" type="image/png" href="Favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb; 
            --safe: #10b981;    
            --warn: #f59e0b;    
            --bg: #f8fafc;
            --text: #1e293b;
        }

        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg);
            font-family: 'Outfit', -apple-system, system-ui, sans-serif;
            color: var(--text);
            overflow: hidden;
            position: relative;
        }

        /* Mesh Gradient Animation */
        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(at 0% 0%, hsla(221, 83%, 53%, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, hsla(262, 83%, 58%, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, hsla(216, 91%, 60%, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, hsla(242, 100%, 70%, 0.1) 0px, transparent 50%);
            filter: blur(80px);
            z-index: -1;
            animation: meshMove 20s ease infinite alternate;
        }

        @keyframes meshMove {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.1) translate(2%, 2%); }
        }

        .card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 3rem 2.5rem;
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 460px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .logo-container { margin-bottom: 2rem; }
        .logo-container img { max-width: 180px; max-height: 80px; object-fit: contain; }

        h2 { font-weight: 700; font-size: 1.5rem; margin-bottom: 1.5rem; letter-spacing: -0.5px; }

        .tip-box {
            padding: 1.25rem;
            border-radius: 1.2rem;
            margin-bottom: 2rem;
            text-align: left;
            font-size: 0.95rem;
            line-height: 1.6;
            border: 1px solid transparent;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
        }
        .tip-warn { background: #fff7ed; border-color: #ffedd5; color: #9a3412; }
        .tip-safe { background: #f0fdf4; border-color: #dcfce7; color: #166534; }
        .safe-heading { color: var(--safe) !important; }

        .domain-display {
            display: block;
            background: rgba(255, 255, 255, 0.5);
            padding: 0.6rem 1rem;
            border-radius: 0.75rem;
            color: var(--primary);
            font-weight: 600;
            margin-top: 0.5rem;
            border: 1px dashed rgba(37, 99, 235, 0.2);
            word-break: break-all;
            font-size: 0.9rem;
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
        }
        .is-safe-bar { background: var(--safe); }

        .btn-direct {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 1rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 1rem;
            font-weight: 700;
            transition: all 0.3s;
            border: none;
            width: 100%;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }
        .btn-safe { background: var(--safe); box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3); }
        .btn-direct:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 15px 20px -5px rgba(0, 0, 0, 0.1); }

        .input-field {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 1rem;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.5);
            font-family: inherit;
            margin-bottom: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }
        .input-field:focus { border-color: var(--primary); }
        
        .result-box {
            margin-top: 2rem;
            padding: 1.25rem;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
            word-break: break-all;
            display: none;
            text-align: left;
        }
    </style>
</head>
<body>

<div class="card">
    <!-- Logo 区域 -->
    <div class="logo-container">
        <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
    </div>

    <?php if ($is_redirect): ?>
        <!-- Redirect UI -->
        <h2 class="<?php echo $is_safe ? 'safe-heading' : ''; ?>">
            <?php echo $is_safe ? $text['heading_safe'] : $text['heading_exit']; ?>
        </h2>

        <div class="tip-box <?php echo $is_safe ? 'tip-safe' : 'tip-warn'; ?>">
            <i class="bi <?php echo $is_safe ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill'; ?> fs-4"></i>
            <div>
                <?php echo $is_safe ? $text['tip_safe'] : $text['tip_warn']; ?>
                <span class="domain-display"><?php echo htmlspecialchars($host); ?></span>
            </div>
        </div>

        <div class="progress-container">
            <div class="progress-bar <?php echo $is_safe ? 'is-safe-bar' : ''; ?>" id="progressBar"></div>
        </div>
        
        <p style="color:var(--text); font-size:0.95rem; opacity: 0.8; font-weight: 500;">
            <?php echo $text['countdown']; ?>
        </p>

        <a href="<?php echo htmlspecialchars($target_url); ?>" class="btn-direct <?php echo $is_safe ? 'btn-safe' : ''; ?>">
            <i class="bi bi-box-arrow-up-right"></i> <?php echo $text['btn_go']; ?>
        </a>

    <?php else: ?>
        <!-- Generator UI -->
        <h2 class="mb-4 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-shield-lock-fill text-primary"></i>
            <?php echo $text['generator_title']; ?>
        </h2>
        <div class="mb-3 text-start">
            <label class="form-label small fw-bold opacity-75 mb-2 ml-1">ORIGINAL URL</label>
            <input type="url" id="rawUrl" class="input-field" placeholder="<?php echo $text['gen_placeholder']; ?>">
        </div>
        <button class="btn-direct" onclick="generateLink()">
            <i class="bi bi-magic"></i> <?php echo $text['btn_gen']; ?>
        </button>
        
        <div id="resultBox" class="result-box">
            <label class="d-block small fw-bold opacity-75 mb-2"><?php echo $text['gen_res']; ?></label>
            <div id="finalLink" style="font-family: 'Outfit', monospace; font-size: 0.85rem; background: rgba(255,255,255,0.8); padding: 1rem; border-radius: 0.75rem; border: 1px solid rgba(0,0,0,0.05); user-select: all; cursor: pointer; color: var(--primary);"></div>
            <p class="small text-muted mt-2 mb-0 text-center"><i class="bi bi-info-circle"></i> Click to copy the link</p>
        </div>
    <?php endif; ?>
</div>

<script>
    <?php if ($is_redirect): ?>
    /**
     * 跳转页逻辑：倒计时与进度条
     */
    let timeLeft = <?php echo $redirect_delay; ?>;
    const totalTime = <?php echo $redirect_delay; ?>;
    const progressEl = document.getElementById('progressBar');
    
    const countdown = setInterval(() => {
        timeLeft--;
        const timerDoc = document.getElementById('timer');
        if (timerDoc) timerDoc.textContent = timeLeft;
        
        // 计算进度：((总时间 - 剩余时间) / 总时间) * 100
        progressEl.style.width = ((totalTime - timeLeft) / totalTime * 100) + "%";
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            window.location.href = "<?php echo $target_url; ?>";
        }
    }, 1000);
    
    // 初始启动微量进度
    window.onload = () => setTimeout(() => progressEl.style.width = (100 / totalTime) + "%", 50);

    <?php else: ?>
    /**
     * 生成器逻辑：将 URL 转为 Base64
     */
    function generateLink() {
        const rawUrl = document.getElementById('rawUrl').value.trim();
        if (!rawUrl.startsWith('http')) return alert('请输入完整的网址 (http/https)');
        
        // 使用 btoa 进行加密
        const encoded = btoa(unescape(encodeURIComponent(rawUrl)));
        const finalUrl = window.location.href.split('?')[0] + '?url=' + encoded;
        
        document.getElementById('finalLink').textContent = finalUrl;
        document.getElementById('resultBox').style.display = 'block';
    }
    <?php endif; ?>
</script>

</body>
</html>