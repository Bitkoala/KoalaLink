<?php
/**
 * KoalaLink Lite Analytics
 * 功能：流量数据可视化、来源分析
 */
session_start();
date_default_timezone_set('Asia/Shanghai');
// require_once __DIR__ . '/go_lite.php'; // Decoupled
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
            return $pdo;
        } catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
    }
}

// 鏉冮檺鏍￠獙
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

$pdo = init_db($db_path);

// --- [ 0. 璇█妫€娴?(i18n) ] ---
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

$t = [
    'zh' => [
        'global_title' => '全局流量统计',
        'link_title' => '外链统计',
        'btn_back' => '返回管理',
        'nav_admin' => '管理后台',
        'trend_title' => '24小时访问趋势',
        'trend_label' => '点击量',
        'ref_title' => '主要访问来源',
        'recent_title' => '最近访问记录',
        'dynamic_title' => '热门动态跳转 (加密/直接)',
        'table_time' => '时间',
        'table_type' => '标识/类型',
        'table_target' => '目标 URL',
        'table_ip' => 'IP地址',
        'table_source' => '来源/系统',
        'table_clicks' => '本月点击',
        'type_dynamic' => '动态跳转',
        'ref_direct' => '直接访问'
    ],
    'en' => [
        'global_title' => 'Global Traffic Analytics',
        'link_title' => 'Link Analytics',
        'btn_back' => 'Back to Admin',
        'nav_admin' => 'Admin Panel',
        'trend_title' => '24h Traffic Trend',
        'trend_label' => 'Clicks',
        'ref_title' => 'Top Traffic Sources',
        'recent_title' => 'Recent Access Logs',
        'dynamic_title' => 'Popular Dynamic Redirects',
        'table_time' => 'Time',
        'table_type' => 'Alias/Type',
        'table_target' => 'Target URL',
        'table_ip' => 'IP Address',
        'table_source' => 'Source/OS',
        'table_clicks' => 'Monthly Clicks',
        'type_dynamic' => 'Dynamic',
        'ref_direct' => 'Direct/None'
    ]
];
$text = $t[$lang];

$link_id = $_GET['id'] ?? 0;

// 鑾峰彇閾炬帴淇℃伅
if ($link_id) {
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
    $stmt->execute([$link_id]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$link) die("Link not found.");
    $title = $text['link_title'] . ": /" . $link['slug'];
} else {
    $title = $text['global_title'];
}

// 缁熻 A: 24灏忔椂娴侀噺瓒嬪娍
$trend_sql = "SELECT strftime('%H:00', click_time) as hour, COUNT(*) as count 
              FROM stats " . ($link_id ? "WHERE link_id = $link_id" : "") . " 
              GROUP BY hour ORDER BY hour ASC";
$trend_data = $pdo->query($trend_sql)->fetchAll(PDO::FETCH_ASSOC);

// 缁熻 B: 鏉ユ簮鍒嗗竷 (Referer)
$ref_sql = "SELECT referer, COUNT(*) as count FROM stats " . 
           ($link_id ? "WHERE link_id = $link_id" : "") . " 
           GROUP BY referer ORDER BY count DESC LIMIT 10";
$ref_data = $pdo->query($ref_sql)->fetchAll(PDO::FETCH_ASSOC);

$recent_sql = "SELECT s.*, l.slug FROM stats s 
               LEFT JOIN links l ON s.link_id = l.id " . 
               ($link_id ? "WHERE s.link_id = $link_id" : "") . " 
               ORDER BY s.click_time DESC LIMIT 50";
$recent_logs = $pdo->query($recent_sql)->fetchAll(PDO::FETCH_ASSOC);

