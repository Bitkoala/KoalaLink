<?php
/**
 * KoalaLink SaaS - Developer API
 * 
 * Authentication: Header 'X-API-KEY'
 */
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$pdo = init_saas_db();
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (!$api_key) {
    http_response_code(401);
    echo json_encode(['error' => 'API Key required']);
    exit;
}

// 验证 API KEY
$stmt = $pdo->prepare("SELECT id, username, user_tier FROM users WHERE api_key = ?");
$stmt->execute([$api_key]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API Key']);
    exit;
}

$user_id = $user['id'];
$user_tier = $user['user_tier'];
$limit = defined('FREE_LINK_LIMIT') ? FREE_LINK_LIMIT : 5;

// 获取请求数据
$inputJSON = json_decode(file_get_contents('php://input'), true) ?? [];
// 合并 JSON 数据与 POST 数据，支持多种调用方式
$input = array_merge($_POST, $inputJSON);

// 动作检测: 优先考虑路径，其次是 action 参数
$uri = $_SERVER['REQUEST_URI'];
$action = $_GET['action'] ?? '';

if (strpos($uri, '/api/v1/links') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = 'create';
    } else {
        $action = 'list';
    }
}

switch ($action) {
    case 'create':
        // 检查配额
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current_count = $stmt->fetchColumn();

        if ($user_tier === 'free' && $current_count >= $limit) {
            http_response_code(403);
            echo json_encode(['error' => "Usage limit reached ($limit). Please upgrade to VIP."]);
            exit;
        }

        $target_url = $input['target_url'] ?? '';
        $slug = trim($input['slug'] ?? substr(md5(uniqid(mt_rand(), true)), 0, 6));

        if (!$target_url) {
            http_response_code(400);
            echo json_encode(['error' => 'target_url is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO links (user_id, slug, target_url) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $slug, $target_url]);
            
            $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $short_url = $proto . "://" . PRIMARY_DOMAIN . "/go_pro.php?to=" . $slug;
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'slug' => $slug,
                    'target_url' => $target_url,
                    'short_url' => $short_url
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create link: ' . $e->getMessage()]);
        }
        break;

    case 'list':
        $stmt = $pdo->prepare("SELECT slug, target_url, status, created_at FROM links WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $links]);
        break;

    case 'stats':
        $slug = $_GET['slug'] ?? '';
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'slug is required for stats']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM links WHERE user_id = ? AND slug = ?");
        $stmt->execute([$user_id, $slug]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            http_response_code(404);
            echo json_encode(['error' => 'Link not found']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as clicks, 
            COUNT(DISTINCT ip_address) as unique_visitors,
            (SELECT device_type FROM stats WHERE link_id = ? GROUP BY device_type ORDER BY COUNT(*) DESC LIMIT 1) as top_device 
            FROM stats WHERE link_id = ?");
        $stmt->execute([$link['id'], $link['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Supported: create, list, stats']);
        break;
}
