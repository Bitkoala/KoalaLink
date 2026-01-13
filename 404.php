<?php
/**
 * KoalaLink Lite 404 Error Page
 * 功能：品牌化 404 错误提示页，支持 i18n
 */

// --- [ 1. 语言检测 (i18n) ] ---
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'zh' => [
        'error_code' => '404',
        'title' => '页面未找到 - KoalaLink Lite',
        'heading' => '抱歉，页面未找到',
        'message' => '您访问的资源可能已被移除、更名或暂时不可用。',
        'btn_home' => '返回首页',
        'btn_back' => '返回上一页'
    ],
    'en' => [
        'error_code' => '404',
        'title' => '404 Not Found - KoalaLink Lite',
        'heading' => 'Oops! Page Not Found',
        'message' => 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.',
        'btn_home' => 'Go to Homepage',
        'btn_back' => 'Go Back'
    ]
];
$text = $t[$lang];

header("HTTP/1.1 404 Not Found");
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $text['title']; ?></title>
    <link rel="icon" type="image/png" href="Favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
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
            color: var(--text);
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(at 0% 0%, hsla(221, 83%, 53%, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, hsla(262, 83%, 58%, 0.1) 0px, transparent 50%);
            filter: blur(100px);
            z-index: -1;
        }

        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            padding: 4rem 3rem;
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 480px;
            text-align: center;
        }

        .logo-container {
            margin-bottom: 2rem;
        }

        .logo-container img {
            max-width: 180px;
            max-height: 70px;
            object-fit: contain;
        }

        .error-code {
            font-size: 7rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
            letter-spacing: -2px;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 1rem;
            color: var(--text);
        }

        p {
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
            font-weight: 400;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn {
            padding: 0.8rem 1.8rem;
            border-radius: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--text);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.95;
        }

        @media (max-width: 480px) {
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="logo-container">
        <img src="logo.png" alt="KoalaLink" onerror="this.src='saas/logo.png'">
    </div>

    <div class="error-code"><?php echo $text['error_code']; ?></div>
    
    <h1><?php echo $text['heading']; ?></h1>
    
    <p>
        <?php echo $text['message']; ?>
    </p>

    <div class="btn-group">
        <a href="admin.php" class="btn btn-primary"><?php echo $text['btn_home']; ?></a>
        <button onclick="history.back()" class="btn btn-outline"><?php echo $text['btn_back']; ?></button>
    </div>
</div>

</body>
</html>