// 缁熻 D: 鐑棬鍔ㄦ€佽烦杞?(浠呭湪鍏ㄥ眬妯″紡鏄剧ず)
$dynamic_stats = [];
if (!$link_id) {
    $dynamic_sql = "SELECT target_url, COUNT(*) as count FROM stats 
                    WHERE link_id IS NULL OR link_id = 0 
                    GROUP BY target_url ORDER BY count DESC LIMIT 10";
    $dynamic_stats = $pdo->query($dynamic_sql)->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - KoalaLink Lite</title>
    <link rel="icon" type="image/png" href="Favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(at 0% 0%, hsla(221, 83%, 53%, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, hsla(262, 83%, 58%, 0.05) 0px, transparent 50%);
            filter: blur(100px);
            z-index: -1;
            pointer-events: none;
        }

        .navbar { 
            background: rgba(255, 255, 255, 0.8); 
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 0;
        }
        
        .card { 
            border: 1px solid rgba(255, 255, 255, 0.5); 
            border-radius: 1.5rem; 
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); 
        }

        .table thead th {
            background: rgba(0,0,0,0.02);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-top: none;
            padding: 1rem;
        }
        .table tbody td { padding: 1rem; }
        
        .badge-link {
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top mb-5">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="admin.php">
            <img src="logo.png" alt="KoalaLink" height="35" class="me-2" onerror="this.src='saas/logo.png'">
            <span class="fw-bold text-primary"><?php echo $text['nav_admin']; ?></span>
        </a>
        <a href="admin.php" class="btn btn-primary-light text-primary fw-bold rounded-pill px-4 btn-sm">
            <i class="bi bi-arrow-left me-1"></i> <?php echo $text['btn_back']; ?>
        </a>
    </div>
</nav>

<div class="container pb-5">
    <h2 class="mb-5 text-center fw-bold text-dark"><?php echo $title; ?></h2>

    <div class="row g-4 mb-5">
        <!-- Traffic Trend Chart -->
        <div class="col-md-8">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up text-primary me-2"></i><?php echo $text['trend_title']; ?></h5>
                    <span class="badge bg-primary-light text-primary rounded-pill px-3"><?php echo array_sum(array_column($trend_data, 'count')); ?> total</span>
                </div>
                <canvas id="trendChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        <!-- Sources Chart -->
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-4"><i class="bi bi-pie-chart-fill text-info me-2"></i><?php echo $text['ref_title']; ?></h5>
                <canvas id="refChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Logs Table -->
    <div class="card overflow-hidden border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i><?php echo $text['recent_title']; ?></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4"><?php echo $text['table_time']; ?>_</th>
                        <th><?php echo $text['table_type']; ?></th>
                        <th><?php echo $text['table_target']; ?></th>
                        <th><?php echo $text['table_ip']; ?></th>
                        <th class="pe-4"><?php echo $text['table_source']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_logs as $log): ?>
                    <tr>
                        <td class="ps-4 small text-muted font-monospace"><?php echo date('m-d H:i', strtotime($log['click_time'])); ?></td>
                        <td>
                            <?php if($log['slug']): ?>
                                <span class="badge-link bg-primary text-white">/<?php echo htmlspecialchars($log['slug']); ?></span>
                            <?php else: ?>
                                <span class="badge-link bg-info text-white"><?php echo $text['type_dynamic']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($log['target_url']); ?>">
                                <a href="<?php echo htmlspecialchars($log['target_url']); ?>" target="_blank" class="text-decoration-none text-primary fw-medium"><?php echo htmlspecialchars($log['target_url']); ?></a>
                            </div>
                        </td>
                        <td class="small text-muted font-monospace"><?php echo $log['ip_address']; ?></td>
                        <td class="pe-4">
                            <div class="text-truncate small text-dark fw-medium" style="max-width: 150px;" title="<?php echo htmlspecialchars($log['referer'] ?: $text['ref_direct']); ?>">
                                <i class="bi bi-link-45deg"></i> <?php echo htmlspecialchars($log['referer'] ?: $text['ref_direct']); ?>
                            </div>
                            <div class="text-muted text-truncate" style="max-width: 180px; font-size: 0.75rem;" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                <?php echo htmlspecialchars($log['user_agent']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if(!$link_id && !empty($dynamic_stats)): ?>
    <!-- 鍔ㄦ€佽烦杞粺璁?-->
    <div class="card mt-4 overflow-hidden">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><?php echo $text['dynamic_title']; ?></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?php echo $text['table_target']; ?></th>
                        <th class="text-center"><?php echo $text['table_clicks']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dynamic_stats as $ds): ?>
                    <tr>
                        <td class="small"><?php echo htmlspecialchars($ds['target_url']); ?></td>
                        <td class="text-center"><span class="badge bg-info"><?php echo $ds['count']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($trend_data, 'hour')); ?>,
        datasets: [{
            label: '<?php echo $text['trend_label']; ?>',
            data: <?php echo json_encode(array_column($trend_data, 'count')); ?>,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#2563eb',
            borderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { 
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1, font: { family: 'Outfit' } } },
            x: { grid: { display: false }, ticks: { font: { family: 'Outfit' } } }
        }
    }
});

// Sources Chart
const refCtx = document.getElementById('refChart').getContext('2d');
new Chart(refCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($v) use ($text){ return $v ?: $text['ref_direct']; }, array_column($ref_data, 'referer'))); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($ref_data, 'count')); ?>,
            backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#64748b'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { position: 'bottom', labels: { font: { family: 'Outfit', size: 12 }, padding: 20 } } 
        },
        cutout: '70%'
    }
});
</script>

</body>
</html>

