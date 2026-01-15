<?php
/**
 * KoalaLink SaaS - Landing Page
 */
session_start();
require_once __DIR__ . '/config.php';

// Detect Language
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'zh' => [
        'title' => 'KoalaLink 专业版 - 企业级短链接解决方案',
        'nav_logo' => 'KoalaLink 专业版',
        'nav_login' => '登录后台',
        'nav_reg' => '免费注册',
        'hero_title' => '让每一个链接更有价值',
        'hero_desc' => 'KoalaLink 为您提供安全、稳定、高效的短链接中转与数据分析服务。支持自定义域名、API 接入及全品牌化定制。',
        'btn_start' => '立即开始',
        'feat_1' => '深度数据洞察',
        'feat_1_desc' => '精准记录点击时间、全球地理分布(地图)、设备(OS/浏览器)占比及来源分析。',
        'feat_2' => '品牌与白标定制',
        'feat_2_desc' => '自定义 Logo、配色、倒计时广告，支持去除官方品牌标识 (White-labeling)。',
        'feat_3' => '智能分流路由',
        'feat_3_desc' => '根据访客设备(iOS/Android)、地理位置自动跳转不同目标，支持 A/B 测试。',
        'feat_4' => '企业级安全防护',
        'feat_4_desc' => '集成 Google Safe Browsing 自动拦截恶意链接，支持 Referer 白名单防盗链。',
        'price_title' => '灵活的订阅方案',
        'price_free' => '免费版',
        'price_vip' => 'VIP 专业版',
        'unit' => '元',
        'month' => '月',
        'quarter' => '季',
        'year' => '年',
        'btn_choose' => '选择此方案',
        'ul_unlimited' => '无限制链接数量',
        'ul_domains' => '自定义域名绑定',
        'ul_branding' => '品牌中心 & 去标',
        'ul_priority' => '优先技术支持',
        'ul_smart' => '智能分流 (设备/地区/AB)',
        'ul_security' => '安全中心 (由 Google 驱动)',
        'ul_analytics' => '深度可视化报表',
        'free_limit' => '仅限 5 个链接',
        'free_analytics' => '基础数据统计',
        'tag_rec' => '超值推荐',
        'faq_title' => '常见问题',
        'faq_q1' => '免费版和VIP版有什么区别？',
        'faq_a1' => '免费版限 5 个链接，仅提供基础统计。VIP版解锁无限链接、智能分流、安全中心、品牌定制、深度分析等全部企业级功能。',
        'faq_q2' => '如何升级到VIP？',
        'faq_a2' => '登录后进入个人面板，点击"开通VIP"选择订阅方案即可。支持月付、季付、年付，也可使用激活码兑换。',
        'faq_q3' => '智能分流是如何工作的？',
        'faq_a3' => '创建链接时可设置不同设备(iOS/Android)、不同地区的访客跳转到不同目标URL，还支持A/B测试按比例分流。',
        'faq_q4' => '数据安全吗？',
        'faq_a4' => '我们集成Google Safe Browsing API自动拦截恶意链接，支持Referer白名单防盗链，所有数据加密存储。',
        'faq_q5' => '可以绑定自己的域名吗？',
        'faq_a5' => 'VIP用户可以绑定自定义域名，通过CNAME解析即可使用您的专属短链接域名。',
        'faq_q6' => '如何使用API？',
        'faq_a6' => '登录后进入"开发平台"页面生成API Key，通过RESTful接口即可自动化创建和管理链接。',
        'footer' => '© 2026 BitkoalaLab. All rights reserved.'
    ],
    'en' => [
        'title' => 'KoalaLink Pro - Enterprise Link Management',
        'nav_logo' => 'KoalaLink Pro',
        'nav_login' => 'Login',
        'nav_reg' => 'Sign Up',
        'hero_title' => 'Make Every Link Count',
        'hero_desc' => 'Secure, reliable key link management with advanced analytics. Support custom domains, API, and full branding.',
        'btn_start' => 'Get Started',
        'feat_1' => 'Deep Analytics',
        'feat_1_desc' => 'Real-time heatmap, device/OS breakdown, trend analysis, and referrer tracking.',
        'feat_2' => 'Branding & White-labeling',
        'feat_2_desc' => 'Customize logo, colors, ads, and remove "Powered by" branding.',
        'feat_3' => 'Smart Routing',
        'feat_3_desc' => 'Target users by Device (iOS/Android), Geo-location, or run A/B Split tests.',
        'feat_4' => 'Enterprise Security',
        'feat_4_desc' => 'Google Safe Browsing integration, Referer Whitelisting, and Link Expiry Fallback.',
        'price_title' => 'Flexible Pricing',
        'price_free' => 'Free Starter',
        'price_vip' => 'VIP Pro',
        'unit' => 'CNY',
        'month' => 'mo',
        'quarter' => 'qtr',
        'year' => 'yr',
        'btn_choose' => 'Choose Plan',
        'ul_unlimited' => 'Unlimited Links',
        'ul_domains' => 'Custom Domains',
        'ul_branding' => 'Branding Center & White-label',
        'ul_priority' => 'Priority Support',
        'ul_smart' => 'Smart Routing (Geo/Device/AB)',
        'ul_security' => 'Security Suite (Safe Browsing)',
        'ul_analytics' => 'Deep Analytics Reports',
        'free_limit' => '5 Links Limit',
        'free_analytics' => 'Basic Analytics',
        'tag_rec' => 'RECOMMENDED',
        'faq_title' => 'Frequently Asked Questions',
        'faq_q1' => 'What is the difference between Free and VIP?',
        'faq_a1' => 'Free tier is limited to 5 links with basic analytics. VIP unlocks unlimited links, Smart Routing, Security Suite, Branding, Deep Analytics, and all enterprise features.',
        'faq_q2' => 'How do I upgrade to VIP?',
        'faq_a2' => 'Login to your dashboard, click Upgrade VIP and choose a subscription plan. We support monthly, quarterly, and yearly billing, or use activation codes.',
        'faq_q3' => 'How does Smart Routing work?',
        'faq_a3' => 'When creating a link, you can set different target URLs for different devices, regions, or run A/B tests with traffic splitting.',
        'faq_q4' => 'Is my data secure?',
        'faq_a4' => 'We integrate Google Safe Browsing API to block malicious links, support Referer whitelisting, and encrypt all data at rest.',
        'faq_q5' => 'Can I use my own domain?',
        'faq_a5' => 'VIP users can bind custom domains via CNAME records to use your own branded short link domain.',
        'faq_q6' => 'How do I use the API?',
        'faq_a6' => 'Login and visit the API Platform page to generate your API Key. Use our RESTful endpoints to automate link creation and management.',
        'footer' => '© 2026 BitkoalaLab. All rights reserved.'
    ]
];
$text = $t[$lang];
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
    <title><?php echo $text['title']; ?></title>
    <link rel="icon" type="image/png" href="../Favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: 221.2 83.2% 53.3%;
            --primary-foreground: 210 40% 98%;
            --background: 210 40% 98%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 84% 4.9%;
            --accent: 210 40% 96.1%;
            --accent-foreground: 222.2 47.4% 11.2%;
            --border: 214.3 31.8% 91.4%;
            --radius: 1rem;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: hsl(var(--background));
            color: hsl(var(--card-foreground));
            overflow-x: hidden;
            background: 
                radial-gradient(at 0% 0%, hsla(221, 83%, 53%, 0.1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(262, 83%, 58%, 0.1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(199, 89%, 48%, 0.1) 0, transparent 50%);
            background-attachment: fixed;
        }

        /* Mesh Gradient Animation */
        .mesh-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            filter: blur(100px);
            opacity: 0.6;
        }

        .mesh-ball {
            position: absolute;
            width: 50vw;
            height: 50vw;
            border-radius: 50%;
            animation: mesh-move 20s infinite alternate ease-in-out;
        }

        @keyframes mesh-move {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(20vw, 10vh) rotate(360deg); }
        }

        /* Glassmorphism */
        .glass-nav {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: var(--radius);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 48px rgba(37, 99, 235, 0.1);
            border-color: hsla(var(--primary), 0.3);
        }

        /* Typography & Layout */
        .hero-section {
            padding: 10rem 0 6rem;
            position: relative;
        }

        .hero-title {
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn-primary-premium {
            background: hsl(var(--primary));
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 10px 20px -5px hsla(var(--primary), 0.4);
        }

        .btn-primary-premium:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px -5px hsla(var(--primary), 0.5);
            color: white;
        }

        .feature-item {
            padding: 2.5rem;
            text-align: left;
        }

        .feature-icon-box {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            background: hsla(var(--primary), 0.1);
            color: hsl(var(--primary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Pricing Specialized */
        .card-pricing.premium-pick {
            border: 2px solid hsl(var(--primary));
            position: relative;
            background: rgba(255, 255, 255, 0.8);
        }

        .premium-pick::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: calc(var(--radius) + 2px);
            background: linear-gradient(135deg, hsla(var(--primary), 1), hsla(262, 83%, 58%, 1));
            z-index: -1;
            opacity: 0.5;
            filter: blur(10px);
        }

        .vip-badge {
            background: linear-gradient(90deg, #f59e0b, #ef4444);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        /* Animations */
        [data-reveal] {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }

        [data-reveal].active {
            opacity: 1;
            transform: translateY(0);
        }

        /* Social Icons in Footer */
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .social-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: hsl(var(--card-foreground));
            font-size: 1.25rem;
            transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
            text-decoration: none;
            backdrop-filter: blur(4px);
        }

        .social-icon:hover {
            background: hsl(var(--primary));
            color: white;
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 10px 20px -5px hsla(var(--primary), 0.4);
            border-color: hsla(var(--primary), 0.5);
        }

        .wechat-popover-content img {
            width: 150px;
            height: 150px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
<!-- Mesh Background Elements -->
<div class="mesh-bg">
    <div class="mesh-ball" style="background: hsla(221, 83%, 53%, 0.4); top: -10%; left: -10%;"></div>
    <div class="mesh-ball" style="background: hsla(262, 83%, 58%, 0.4); bottom: -10%; right: -10%; animation-delay: -5s;"></div>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top glass-nav">
    <div class="container px-4">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="../logo.png" alt="Logo" height="32" class="me-2" onerror="this.style.display='none'">
            <span class="fw-bold text-dark" style="letter-spacing: -0.5px;"><?php echo $text['nav_logo']; ?></span>
        </a>
        <div class="ms-auto">
            <a href="auth.php?action=login" class="btn btn-link text-dark text-decoration-none fw-semibold me-3"><?php echo $text['nav_login']; ?></a>
            <a href="auth.php?action=register" class="btn btn-primary-premium"><?php echo $text['nav_reg']; ?></a>
        </div>
    </div>
</nav>

<!-- Hero -->
<header class="hero-section">
    <div class="container text-center">
        <div data-reveal>
            <h1 class="display-3 hero-title mb-4"><?php echo $text['hero_title']; ?></h1>
            <p class="lead text-muted mb-5 mx-auto" style="max-width: 700px; font-weight: 300; font-size: 1.25rem;">
                <?php echo $text['hero_desc']; ?>
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="auth.php?action=register" class="btn btn-primary-premium btn-lg px-5 shadow-lg">
                    <?php echo $text['btn_start']; ?> <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Features -->
<section class="py-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-md-6 col-lg-3" data-reveal>
                <div class="glass-card feature-item h-100">
                    <div class="feature-icon-box">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h5 class="fw-bold mb-3"><?php echo $text['feat_1']; ?></h5>
                    <p class="text-muted small mb-0"><?php echo $text['feat_1_desc']; ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-reveal style="transition-delay: 0.1s;">
                <div class="glass-card feature-item h-100">
                    <div class="feature-icon-box" style="background: hsla(262, 83%, 58%, 0.1); color: #8b5cf6;">
                        <i class="bi bi-palette"></i>
                    </div>
                    <h5 class="fw-bold mb-3"><?php echo $text['feat_2']; ?></h5>
                    <p class="text-muted small mb-0"><?php echo $text['feat_2_desc']; ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-reveal style="transition-delay: 0.2s;">
                <div class="glass-card feature-item h-100">
                    <div class="feature-icon-box" style="background: hsla(142, 71%, 45%, 0.1); color: #10b981;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5 class="fw-bold mb-3"><?php echo $text['feat_4']; ?></h5>
                    <p class="text-muted small mb-0"><?php echo $text['feat_4_desc']; ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-reveal style="transition-delay: 0.3s;">
                <div class="glass-card feature-item h-100">
                    <div class="feature-icon-box" style="background: hsla(199, 89%, 48%, 0.1); color: #0ea5e9;">
                        <i class="bi bi-code-slash"></i>
                    </div>
                    <h5 class="fw-bold mb-3"><?php echo $text['feat_3']; ?></h5>
                    <p class="text-muted small mb-0"><?php echo $text['feat_3_desc']; ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing -->
<section class="py-5" id="pricing">
    <div class="container py-5">
        <div class="text-center mb-5" data-reveal>
            <h2 class="fw-bold display-5 mb-3"><?php echo $text['price_title']; ?></h2>
        </div>
        <div class="row justify-content-center g-4 align-items-stretch">
            <div class="col-md-5 col-lg-4" data-reveal>
                <div class="glass-card card-pricing p-5 h-100 d-flex flex-column">
                    <div class="text-center mb-4">
                        <h4 class="text-muted fw-normal"><?php echo $text['price_free']; ?></h4>
                        <div class="display-5 fw-bold my-3">0 <span class="fs-6 text-muted">/ <?php echo $text['month']; ?></span></div>
                    </div>
                    <ul class="list-unstyled mb-5 flex-grow-1">
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-check-circle-fill text-success me-3"></i> <?php echo $text['free_limit']; ?></li>
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-check-circle-fill text-success me-3"></i> <?php echo $text['free_analytics']; ?></li>
                    </ul>
                    <a href="auth.php?action=register" class="btn btn-outline-dark border-2 rounded-pill py-2 fw-semibold w-100 mt-auto"><?php echo $text['btn_choose']; ?></a>
                </div>
            </div>
            <div class="col-md-5 col-lg-4" data-reveal style="transition-delay: 0.1s;">
                <div class="glass-card card-pricing premium-pick p-5 h-100 d-flex flex-column position-relative">
                    <div class="vip-badge position-absolute top-0 start-50 translate-middle px-4 py-1 rounded-pill text-white fw-bold small">
                        <?php echo $text['tag_rec']; ?>
                    </div>
                    <div class="text-center mb-4">
                        <h4 class="text-primary fw-bold"><?php echo $text['price_vip']; ?></h4>
                        <div class="my-3">
                            <span class="display-5 fw-bold text-primary"><?php echo PRICE_MONTHLY; ?></span>
                            <span class="fs-6 text-muted">/ <?php echo $text['month']; ?></span>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-4 p-3 mb-4">
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted"><?php echo $text['quarter']; ?></span>
                                <span class="fw-bold text-dark"><?php echo PRICE_QUARTERLY; ?> / <?php echo $text['quarter']; ?></span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted"><?php echo $text['year']; ?></span>
                                <span class="fw-bold text-dark"><?php echo PRICE_YEARLY; ?> / <?php echo $text['year']; ?></span>
                            </div>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-5 flex-grow-1">
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-check-circle-fill text-primary me-3"></i> <?php echo $text['ul_unlimited']; ?></li>
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-check-circle-fill text-primary me-3"></i> <?php echo $text['ul_domains']; ?></li>
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-check-circle-fill text-primary me-3"></i> <?php echo $text['ul_branding']; ?></li>
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-check-circle-fill text-primary me-3"></i> <?php echo $text['ul_smart']; ?></li>
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-shield-lock-fill text-primary me-3"></i> <?php echo $text['ul_security']; ?></li>
                        <li class="mb-3 d-flex align-items-center"><i class="bi bi-bar-chart-fill text-primary me-3"></i> <?php echo $text['ul_analytics']; ?></li>
                    </ul>
                    <a href="auth.php?action=register" class="btn btn-primary-premium py-2 w-100 mt-auto"><?php echo $text['btn_choose']; ?></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="py-5">
    <div class="container py-5">
        <div class="text-center mb-5" data-reveal>
            <h2 class="fw-bold display-6"><?php echo $text['faq_title']; ?></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion accordion-flush" id="faqAccordion">
                    <?php 
                    $faqs = [
                        ['q' => $text['faq_q1'], 'a' => $text['faq_a1']],
                        ['q' => $text['faq_q2'], 'a' => $text['faq_a2']],
                        ['q' => $text['faq_q3'], 'a' => $text['faq_a3']],
                        ['q' => $text['faq_q4'], 'a' => $text['faq_a4']],
                        ['q' => $text['faq_q5'], 'a' => $text['faq_a5']],
                        ['q' => $text['faq_q6'], 'a' => $text['faq_a6']],
                    ];
                    foreach ($faqs as $i => $faq): ?>
                    <div class="accordion-item glass-card mb-3 border-0 overflow-hidden" data-reveal style="transition-delay: <?php echo $i * 0.05; ?>s;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-semibold bg-transparent shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>">
                                <?php echo $faq['q']; ?>
                            </button>
                        </h2>
                        <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small pt-0">
                                <?php echo $faq['a']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="py-5 bg-white bg-opacity-50 text-center text-muted border-top border-white border-opacity-20 backdrop-blur">
    <div class="container">
        <div class="social-links" data-reveal>
            <a href="https://github.com/Bitkoala/KoalaLink" target="_blank" class="social-icon" title="GitHub">
                <i class="bi bi-github"></i>
            </a>
            <a href="https://x.com/Bitekaola" target="_blank" class="social-icon" title="X">
                <i class="bi bi-twitter-x"></i>
            </a>
            <a href="https://t.me/BitekaolaTeam" target="_blank" class="social-icon" title="Telegram">
                <i class="bi bi-telegram"></i>
            </a>
            <a href="https://discord.gg/QbdktDgGm8" target="_blank" class="social-icon" title="Discord">
                <i class="bi bi-discord"></i>
            </a>
            <a href="javascript:void(0);" class="social-icon" id="wechatBtn" title="WeChat">
                <i class="bi bi-wechat"></i>
            </a>
        </div>
        <div class="mb-3">
            <img src="../logo.png" alt="Logo" height="24" class="opacity-50" onerror="this.style.display='none'">
        </div>
        <small class="fw-light"><?php echo $text['footer']; ?></small>
    </div>
</footer>

<!-- WeChat QR Popover Template (Hidden) -->
<div id="wechatPopoverContent" style="display:none;">
    <div class="wechat-popover-content text-center p-2">
        <img src="https://pickoala.com/img/images/2026/01/05/RM45hGH4.webp" alt="WeChat QR Code" class="img-fluid">
        <div class="mt-2 small text-dark">扫描二维码添加微信</div>
    </div>
</div>

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://pandax.mom/ns.html?id=GTM-TX5CNWGV" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript">window.$crisp=[];window.CRISP_WEBSITE_ID="e50e2b74-a90b-4767-8eb5-d6d2447edcb2";(function(){d=document;s=d.createElement("script");s.src="https://client.crisp.chat/l.js";s.async=1;d.getElementsByTagName("head")[0].appendChild(s);})();</script>

<script>
    // Intersection Observer for Reveal on Scroll
    const observerOptions = { threshold: 0.1 };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, observerOptions);

    document.querySelectorAll('[data-reveal]').forEach(el => observer.observe(el));

    // Initialize WeChat Popover
    const wechatBtn = document.getElementById('wechatBtn');
    if (wechatBtn) {
        new bootstrap.Popover(wechatBtn, {
            html: true,
            content: document.getElementById('wechatPopoverContent').innerHTML,
            trigger: 'hover click',
            placement: 'top',
            customClass: 'glass-card border-0 shadow-lg'
        });
    }
</script>
</body>
</html>


