<?php
/**
 * KoalaLink Pro - Multi-tenant Dashboard
 */
session_start();
date_default_timezone_set('Asia/Shanghai');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['saas_user_id'])) {
    header("Location: auth.php");
    exit;
}

$user_id = $_SESSION['saas_user_id'];
$username = $_SESSION['saas_username'];
$view = $_GET['view'] ?? 'links'; // 视图切换: links, stats, api, domains, design
$pdo = init_saas_db($db_path);
$message = '';
// --- [ 1. 语言检测 (i18n) ] ---
$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
$lang = (strpos($accept_lang, 'zh') !== false) ? 'zh' : 'en';

// --- [ 1.0 用户数据与限额 ] ---
$stmt = $pdo->prepare("SELECT user_tier, vip_expiry, api_key, is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_tier = $u_data['user_tier'] ?? 'free';
$vip_expiry = $u_data['vip_expiry'] ?? 'N/A';
$api_key = $u_data['api_key'] ?? '';
$is_admin = (int)($u_data['is_admin'] ?? 0);
$limit = ($user_tier === 'vip') ? 10000 : 10; // 免费版限额

// 获取用户自定义域名 (VIP Only)
$user_domains = [];
if ($user_tier === 'vip') {
    // 假设 status 1 为已验证
    $stmt = $pdo->prepare("SELECT domain FROM custom_domains WHERE user_id = ? AND status = 1");
    $stmt->execute([$user_id]);
    $user_domains = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 数据容器初始化 (防止 Undefined Variable)
$links = [];
$trend_data = [];
$geo_stats = [];
$device_stats = [];
$os_stats = [];
$browser_stats = [];
$referer_stats = [];

// 全局获取链接列表
$stmt = $pdo->prepare("SELECT * FROM links WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- [ 1.1 不同视图的数据获取 ] ---
if ($view === 'stats') {
    $link_ids = array_column($links, 'id');
    $selected_link_id = isset($_GET['link_id']) && is_numeric($_GET['link_id']) ? (int)$_GET['link_id'] : 0;
    
    // Filter logic: If a specific link is selected and belongs to user
    $target_ids = [];
    if ($selected_link_id > 0 && in_array($selected_link_id, $link_ids)) {
        $target_ids = [$selected_link_id];
    } else {
        $target_ids = $link_ids;
    }

    if (!empty($target_ids)) {
        $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
        
        // 1. 最近 7 天趋势
        $stmt = $pdo->prepare("SELECT date(click_time) as d, count(*) as c FROM stats WHERE link_id IN ($placeholders) AND click_time >= date('now', '-7 days') GROUP BY d");
        $stmt->execute($target_ids);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { $trend_data[$r['d']] = (int)$r['c']; }
        
        // 2. 地理分布 (使用 country_code)
        $stmt = $pdo->prepare("SELECT country_code as country, count(*) as c FROM stats WHERE link_id IN ($placeholders) GROUP BY country_code");
        $stmt->execute($target_ids);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { 
            if($r['country']) {
                $code = ($r['country'] === 'ZH') ? 'CN' : $r['country']; // Fix ZH -> CN
                if (!isset($geo_stats[$code])) $geo_stats[$code] = 0;
                $geo_stats[$code] += (int)$r['c'];
            }
        }
        
        // 3. 设备分布
        $stmt = $pdo->prepare("SELECT device_type, count(*) as c FROM stats WHERE link_id IN ($placeholders) GROUP BY device_type");
        $stmt->execute($target_ids);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { if($r['device_type']) $device_stats[$r['device_type']] = (int)$r['c']; }

        // 4. OS 分布
        $stmt = $pdo->prepare("SELECT os_name, count(*) as c FROM stats WHERE link_id IN ($placeholders) GROUP BY os_name");
        $stmt->execute($target_ids);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { if($r['os_name']) $os_stats[$r['os_name']] = (int)$r['c']; }

        // 5. 浏览器分布
        $stmt = $pdo->prepare("SELECT browser_name, count(*) as c FROM stats WHERE link_id IN ($placeholders) GROUP BY browser_name");
        $stmt->execute($target_ids);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) { if($r['browser_name']) $browser_stats[$r['browser_name']] = (int)$r['c']; }

        // 6. Referer 分布
        $stmt = $pdo->prepare("SELECT referer, count(*) as c FROM stats WHERE link_id IN ($placeholders) GROUP BY referer ORDER BY c DESC LIMIT 20");
        $stmt->execute($target_ids);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ref = empty($r['referer']) ? 'Direct / Unknown' : (parse_url($r['referer'], PHP_URL_HOST) ?: $r['referer']);
            if (!isset($referer_stats[$ref])) $referer_stats[$ref] = 0;
            $referer_stats[$ref] += (int)$r['c'];
        }
        arsort($referer_stats);
        $referer_stats = array_slice($referer_stats, 0, 10);
    }
}

// --- [ 1.1.1 Admin View Data ] ---
if ($view === 'admin' && $is_admin) {
    // System Stats
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_links = $pdo->query("SELECT COUNT(*) FROM links")->fetchColumn();
    
    // User List with Link Counts
    $stmt = $pdo->query("SELECT u.id, u.username, u.user_tier, u.status, u.created_at, (SELECT COUNT(*) FROM links l WHERE l.user_id = u.id) as link_count FROM users u ORDER BY u.created_at DESC");
    $admin_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Links across all users
    $stmt = $pdo->query("SELECT l.slug, l.target_url, u.username, COUNT(s.id) as clicks FROM links l LEFT JOIN users u ON l.user_id = u.id LEFT JOIN stats s ON l.id = s.link_id GROUP BY l.id ORDER BY clicks DESC LIMIT 10");
    $admin_top_links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // System Security Settings
    $sys_allowed_referers = get_user_option($pdo, 0, 'allowed_referers');
    $sys_whitelist = get_user_option($pdo, 0, 'whitelist');
    $sys_sb_key = get_user_option($pdo, 0, 'safe_browsing_key');

    // Activation Codes
    $stmt = $pdo->query("SELECT * FROM activation_codes ORDER BY created_at DESC LIMIT 20");
    $admin_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper: Check URL Safety (Google Safe Browsing)
function check_url_safety($pdo, $url) {
    $api_key = get_user_option($pdo, 0, 'safe_browsing_key');
    if (empty($api_key)) return true; // Fail open if no key

    $api_url = "https://safebrowsing.googleapis.com/v4/threatMatches:find?key=$api_key";
    $data = [
        'client' => ['clientId' => 'koalalink', 'clientVersion' => '1.0.0'],
        'threatInfo' => [
            'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE'],
            'platformTypes' => ['ANY_PLATFORM'],
            'threatEntryTypes' => ['URL'],
            'threatEntries' => [['url' => $url]]
        ]
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (!empty($result['matches'])) {
            return false; // THREAT FOUND
        }
    }
    return true; // Safe or Check Failed (Fail Open)
}

// --- [ 1.2 POST Actions Handler ] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Reset API Key
    if ($action === 'reset_api') {
        $new_key = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("UPDATE users SET api_key = ? WHERE id = ?");
        $stmt->execute([$new_key, $user_id]);
        header("Location: dashboard_pro.php?view=api&msg=api_reset");
        exit;
    }

    // 2. Toggle Link Status
    if (isset($_GET['toggle'])) {
        $lid = (int)$_GET['toggle'];
        $stmt = $pdo->prepare("UPDATE links SET is_active = 1 - is_active WHERE id = ? AND user_id = ?");
        $stmt->execute([$lid, $user_id]);
        header("Location: dashboard_pro.php?msg=updated");
        exit;
    }

    // 3. Delete Link
    if (isset($_GET['delete'])) {
        $lid = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
        $stmt->execute([$lid, $user_id]);
        header("Location: dashboard_pro.php?msg=deleted");
        exit;
    }

    // 4. Add Custom Domain
    if ($action === 'add_domain' && $user_tier === 'vip') {
        $domain = trim($_POST['domain']);
        if ($domain) {
            $stmt = $pdo->prepare("INSERT INTO custom_domains (user_id, domain, status) VALUES (?, ?, 0)");
            $stmt->execute([$user_id, $domain]);
            header("Location: dashboard_pro.php?view=domains&msg=added");
            exit;
        }
    }

    // 5. Delete Custom Domain
    if (isset($_GET['del_domain']) && $user_tier === 'vip') {
        $did = (int)$_GET['del_domain'];
        $stmt = $pdo->prepare("DELETE FROM custom_domains WHERE id = ? AND user_id = ?");
        $stmt->execute([$did, $user_id]);
        header("Location: dashboard_pro.php?view=domains&msg=deleted");
        exit;
    }

    // 6. Add Branding Theme
    if ($action === 'add_theme' && $user_tier === 'vip') {
        $name = trim($_POST['name']);
        $layout = $_POST['layout_type'] ?? 'minimal';
        $color = $_POST['primary_color'] ?? '#2563eb';
        $logo = $_POST['logo_url'] ?? '';
        $text_val = $_POST['custom_text'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO themes (user_id, name, layout_type, primary_color, logo_url, custom_text) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $layout, $color, $logo, $text_val]);
        header("Location: dashboard_pro.php?view=design&msg=added");
        exit;
    }

    // 7. Delete Theme
    if (isset($_GET['del_theme']) && $user_tier === 'vip') {
        $tid = (int)$_GET['del_theme'];
        $stmt = $pdo->prepare("DELETE FROM themes WHERE id = ? AND user_id = ?");
        $stmt->execute([$tid, $user_id]);
        header("Location: dashboard_pro.php?view=design&msg=deleted");
        exit;
    }

    // 8. Update Security Settings
    if ($action === 'update_security') {
        $referers = trim($_POST['allowed_referers']);
        $whitelist = trim($_POST['whitelist']);
        update_user_option($pdo, $user_id, 'allowed_referers', $referers);
        update_user_option($pdo, $user_id, 'whitelist', $whitelist);
        header("Location: dashboard_pro.php?view=design&msg=updated");
        exit;
    }

    // 9. Update Branding Options (Simplified)
    if ($action === 'update_branding_opts') {
        $logo = trim($_POST['custom_logo'] ?? '');
        $heading = trim($_POST['custom_heading'] ?? '');
        $rm = isset($_POST['remove_branding']) ? '1' : '0';
        
        update_user_option($pdo, $user_id, 'custom_logo', $logo);
        update_user_option($pdo, $user_id, 'custom_heading', $heading);
        update_user_option($pdo, $user_id, 'remove_branding', $rm);
        
        header("Location: dashboard_pro.php?view=design&msg=updated");
        exit;
    }

    // 10. Update Password (Local)
    if ($action === 'update_password') {
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        
        if (strlen($new_pass) < 6) {
            header("Location: dashboard_pro.php?view=settings&msg=pwd_too_short");
        } else if ($new_pass !== $confirm_pass) {
            header("Location: dashboard_pro.php?view=settings&msg=pwd_mismatch");
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
            header("Location: dashboard_pro.php?view=settings&msg=updated");
        }
        exit;
    }

    // --- [ 10. Admin Actions ] ---
    if ($is_admin) {
        if ($action === 'admin_delete_user') {
            $target_uid = (int)$_POST['user_id'];
            if ($target_uid !== $user_id) { // Don't delete self
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_uid]);
                $pdo->prepare("DELETE FROM links WHERE user_id = ?")->execute([$target_uid]);
                header("Location: dashboard_pro.php?view=admin&msg=user_deleted");
                exit;
            }
        }
        if ($action === 'admin_toggle_status') {
            $target_uid = (int)$_POST['user_id'];
            $pdo->prepare("UPDATE users SET status = 1 - status WHERE id = ?")->execute([$target_uid]);
            header("Location: dashboard_pro.php?view=admin&msg=user_updated");
            exit;
        }
        if ($action === 'admin_toggle_vip') {
            $target_uid = (int)$_POST['user_id'];
            $curr_tier = $pdo->prepare("SELECT user_tier FROM users WHERE id = ?");
            $curr_tier->execute([$target_uid]);
            $tier = $curr_tier->fetchColumn();
            $new_tier = ($tier === 'vip') ? 'free' : 'vip';
            $expiry = ($new_tier === 'vip') ? date('Y-m-d H:i:s', strtotime('+1 year')) : null;
            $pdo->prepare("UPDATE users SET user_tier = ?, vip_expiry = ? WHERE id = ?")->execute([$new_tier, $expiry, $target_uid]);
            header("Location: dashboard_pro.php?view=admin&msg=user_updated");
            exit;
        }
        if ($action === 'admin_update_sys_security') {
            $ref = trim($_POST['sys_allowed_referers']);
            $white = trim($_POST['sys_whitelist']);
            $sb_key = trim($_POST['sys_safe_browsing_key']);
            update_user_option($pdo, 0, 'allowed_referers', $ref);
            update_user_option($pdo, 0, 'whitelist', $white);
            update_user_option($pdo, 0, 'safe_browsing_key', $sb_key);
            header("Location: dashboard_pro.php?view=admin&msg=sys_updated");
            exit;
        }
        if ($action === 'admin_gen_code') {
            $days = (int)$_POST['code_days'];
            if ($days > 0 && $days <= 3650) {
                $code = strtoupper(bin2hex(random_bytes(8)));
                $stmt = $pdo->prepare("INSERT INTO activation_codes (code, days) VALUES (?, ?)");
                $stmt->execute([$code, $days]);
                header("Location: dashboard_pro.php?view=admin&msg=code_generated");
                exit;
            }
        }
    }
    
    // --- [ 11. Activation Code Redemption ] ---
    if ($action === 'redeem_code') {
        $code = strtoupper(trim($_POST['code']));
        
        // 验证激活码
        $stmt = $pdo->prepare("SELECT * FROM activation_codes WHERE code = ? AND status = 0");
        $stmt->execute([$code]);
        $activation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activation) {
            $days = $activation['days'];
            
            // 计算新的过期时间
            $stmt = $pdo->prepare("SELECT vip_expiry FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_expiry = $stmt->fetchColumn();
            
            if ($current_expiry && strtotime($current_expiry) > time()) {
                // 在现有基础上延长
                $new_expiry = date('Y-m-d H:i:s', strtotime($current_expiry) + ($days * 86400));
            } else {
                // 从现在开始计算
                $new_expiry = date('Y-m-d H:i:s', time() + ($days * 86400));
            }
            
            // 更新用户 VIP 状态
            $stmt = $pdo->prepare("UPDATE users SET user_tier = 'vip', vip_expiry = ? WHERE id = ?");
            $stmt->execute([$new_expiry, $user_id]);
            
            // 标记激活码为已使用
            $stmt = $pdo->prepare("UPDATE activation_codes SET status = 1, used_by = ?, used_at = datetime('now') WHERE id = ?");
            $stmt->execute([$user_id, $activation['id']]);
            
            header("Location: dashboard_pro.php?view=upgrade&msg=vip_activated");
            exit;
        } else {
            header("Location: dashboard_pro.php?view=upgrade&msg=invalid_code");
            exit;
        }
    }
}

// --- [ 1.3 GET Actions Handler ] ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Re-handling toggle/delete for GET links if needed (though POST is safer)
    if (isset($_GET['toggle']) || isset($_GET['delete']) || isset($_GET['del_domain']) || isset($_GET['del_theme'])) {
        // Redirection logic already handled in POST if form used, but these are links
        $action_type = isset($_GET['toggle']) ? 'toggle' : (isset($_GET['delete']) ? 'delete' : (isset($_GET['del_domain']) ? 'del_domain' : 'del_theme'));
        $id = (int)($_GET['toggle'] ?? $_GET['delete'] ?? $_GET['del_domain'] ?? $_GET['del_theme']);
        
        if ($action_type === 'toggle') {
            $stmt = $pdo->prepare("UPDATE links SET is_active = 1 - is_active WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            header("Location: dashboard_pro.php?msg=updated");
        } elseif ($action_type === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            header("Location: dashboard_pro.php?msg=deleted");
        } elseif ($action_type === 'del_domain' && $user_tier === 'vip') {
            $stmt = $pdo->prepare("DELETE FROM custom_domains WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            header("Location: dashboard_pro.php?view=domains&msg=deleted");
        } elseif ($action_type === 'del_theme' && $user_tier === 'vip') {
            $stmt = $pdo->prepare("DELETE FROM themes WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            header("Location: dashboard_pro.php?view=design&msg=deleted");
        }
        exit;
    }
}
// --- [ 1.4 导出 CSV 逻辑 - 必须在任何输出之前 ] ---
if (isset($_GET['action']) && $_GET['action'] === 'export_stats') {
    $pdo = init_saas_db($db_path);
    // 验证用户有效性
    $stmt = $pdo->prepare("SELECT id FROM links WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_link_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($user_link_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_link_ids), '?'));
        $stmt = $pdo->prepare("SELECT s.*, l.slug FROM stats s JOIN links l ON s.link_id = l.id WHERE s.link_id IN ($placeholders) ORDER BY s.click_time DESC");
        $stmt->execute($user_link_ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=stats_export_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Slug', 'Timestamp', 'IP Address', 'Country', 'Device', 'OS', 'Browser', 'Referer']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['slug'], $row['click_time'], $row['ip_address'], $row['country'], 
                $row['device_type'], $row['os_name'], $row['browser_name'], $row['referer']
            ]);
        }
        fclose($output);
        exit;
    }
}

// --- [ VIP Access Control ] ---
// 防止非VIP用户直接通过URL访问VIP功能
$vip_only_views = ['domains', 'design', 'security'];
if (in_array($view, $vip_only_views) && $user_tier !== 'vip') {
    // 非VIP用户尝试访问VIP功能，重定向到升级页面
    header("Location: dashboard_pro.php?view=upgrade&msg=vip_required");
    exit;
}

// --- [ 1.5 创建链接 Handler ] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_link') {
    $target = trim($_POST['target_url']);
    $slug = trim($_POST['slug']);
    $pwd = $_POST['password'] ?? '';
    $custom_domain = $_POST['custom_domain'] ?? ''; // 用户选择的自定义域名
    
    // 检查限额
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $curr_count = $stmt->fetchColumn();
    
    if ($curr_count >= $limit) {
        header("Location: dashboard_pro.php?msg=limit_reached");
        exit;
    }

    // 生成或验证别名
    if (empty($slug)) {
        // 自动生成唯一别名
        $max_attempts = 10;
        for ($i = 0; $i < $max_attempts; $i++) {
            $slug = substr(bin2hex(random_bytes(4)), 0, 6);
            
            // 检查别名是否已被使用
            // 如果使用自定义域名，只需在该域名下唯一；否则全局唯一
            if (!empty($custom_domain)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE slug = ? AND custom_domain = ?");
                $stmt->execute([$slug, $custom_domain]);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE slug = ? AND (custom_domain IS NULL OR custom_domain = '')");
                $stmt->execute([$slug]);
            }
            
            if ($stmt->fetchColumn() == 0) {
                break; // 找到未使用的别名
            }
        }
    } else {
        // 用户自定义别名，检查是否已被使用
        if (!empty($custom_domain)) {
            // 自定义域名：只需在该域名下唯一
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE slug = ? AND custom_domain = ?");
            $stmt->execute([$slug, $custom_domain]);
        } else {
            // 默认域名：全局唯一（排除自定义域名的链接）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE slug = ? AND (custom_domain IS NULL OR custom_domain = '')");
            $stmt->execute([$slug]);
        }
        
        if ($stmt->fetchColumn() > 0) {
            header("Location: dashboard_pro.php?msg=slug_exists");
            exit;
        }
    }
    
    // 安全检查
    if (!check_url_safety($pdo, $target)) {
        header("Location: dashboard_pro.php?msg=unsafe");
        exit;
    }

    // 处理高级选项 (VIP)
    $max_clicks = 0;
    $expire_at = null;
    $fallback_url = '';
    $transit_type = 'direct';
    $transit_desc = '';
    $theme_id = 0;
    $routing_rules_json = null;

    if ($user_tier === 'vip') {
        $max_clicks = (int)($_POST['max_clicks'] ?? 0);
        $expire_at = !empty($_POST['expire_at']) ? $_POST['expire_at'] : null;
        $fallback_url = trim($_POST['fallback_url'] ?? '');
        $transit_type = $_POST['transit_type'] ?? 'direct';
        $transit_desc = $_POST['transit_desc'] ?? '';
        $theme_id = (int)($_POST['theme_id'] ?? 0);

        // Smart Routing
        $rr = [];
        if(!empty($_POST['sr_ios'])) $rr['device']['ios'] = $_POST['sr_ios'];
        if(!empty($_POST['sr_android'])) $rr['device']['android'] = $_POST['sr_android'];
        if(!empty($_POST['sr_geo_code']) && !empty($_POST['sr_geo_url'])) {
            $rr['geo'][strtoupper($_POST['sr_geo_code'])] = $_POST['sr_geo_url'];
        }
        if(!empty($_POST['sr_ab_url']) && !empty($_POST['sr_ab_ratio'])) {
            $rr['ab'] = ['url' => $_POST['sr_ab_url'], 'ratio' => (int)$_POST['sr_ab_ratio']];
        }
        if(!empty($rr)) $routing_rules_json = json_encode($rr);
    }

    // 插入链接（包含高级字段）
    $stmt = $pdo->prepare("INSERT INTO links (
        user_id, slug, target_url, password, status, custom_domain, 
        max_clicks, expire_at, fallback_url, transit_type, transit_desc, theme_id, routing_rules
    ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([
            $user_id, $slug, $target, $pwd, $custom_domain ?: null,
            $max_clicks, $expire_at, $fallback_url, $transit_type, $transit_desc, 0, $routing_rules_json
        ]);
        header("Location: dashboard_pro.php?msg=added");
    } catch (Exception $e) {
        // 如果还是失败（极少情况），提示别名冲突
        header("Location: dashboard_pro.php?msg=slug_exists");
    }
    exit;
}

$t = [
    'zh' => [
        'nav_links' => '链接管理',
        'nav_stats' => '统计看板',
        'nav_api' => '开发平台',
        'nav_domains' => '自定义域名',
        'nav_design' => '品牌中心',
        'nav_security' => '安全中心',
        'nav_logout' => '退出',
        'welcome' => '欢迎回来',
        'msg_deleted' => '链接已删除',
        'msg_updated' => '状态已更新',
        'msg_added' => '新链接已创建',
        'msg_slug_exists' => '该别名已被使用，请换一个',
        'msg_add_failed' => '创建失败',
        'nav_admin' => '系统管理',
        'admin_title' => '全站概览',
        'admin_total_users' => '用户总数',
        'admin_total_links' => '链接总数',
        'admin_user_mgmt' => '用户管理',
        'admin_username' => '用户名',
        'admin_tier' => '等级',
        'admin_link_count' => '链接数',
        'admin_top_links' => '热门链接 (TOP 10)',
        'admin_sys_settings' => '全站系统设置',
        'admin_sys_ref' => '全站允许 Referer (系统默认)',
        'admin_sys_white' => '全站信任域名白名单 (系统默认)',
        'admin_btn_save' => '保存全局设置',
        'admin_action_ban' => '封禁',
        'admin_action_unban' => '解封',
        'admin_action_vip' => '置为 VIP',
        'admin_action_free' => '置为普通',
        'msg_user_deleted' => '用户及相关链接已彻底删除',
        'msg_user_updated' => '用户信息已更新',
        'msg_sys_updated' => '系统全局设置已保存',
        'msg_code_generated' => '激活码已生成',
        'msg_vip_activated' => 'VIP 已成功开通！',
        'msg_invalid_code' => '激活码无效或已被使用',
        'msg_vip_required' => '该功能仅限 VIP 用户使用，请先升级',
        'msg_unsafe' => '目标链接被 Google Safe Browsing 标记为不安全，已被系统拦截。',
        'card_add_title' => '创建新跳转',
        'form_slug' => '别名 (Slug)',
        'form_slug_ph' => '留空则随机生成',
        'form_target' => '目标 URL',
        'form_pwd' => '访问密码 (可选)',
        'form_max_clicks' => '最大点击量 (0为不限)',
        'form_expire' => '过期时间 (可选)',
        'btn_create' => '创建外链',
        'quick_title' => '快速工具 (免数据库)',
        'quick_desc' => '生成无统计的加密或直跳外链',
        'quick_ph' => '输入长链接...',
        'form_quick_enc' => '加密链接 (Base64)',
        'form_quick_dir' => '直连跳转 (无加密)',
        'card_list_title' => '现有外链',
        'table_slug' => '标识',
        'table_target' => '目标 URL / 统计',
        'table_status' => '状态',
        'table_actions' => '操作',
        'status_on' => '启用',
        'status_off' => '禁用',
        'btn_stats' => '统计',
        'btn_copy' => '复制',
        'btn_qrcode' => '二维码',
        'btn_edit' => '编辑',
        'btn_delete' => '删除',
        'confirm_delete' => '确定删除此链接吗？',
        'stats_total_clicks' => '总点击量',
        'stats_today_clicks' => '今日点击',
        'stats_top_referer' => '主要来源',
        'stats_top_country' => '主要地区',
        'filter_last_7' => '最近 7 天',
        'filter_last_30' => '最近 30 天',
        'chart_clicks' => '点击趋势',
        'chart_device' => '设备分布',
        'chart_country' => '地区分布 (仅显示 Top 5)',
        'chart_referer' => '来源分析',
        'ana_title' => '我的链接统计',
        'ana_trend' => '每日点击趋势',
        'ana_map' => '访客分布地图',
        'ana_device' => '设备类型',
        'ana_os' => '操作系统',
        'ana_browser' => '浏览器',
        'btn_export_stats' => '导出报表 (CSV)',
        'confirm_clear_stats' => '确定清空所有统计数据吗？此操作不可撤销！',
        'btn_clear_stats_short' => '清空数据',
        'msg_copied' => '链接已复制！',
        'tooltip_copy' => '复制链接',
        'tooltip_open' => '打开 URL',
        'tooltip_toggle' => '切换状态',
        'tooltip_delete' => '删除',
        'filter_link_label' => '筛选链接:',
        'filter_all_links' => '全部链接',
        'api_title' => 'API 密钥管理',
        'api_key_label' => '您的 API Key',
        'api_regen' => '重新生成密钥',
        'api_doc' => 'API 文档',
        'domain_title' => '自定义域名绑定',
        'domain_label' => '绑定域名',
        'domain_ph' => '例如: link.example.com',
        'domain_add' => '添加域名',
        'domain_verify' => '验证状态',
        'design_title' => '品牌与样式',
        'design_logo' => '自定义 Logo URL',
        'design_color' => '主题色 (Hex)',
        'save_changes' => '保存设置',
        'table_empty' => '暂无数据',
        'table_details' => '高级选项',
        'tooltip_copy' => '复制链接',
        'tooltip_open' => '打开链接',
        'tooltip_toggle' => '开启/关闭',
        'tooltip_delete' => '删除链接',
        'form_clicks' => '点击限制',
        'form_fallback' => '备用跳转 (404/过期等)',
        'form_fallback_ph' => 'https://...',
        'form_transit_type' => '直跳 / 中转页',
        'form_transit_direct' => '直接跳转 (301)',
        'form_transit_inter' => '品牌中转页 (点击跳转)',
        'ph_transit_desc_override' => '中转页提示文字...',
        'smart_routing' => '智能分流 (Beta)',
        'smart_device' => '按设备 (iOS/Android)',
        'ph_ios' => 'iOS 跳转 URL',
        'ph_android' => 'Android 跳转 URL',
        'smart_geo' => '按地区 (ISO 简码)',
        'ph_geo_code' => '例如: CN',
        'ph_geo_url' => '对应地区跳转 URL',
        'smart_ab' => 'A/B 随机测试',
        'ph_ab_url' => 'B 组 URL',
        'ph_ab_ratio' => '权重 %',
        'form_theme_select' => '中转页主题样式',
        'theme_system_default' => '系统默认',
        'vip_desc' => '升级到专业版解锁：自定义域名、品牌中转页、深度统计与 API。',
        'nav_settings' => '账户设置',
        'nav_upgrade' => '升级专业版',
        'msg_copied' => '已复制到剪贴板',
        'ana_title' => '全站流量看板',
        'ana_trend' => '访问趋势 (7天)',
        'ana_map' => '访客地区分布',
        'ana_device' => '设备类型',
        'ana_os' => '操作系统',
        'ana_browser' => '浏览器',
        'btn_export_stats' => '导出数据',
        'btn_clear_stats_short' => '重置统计',
        'confirm_clear_stats' => '警告：这将永久删除所有访问记录，确定吗？',
        'api_usage_tip' => '请妥善保管您的 API Key，不要泄露给他人。',
        'api_reset_confirm' => '确定要重置 API Key 吗？旧的 Key 将立即失效。',
        'api_btn_gen' => '生成我的 API Key',
        'api_btn_reset' => '重置 API Key',
        'domain_help' => '绑定后，请将域名的 CNAME 记录指向：',
        'domain_verify_btn' => '立即验证',
        'des_add_btn' => '创建新主题',
        'des_name' => '主题名称',
        'des_layout' => '布局样式',
        'des_minimal' => '极简',
        'des_dark' => '深色',
        'des_vibrant' => '炫彩',
        'form_transit_color' => '主题主色',
        'form_transit_logo' => 'Logo 链接',
        'ph_main_heading' => '主标题文字',
        'btn_save_theme' => '保存并创建',
        'des_my_themes' => '我的品牌定义',
        'table_action' => '操作',
        'des_empty' => '暂无自定义主题',
        'confirm_delete_theme' => '确定删除此主题吗？',
        'modal_pay_title' => '选择支付方式',
        'pay_alipay' => '支付宝 (当面付)',
        'pay_alipay_desc' => '扫码即时验证 / 手机唤起',
        'btn_continue_pay' => '前往支付',
        'sec_title' => '安全中心',
        'sec_ref_label' => '允许的 Referer (每行一个)',
        'sec_ref_ph' => '例如: google.com',
        'sec_ref_tip' => '每行一个域名。留空继承系统默认，输入 * 表示不限制来源。',
        'sec_white_label' => '安全域名 (白名单) (安全提示)',
        'sec_white_ph' => '例如: dsi.mom, google.com',
        'sec_white_tip' => '这些域名在跳转过渡页时将显示安全绿色提示，而非风险提示。留空继承系统默认，输入 * 表示所有域名都信任。',
        'btn_save_sec' => '保存安全设置',
        'btn_save' => '保存',
        'des_remove_branding' => '移除品牌标识',
        'des_remove_branding_tip' => '开启后，将隐藏所有关于本站的链接与 Logo (专业版专享)',
        'dom_verified' => '已生效',
        'dom_pending' => '待验证',
        'dom_empty' => '暂无绑定域名',
        'api_quick_start' => '快速开始 (cURL)',
        'dom_add_btn' => '绑定新域名',
        'dom_tip' => '请先在 DNS 服务商处将域名 CNAME 指向本站域名。',
        'dom_title' => '域名管理',
        'dom_name' => '域名地址',
        'dom_status' => '验证状态',
        'api_copy_success' => 'API Key 已复制',
        'set_info' => '基本资料',
        'set_username' => '用户名',
        'set_tier' => '当前套餐',
        'vip_active' => '有效',
        'set_expiry' => '过期时间',
        'set_security' => '安全设置',
        'set_new_pwd' => '新密码',
        'set_confirm_pwd' => '确认新密码',
        'btn_update_pwd' => '更新密码',
        'set_security' => '账号安全',
        'vip_title' => '专业版特权',
        'plan_popular' => '最受欢迎',
        'plan_monthly' => '月度版',
        'plan_monthly_desc' => '按月付费，灵活取消',
        'plan_quarterly' => '季度版',
        'plan_quarterly_desc' => '适合个人与小微团队',
        'plan_yearly' => '年度版',
        'plan_yearly_desc' => '最超值，节省 17%',
        'btn_pay' => '立即升级',
        'form_redeem_code' => '激活码兑换',
        'ph_redeem_code' => '输入 16 位激活码',
        'btn_redeem' => '激活',
        'pay_modal_title' => '选择支付方式',
        'pay_modal_desc' => '请选择你的支付方式',
        'pay_alipay' => '支付宝 (扫码支付)',
        'pay_other' => 'LINUX DO Credit',
        'admin_gen_code' => '生成激活码',
        'admin_code_days' => '有效天数',
        'admin_code_list' => '激活码列表',
        'admin_code_value' => '激活码',
        'admin_code_status' => '状态',
        'admin_code_unused' => '未使用',
        'admin_code_used' => '已使用',
        'des_logo_tip' => '自定义Logo图片链接，留空则使用默认Logo',
        'des_heading_tip' => '中转页显示的自定义标题文字',
        'footer' => '© 2026 BitkoalaLab. All rights reserved.'
    ],
    'en' => [
        'nav_links' => 'My Links',
        'nav_stats' => 'Analytics',
        'nav_api' => 'Developers',
        'nav_domains' => 'Domains',
        'nav_design' => 'Branding',
        'nav_security' => 'Security',
        'nav_logout' => 'Logout',
        'welcome' => 'Welcome back',
        'msg_deleted' => 'Link deleted',
        'msg_updated' => 'Status updated',
        'msg_added' => 'Link created',
        'msg_slug_exists' => 'This slug is already taken, please try another',
        'msg_add_failed' => 'Creation failed',
        'nav_admin' => 'Admin',
        'admin_title' => 'System Overview',
        'admin_total_users' => 'Total Users',
        'admin_total_links' => 'Total Links',
        'admin_user_mgmt' => 'User Management',
        'admin_username' => 'Username',
        'admin_tier' => 'Tier',
        'admin_link_count' => 'Links',
        'admin_top_links' => 'Hot Links (TOP 10)',
        'admin_sys_settings' => 'Global System Settings',
        'admin_sys_ref' => 'Global Allowed Referers',
        'admin_sys_white' => 'Global Trusted Whitelist',
        'admin_btn_save' => 'Save Global Configuration',
        'admin_action_ban' => 'Ban',
        'admin_action_unban' => 'Unban',
        'admin_action_vip' => 'Make VIP',
        'admin_action_free' => 'Make Free',
        'msg_user_deleted' => 'User and their links deleted',
        'msg_user_updated' => 'User status updated',
        'msg_sys_updated' => 'Global system settings saved',
        'msg_code_generated' => 'Activation code generated',
        'msg_vip_activated' => 'VIP activated successfully!',
        'msg_invalid_code' => 'Invalid or already used activation code',
        'msg_vip_required' => 'This feature is VIP-only, please upgrade first',
        'card_add_title' => 'New Link',
        'form_slug' => 'Custom Slug',
        'form_slug_ph' => 'Leave empty for random',
        'form_target' => 'Target URL',
        'form_pwd' => 'Password (Optional)',
        'form_max_clicks' => 'Max Clicks (0=Unlimited)',
        'form_expire' => 'Expires At (Optional)',
        'btn_create' => 'Create Link',
        'quick_title' => 'Quick Tools (No DB)',
        'quick_desc' => 'Generate encrypted or direct links without tracking',
        'quick_ph' => 'Enter long URL...',
        'form_quick_enc' => 'Encrypted (Base64)',
        'form_quick_dir' => 'Direct (No Enc)',
        'card_list_title' => 'Active Links',
        'table_slug' => 'Slug',
        'table_target' => 'Target / Stats',
        'table_status' => 'Status',
        'table_actions' => 'Actions',
        'status_on' => 'Active',
        'status_off' => 'Disabled',
        'btn_stats' => 'Stats',
        'btn_copy' => 'Copy',
        'btn_qrcode' => 'QR',
        'btn_edit' => 'Edit',
        'btn_delete' => 'Delete',
        'confirm_delete' => 'Delete this link?',
        'stats_total_clicks' => 'Total Clicks',
        'stats_today_clicks' => 'Today',
        'stats_top_referer' => 'Top Source',
        'stats_top_country' => 'Top Region',
        'filter_last_7' => 'Last 7 Days',
        'filter_last_30' => 'Last 30 Days',
        'chart_clicks' => 'Click Trend',
        'chart_device' => 'Device Breakdown',
        'domain_label' => 'Add Domain',
        'domain_ph' => 'e.g. link.example.com',
        'domain_add' => 'Add',
        'domain_verify' => 'Status',
        'design_title' => 'Branding',
        'design_logo' => 'Logo URL',
        'design_color' => 'Theme Color',
        'save_changes' => 'Save Changes',
        'table_empty' => 'No links found',
        'table_details' => 'Advanced Options',
        'tooltip_copy' => 'Copy',
        'tooltip_open' => 'Open',
        'tooltip_toggle' => 'Toggle Status',
        'tooltip_delete' => 'Delete',
        'form_clicks' => 'Click Limit',
        'form_fallback' => 'Fallback URL',
        'form_fallback_ph' => 'https://...',
        'form_transit_type' => 'Redirect Type',
        'form_transit_direct' => 'Direct (301)',
        'form_transit_inter' => 'Intermediary Page',
        'ph_transit_desc_override' => 'Custom page text...',
        'smart_routing' => 'Smart Routing',
        'smart_device' => 'Device-based',
        'ph_ios' => 'iOS URL',
        'ph_android' => 'Android URL',
        'smart_geo' => 'Geo-based',
        'ph_geo_code' => 'e.g. US',
        'ph_geo_url' => 'Geo-redirect URL',
        'smart_ab' => 'A/B Testing',
        'ph_ab_url' => 'B-side URL',
        'ph_ab_ratio' => 'Weight %',
        'form_theme_select' => 'Page Theme',
        'theme_system_default' => 'Default Theme',
        'vip_desc' => 'Upgrade to Pro for Custom Domains, Branding, API, and Advanced Analytics.',
        'nav_settings' => 'Settings',
        'nav_upgrade' => 'Upgrade to Pro',
        'msg_copied' => 'Copied to clipboard',
        'ana_title' => 'Analytics Overview',
        'ana_trend' => 'Click Trend (7 Days)',
        'ana_map' => 'Geographic Distribution',
        'ana_device' => 'Devices',
        'ana_os' => 'Operating Systems',
        'ana_browser' => 'Browsers',
        'btn_export_stats' => 'Export CSV',
        'btn_clear_stats_short' => 'Clear All',
        'confirm_clear_stats' => 'Warning: This will permanently delete ALL click data. Continue?',
        'api_usage_tip' => 'Keep your API Key secret. Use it to automate link creation.',
        'api_reset_confirm' => 'Are you sure? Existing integrations using the old key will break.',
        'api_btn_gen' => 'Generate API Key',
        'api_btn_reset' => 'Reset API Key',
        'domain_help' => 'After adding, point your domain CNAME record to:',
        'domain_verify_btn' => 'Verify Now',
        'des_add_btn' => 'Add New Theme',
        'des_name' => 'Theme Name',
        'des_layout' => 'Layout Style',
        'des_minimal' => 'Minimal',
        'des_dark' => 'Dark',
        'des_vibrant' => 'Vibrant',
        'form_transit_color' => 'Primary Color',
        'form_transit_logo' => 'Logo URL',
        'ph_main_heading' => 'Hero Heading',
        'btn_save_theme' => 'Save & Create',
        'des_my_themes' => 'My Themes',
        'table_action' => 'Actions',
        'des_empty' => 'No themes yet',
        'confirm_delete_theme' => 'Delete this theme?',
        'modal_pay_title' => 'Select Payment Method',
        'pay_alipay' => 'Alipay (Face-to-Face)',
        'pay_alipay_desc' => 'Instant activation via QR Code',
        'btn_continue_pay' => 'Continue to Pay',
        'sec_title' => 'Security Center',
        'sec_ref_label' => 'Allowed Referers (One per line)',
        'sec_ref_ph' => 'e.g. google.com',
        'sec_ref_tip' => 'One domain per line. Leave empty to inherit system defaults, or use * to allow all sources.',
        'sec_white_label' => 'Safe Domains (Whitelist)',
        'sec_white_ph' => 'e.g. dsi.mom, google.com',
        'sec_white_tip' => 'Listed domains will show a "Safe" green notice instead of a security warning. Leave empty to inherit system defaults, or use * to trust all domains.',
        'btn_save_sec' => 'Save Security Settings',
        'btn_save' => 'Save',
        'des_remove_branding' => 'Remove Branding',
        'des_remove_branding_tip' => 'Hide all mentions of KoalaLink on your pages (Pro only)',
        'dom_verified' => 'Active',
        'dom_pending' => 'Pending',
        'dom_empty' => 'No domains added',
        'api_quick_start' => 'Quick Start (cURL)',
        'dom_add_btn' => 'Add Domain',
        'dom_tip' => 'Please point your CNAME record to this site first.',
        'dom_title' => 'Domains',
        'dom_name' => 'Domain',
        'dom_status' => 'Status',
        'api_copy_success' => 'API Key copied',
        'set_info' => 'Account Info',
        'set_username' => 'Username',
        'set_tier' => 'Plan',
        'vip_active' => 'Active',
        'set_expiry' => 'Expiries At',
        'set_security' => 'Security',
        'set_curr_pwd' => 'Current Password',
        'set_new_pwd' => 'New Password',
        'set_confirm_pwd' => 'Confirm Password',
        'btn_update_pwd' => 'Update Password',
        'set_new_pwd' => 'New Password',
        'set_confirm_pwd' => 'Confirm New Password',
        'btn_update_pwd' => 'Update Password',
        'set_security' => 'Account Security',
        'vip_title' => 'Pro Privileges',
        'plan_popular' => 'Popular',
        'plan_monthly' => 'Monthly',
        'plan_monthly_desc' => 'Pay monthly, cancel anytime',
        'plan_quarterly' => 'Quarterly',
        'plan_quarterly_desc' => 'Best for individuals',
        'plan_yearly' => 'Yearly',
        'plan_yearly_desc' => 'Best value, save 17%',
        'btn_pay' => 'Upgrade Now',
        'form_redeem_code' => 'Redeem Code',
        'ph_redeem_code' => 'Enter 16-digit code',
        'btn_redeem' => 'Redeem',
        'pay_modal_title' => 'Select Payment Method',
        'pay_modal_desc' => 'Choose your preferred way to pay',
        'pay_alipay' => 'Alipay (Face-to-Face)',
        'pay_other' => 'LINUX DO Credit',
        'admin_gen_code' => 'Generate Activation Code',
        'admin_code_days' => 'Valid Days',
        'admin_code_list' => 'Activation Codes',
        'admin_code_value' => 'Code',
        'admin_code_status' => 'Status',
        'admin_code_unused' => 'Unused',
        'admin_code_used' => 'Used',
        'des_logo_tip' => 'URL to your custom logo image. Leave empty to use system default.',
        'des_heading_tip' => 'Custom title text for the redirection page.',
        'footer' => '© 2026 BitkoalaLab. All rights reserved.'
    ]
];
$text = $t[$lang];

if (isset($_GET['msg'])) {
    $msg_key = 'msg_' . $_GET['msg'];
    $message = $text[$msg_key] ?? $_GET['msg'];
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
    <title>KoalaLink SaaS - <?php echo $text['nav_links']; ?></title>
    <link rel="icon" type="image/png" href="../Favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Map -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/js/jsvectormap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>
    <style>
        :root { --primary: #2563eb; --active: #10b981; --bg: #f8fafc; }
        body { background: var(--bg); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .navbar { background: rgba(255, 255, 255, 0.8) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .nav-link { transition: all 0.3s ease; border-radius: 0.5rem; margin: 0 2px; }
        .nav-link:hover { background: rgba(37, 99, 235, 0.05); color: var(--primary) !important; }
        .nav-link.active { background: rgba(37, 99, 235, 0.1); color: var(--primary) !important; font-weight: bold; }
        .card { border: none; border-radius: 1rem !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05); margin-bottom: 1.5rem; overflow: hidden; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .bg-active { background: var(--active); box-shadow: 0 0 5px var(--active); }
        .bg-inactive { background: #94a3b8; }
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }

        /* Social Icons in Footer */
        .footer-social-section {
            padding: 3rem 0;
            text-align: center;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-top: 3rem;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 1.15rem;
            transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .social-icon:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
            border-color: var(--primary);
        }

        .wechat-popover-content img {
            width: 140px;
            height: 140px;
            border-radius: 6px;
        }
        .rounded-4 { border-radius: 1rem !important; }
        .shadow-sm { box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1) !important; }
        .btn { border-radius: 0.75rem; transition: all 0.2s; }
        .btn-primary { border-radius: 2rem; }
        .table thead th { background: #fcfcfd; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; color: #64748b; letter-spacing: 0.025em; border-top: none; }
    </style>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://pandax.mom/ns.html?id=GTM-TX5CNWGV"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<nav class="navbar navbar-expand-lg mb-4 py-2 sticky-top">
    <div class="container d-flex align-items-center">
        <a class="navbar-brand d-flex align-items-center" href="dashboard_pro.php">
            <img src="../logo.png" alt="Logo" height="30" class="me-2" onerror="this.style.display='none'">
            <span class="fw-bold fs-5">KoalaLink <span class="badge bg-primary ms-1" style="font-size: 0.6rem;"><?php echo $lang === 'zh' ? '专业版' : 'Pro'; ?></span></span>
        </a>
        
        <div class="ms-lg-3 d-flex align-items-center overflow-auto">
            <ul class="navbar-nav flex-row gap-0 flex-nowrap">
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'links' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=links">
                        <i class="bi bi-link-45deg"></i> <?php echo $text['nav_links']; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'stats' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=stats">
                        <i class="bi bi-pie-chart"></i> <?php echo $text['nav_stats']; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'api' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=api">
                        <i class="bi bi-code-slash"></i> <?php echo $text['nav_api']; ?>
                    </a>
                </li>
                <?php if ($user_tier === 'vip'): ?>
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'domains' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=domains">
                        <i class="bi bi-globe"></i> <?php echo $text['nav_domains']; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'design' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=design">
                        <i class="bi bi-palette"></i> <?php echo $text['nav_design']; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'security' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=security">
                        <i class="bi bi-shield-lock"></i> <?php echo $text['nav_security']; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                <li class="nav-item">
                    <a class="nav-link px-1 px-lg-2 text-nowrap <?php echo $view === 'admin' ? 'active text-primary fw-bold' : 'text-muted'; ?>" href="?view=admin">
                        <i class="bi bi-shield-check"></i> <?php echo $text['nav_admin']; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="d-flex align-items-center ms-auto">
            <?php if($user_tier === 'vip'): ?>
                <div class="me-3 d-none d-md-block text-end">
                    <small class="text-muted d-block" style="font-size: 0.7rem;"><?php echo $lang === 'zh' ? 'VIP 到期' : 'VIP Expires'; ?></small>
                    <span class="badge bg-warning text-dark border-0" style="font-size: 0.75rem;"><?php echo date('Y-m-d', strtotime($vip_expiry)); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Upgrade / Renew Button -->
            <a href="?view=upgrade" class="btn btn-warning btn-sm rounded-circle me-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;" title="<?php echo $user_tier === 'vip' ? ($lang === 'zh' ? '续费 VIP' : 'Renew VIP') : $text['nav_upgrade']; ?>">
                <i class="bi bi-lightning-charge-fill"></i>
            </a>

            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4" aria-labelledby="userDropdown">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($username); ?></div>
                        <small class="text-muted d-block"><?php echo $user_tier === 'vip' ? '<i class="bi bi-star-fill text-warning"></i> VIP Pro' : 'Free Plan'; ?></small>
                    </li>
                    <li><a class="dropdown-item py-2" href="?view=settings"><i class="bi bi-gear me-2"></i> <?php echo $text['nav_settings']; ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="auth.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i> <?php echo $text['nav_logout']; ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <?php if($message): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm rounded-4 border-0" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($view === 'links'): ?>
    <div class="row g-4">
        <!-- Create Link -->
        <div class="col-lg-4">
            <div class="card p-4 shadow-sm border-0 rounded-4">
                <h5 class="mb-4 d-flex align-items-center">
                   <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-2"><i class="bi bi-plus-lg"></i></div> 
                   <?php echo $text['card_add_title']; ?>
                </h5>
                <form method="POST">
                    <input type="hidden" name="action" value="create_link">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase"><?php echo $text['form_target']; ?></label>
                        <input type="url" name="target_url" class="form-control form-control-lg bg-light border-0" placeholder="https://..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase"><?php echo $text['form_slug']; ?></label>
                        <div class="input-group">
                            <?php if($user_tier === 'vip' && !empty($user_domains)): ?>
                                <select name="custom_domain" class="form-select bg-light border-0 text-muted" style="max-width: 160px;">
                                    <option value="" selected><?php echo PRIMARY_DOMAIN; ?>/</option>
                                    <?php foreach($user_domains as $ud): ?>
                                    <option value="<?php echo htmlspecialchars($ud); ?>"><?php echo htmlspecialchars($ud); ?>/</option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span class="input-group-text bg-light border-0 text-muted">/</span>
                            <?php endif; ?>
                            <input type="text" name="slug" class="form-control form-control-lg bg-light border-0" placeholder="<?php echo $text['form_slug_ph']; ?>">
                        </div>
                    </div>

                    <?php if($user_tier === 'vip'): ?>
                    <div class="accordion mb-3" id="advOpts">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-white shadow-none px-0 text-primary fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdv">
                                    <i class="bi bi-sliders me-2"></i> <?php echo $text['table_details']; ?>
                                </button>
                            </h2>
                            <div id="collapseAdv" class="accordion-collapse collapse" data-bs-parent="#advOpts">
                                <div class="pt-2">
                                    <!-- 1. Password -->
                                    <div class="mb-3">
                                        <label class="form-label small text-muted"><?php echo $text['form_pwd']; ?></label>
                                        <input type="text" name="password" class="form-control bg-light border-0">
                                    </div>
                                    <!-- 2. Limits -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small text-muted"><?php echo $text['form_clicks']; ?></label>
                                            <input type="number" name="max_clicks" class="form-control bg-light border-0" value="0">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-muted"><?php echo $text['form_expire']; ?></label>
                                            <input type="datetime-local" name="expire_at" class="form-control bg-light border-0">
                                        </div>
                                    </div>
                                    <!-- 3. Fallback URL -->
                                    <div class="mb-3">
                                        <label class="form-label small text-muted"><?php echo $text['form_fallback']; ?></label>
                                        <input type="url" name="fallback_url" class="form-control bg-light border-0" placeholder="<?php echo $text['form_fallback_ph']; ?>">
                                    </div>

                                    <!-- 4. Transit Page -->
                                    <div class="mb-3 border-top pt-3">
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-stopwatch"></i> <?php echo $text['form_transit_type']; ?></label>
                                        <select name="transit_type" class="form-select bg-light border-0 mb-2">
                                            <option value="direct"><?php echo $text['form_transit_direct']; ?></option>
                                            <option value="interstitial"><?php echo $text['form_transit_inter']; ?></option>
                                        </select>
                                        <input type="text" name="transit_desc" class="form-control bg-light border-0" placeholder="<?php echo $text['ph_transit_desc_override']; ?>">
                                    </div>

                                    <!-- 5. Smart Routing -->
                                    <div class="mb-3 border-top pt-3">
                                        <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-shuffle"></i> <?php echo $text['smart_routing']; ?></label>
                                        
                                        <!-- Device -->
                                        <div class="bg-light p-2 rounded mb-2">
                                            <label class="small text-muted mb-1"><?php echo $text['smart_device']; ?></label>
                                            <input type="url" name="sr_ios" class="form-control form-control-sm mb-1" placeholder="<?php echo $text['ph_ios']; ?>">
                                            <input type="url" name="sr_android" class="form-control form-control-sm" placeholder="<?php echo $text['ph_android']; ?>">
                                        </div>

                                        <!-- Geo -->
                                        <div class="bg-light p-2 rounded mb-2">
                                            <label class="small text-muted mb-1"><?php echo $text['smart_geo']; ?></label>
                                            <div class="input-group input-group-sm mb-1">
                                                <input type="text" name="sr_geo_code" class="form-control" placeholder="<?php echo $text['ph_geo_code']; ?>" style="max-width: 80px;">
                                                <input type="url" name="sr_geo_url" class="form-control" placeholder="<?php echo $text['ph_geo_url']; ?>">
                                            </div>
                                        </div>

                                        <!-- A/B -->
                                        <div class="bg-light p-2 rounded">
                                            <label class="small text-muted mb-1"><?php echo $text['smart_ab']; ?></label>
                                            <div class="input-group input-group-sm">
                                                <input type="url" name="sr_ab_url" class="form-control" placeholder="<?php echo $text['ph_ab_url']; ?>">
                                                <input type="number" name="sr_ab_ratio" class="form-control" placeholder="<?php echo $text['ph_ab_ratio']; ?>" min="0" max="100" style="max-width: 70px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- 6. Theme Select Removed (Global Branding Used) -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-3 bg-light rounded-3 mb-3 text-center">
                        <small class="text-muted d-block mb-2"><?php echo $text['vip_desc']; ?></small>
                        <a href="?view=upgrade" class="btn btn-sm btn-warning text-dark fw-bold"><i class="bi bi-lock-fill"></i> <?php echo $text['nav_upgrade']; ?></a>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm"><?php echo $text['btn_create']; ?></button>
                </form>
            </div>

            <!-- Card 2: Quick Tools (No DB) -->
            <div class="card p-4 mt-4 shadow-sm border-0 rounded-4">
                <h5 class="mb-3 fw-bold text-primary"><i class="bi bi-lightning-charge-fill"></i> <?php echo $text['quick_title']; ?></h5>
                <div class="mb-3">
                    <label class="form-label small text-muted"><?php echo $text['quick_desc']; ?></label>
                    <input type="url" id="toolRawUrl" class="form-control form-control-lg bg-light border-0" placeholder="<?php echo $text['quick_ph']; ?>" oninput="genQuickLinks()">
                </div>
                
                <div id="quickLinks" class="d-none animate__animated animate__fadeIn">
                    <div class="mb-3">
                        <label class="form-label text-muted" style="font-size:0.75rem"><?php echo $text['form_quick_enc']; ?></label>
                        <div class="input-group">
                            <input type="text" id="linkBase64" class="form-control bg-light border-0" readonly style="font-size:0.9rem">
                            <button type="button" class="btn btn-primary px-3" onclick="copyToClipboard('linkBase64')"><i class="bi bi-clipboard me-1"></i> <?php echo $text['btn_copy']; ?></button>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted" style="font-size:0.75rem"><?php echo $text['form_quick_dir']; ?></label>
                        <div class="input-group">
                            <input type="text" id="linkDirect" class="form-control bg-light border-0" readonly style="font-size:0.9rem">
                            <button type="button" class="btn btn-primary px-3" onclick="copyToClipboard('linkDirect')"><i class="bi bi-clipboard me-1"></i> <?php echo $text['btn_copy']; ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Links List -->
        <div class="col-lg-8">
            <div class="card p-0">
                <div class="card-header bg-white py-3 px-4 border-0">
                    <h5 class="mb-0 d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-2"><i class="bi bi-list-ul"></i></div>
                        <?php echo $text['card_list_title']; ?>
                    </h5>
                    <?php if($user_tier === 'free'): ?>
                    <small class="text-muted d-block mt-1">
                        Free Plan Usage: <span class="fw-bold <?php echo count($links) >= $limit ? 'text-danger' : 'text-success'; ?>"><?php echo count($links); ?> / <?php echo $limit; ?></span>
                    </small>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4"><?php echo $text['table_slug']; ?></th>
                                <th><?php echo $text['table_target']; ?></th>
                                <th><?php echo $text['table_status']; ?></th>
                                <th class="text-end pe-4"><?php echo $text['table_actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($links as $link): 
                                // Get stats summary & build URL
                                $s_stmt = $pdo->prepare("SELECT COUNT(*) FROM stats WHERE link_id = ?");
                                $s_stmt->execute([$link['id']]);
                                $click_count = $s_stmt->fetchColumn();
                                
                                $base_url = (!empty($link['custom_domain'])) ? 'http://' . $link['custom_domain'] : SITE_URL;
                                $short_url = $base_url . '/' . $link['slug'];
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-primary">/<?php echo htmlspecialchars($link['slug']); ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem"><?php echo date('M d', strtotime($link['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center mb-1">
                                        <div class="text-truncate" style="max-width: 200px;">
                                            <a href="<?php echo htmlspecialchars($link['target_url']); ?>" target="_blank" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($link['target_url']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark border"><i class="bi bi-bar-chart-fill text-primary"></i> <?php echo $click_count; ?></span>
                                        <?php if($link['password']): ?><span class="badge bg-warning text-dark"><i class="bi bi-lock-fill"></i></span><?php endif; ?>
                                        <?php if($link['transit_type'] === 'inter'): ?><span class="badge bg-info text-dark"><i class="bi bi-stopwatch"></i> TM</span><?php endif; ?>
                                        <?php if($link['routing_rules']): ?><span class="badge bg-purple text-white" style="background:#8b5cf6"><i class="bi bi-shuffle"></i> SR</span><?php endif; ?>
                                        <?php if(!empty($link['custom_domain'])): ?><span class="badge bg-success text-white"><i class="bi bi-globe"></i> <?php echo htmlspecialchars($link['custom_domain']); ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($link['status']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?php echo $text['status_on']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3"><?php echo $text['status_off']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-light text-muted" onclick="copyUrl('<?php echo $short_url; ?>')" data-bs-toggle="tooltip" title="<?php echo $text['tooltip_copy']; ?>"><i class="bi bi-clipboard"></i></button>
                                        <a href="<?php echo $short_url; ?>" target="_blank" class="btn btn-sm btn-light text-muted" data-bs-toggle="tooltip" title="<?php echo $text['tooltip_open']; ?>"><i class="bi bi-box-arrow-up-right"></i></a>
                                        <a href="?toggle=<?php echo $link['id']; ?>" class="btn btn-sm btn-light text-warning" data-bs-toggle="tooltip" title="<?php echo $text['tooltip_toggle']; ?>"><i class="bi bi-power"></i></a>
                                        <a href="?delete=<?php echo $link['id']; ?>" class="btn btn-sm btn-light text-danger" onclick="return confirm('<?php echo $text['confirm_delete']; ?>')" data-bs-toggle="tooltip" title="<?php echo $text['tooltip_delete']; ?>"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($links)): ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted"><?php echo $text['table_empty']; ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif($view === 'stats'): ?>
    <!-- STATS VIEW -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold"><?php echo $text['ana_title']; ?></h4>
        <div>
             <form class="d-inline-flex align-items-center me-2" method="GET">
                 <input type="hidden" name="view" value="stats">
                 <span class="me-2 text-muted small fw-bold"><?php echo $text['filter_link_label']; ?></span>
                 <select name="link_id" class="form-select form-select-sm" style="width: auto; max-width: 200px;" onchange="this.form.submit()">
                     <option value="0"><?php echo $text['filter_all_links']; ?></option>
                     <?php foreach($links as $lnk): ?>
                     <option value="<?php echo $lnk['id']; ?>" <?php echo (isset($selected_link_id) && $selected_link_id == $lnk['id']) ? 'selected' : ''; ?>>/<?php echo htmlspecialchars($lnk['slug']); ?></option>
                     <?php endforeach; ?>
                 </select>
             </form>
             <a href="?action=export_stats" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-download"></i> <?php echo $text['btn_export_stats']; ?></a>
             <a href="?clear_stats=1" class="btn btn-outline-danger btn-sm" onclick="return confirm('<?php echo $text['confirm_clear_stats']; ?>')"><i class="bi bi-trash"></i> <?php echo $text['btn_clear_stats_short']; ?></a>
        </div>
    </div>
    
    <div class="row g-4 mb-4">
        <!-- 1. Trend Chart -->
        <div class="col-lg-8">
            <div class="card p-4 h-100">
                <h5 class="mb-4"><?php echo $text['ana_trend']; ?></h5>
                <canvas id="trendChart" height="100"></canvas>
            </div>
        </div>
        <!-- 2. Geo Map -->
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h5 class="mb-4"><?php echo $text['ana_map']; ?></h5>
                <div id="world-map" style="width: 100%; height: 250px;"></div>
                <div class="mt-3">
                    <?php 
                    arsort($geo_stats);
                    $top_geo = array_slice($geo_stats, 0, 5);
                    foreach($top_geo as $cc => $cnt): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted"><img src="https://flagcdn.com/16x12/<?php echo strtolower($cc); ?>.png" class="me-2"> <?php echo $cc; ?></span>
                        <span class="badge bg-light text-dark"><?php echo $cnt; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="mb-3"><?php echo $text['ana_device']; ?></h5>
                <canvas id="deviceChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="mb-3"><?php echo $text['ana_os']; ?></h5>
                <canvas id="osChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 h-100">
                <h5 class="mb-3"><?php echo $text['ana_browser']; ?></h5>
                <canvas id="browserChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Referer Analysis Row -->
    <div class="row g-4 mt-2 mb-4">
        <div class="col-12">
            <div class="card p-4">
                <h5 class="mb-3"><?php echo $text['chart_referer'] ?? 'Top Referers'; ?></h5>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Source</th>
                                <th class="text-end">Clicks</th>
                                <th class="text-end" style="width: 200px;">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_refs = array_sum($referer_stats); 
                            foreach($referer_stats as $ref => $cnt): 
                                $pct = $total_refs > 0 ? round(($cnt / $total_refs) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><div class="text-truncate" style="max-width: 400px;"><?php echo htmlspecialchars($ref); ?></div></td>
                                <td class="text-end fw-bold"><?php echo $cnt; ?></td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end">
                                        <span class="me-2 text-muted small"><?php echo $pct; ?>%</span>
                                        <div class="progress" style="height: 6px; width: 80px;">
                                            <div class="progress-bar bg-info" style="width: <?php echo $pct; ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($referer_stats)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Trend
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_keys($trend_data)); ?>,
            datasets: [{
                label: 'Clicks',
                data: <?php echo json_encode(array_values($trend_data)); ?>,
                borderColor: '#2563eb',
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(37, 99, 235, 0.1)'
            }]
        },
        options: { plugins: { legend: {display: false} }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // Donut Config Helper
    const donutConfig = (labels, data) => ({
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                borderWidth: 0
            }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, cutout: '70%' }
    });

    // Charts
    new Chart(document.getElementById('deviceChart'), donutConfig(
        <?php echo json_encode(array_keys($device_stats)); ?>, 
        <?php echo json_encode(array_values($device_stats)); ?>
    ));
    new Chart(document.getElementById('osChart'), donutConfig(
        <?php echo json_encode(array_keys($os_stats)); ?>, 
        <?php echo json_encode(array_values($os_stats)); ?>
    ));
    new Chart(document.getElementById('browserChart'), donutConfig(
        <?php echo json_encode(array_keys($browser_stats)); ?>, 
        <?php echo json_encode(array_values($browser_stats)); ?>
    ));
    
    // Map
    const mapData = <?php echo json_encode($geo_stats); ?>;
    new jsVectorMap({
        selector: "#world-map",
        map: "world",
        visualizeData: { scale: ['#dbeafe', '#1e40af'], values: mapData },
        onRegionTooltipShow(event, tooltip, code) {
            tooltip.text(
                tooltip.text() + " (" + (mapData[code] || 0) + ")"
            )
        }
    });
    </script>
    
    <?php elseif($view === 'api'): ?>
    <!-- API VIEW -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-5 text-center">
                <div class="mb-4 text-primary display-1"><i class="bi bi-code-square"></i></div>
                <h3 class="mb-4"><?php echo $text['api_title']; ?></h3>
                
                <div class="mb-4">
                    <label class="form-label text-muted"><?php echo $text['api_key_label']; ?></label>
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control text-center text-monospace bg-light" id="apiKeyField" value="<?php echo $api_key ?: 'Not Generated'; ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyApiKey()"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <small class="text-muted mt-2 d-block"><?php echo $text['api_usage_tip']; ?></small>
                </div>

                <form method="POST" onsubmit="return confirm('<?php echo $text['api_reset_confirm']; ?>');">
                    <input type="hidden" name="action" value="reset_api">
                    <?php if(!$api_key): ?>
                    <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill shadow"><?php echo $text['api_btn_gen']; ?></button>
                    <?php else: ?>
                    <button type="submit" class="btn btn-outline-danger px-4 rounded-pill"><?php echo $text['api_btn_reset']; ?></button>
                    <?php endif; ?>
                </form>

                <div class="mt-5 text-start bg-dark text-white p-4 rounded-4">
                    <h6 class="text-white-50 border-bottom border-secondary pb-2 mb-3"><?php echo $text['api_quick_start']; ?></h6>
                    <p class="small text-white-50 mb-2">Endpoint: <code>https://<?php echo PRIMARY_DOMAIN; ?>/api/v1/links</code></p>
                    <pre class="mb-3 text-white" style="font-size: 0.85rem"><code># Option A: JSON (Recommended)
curl -X POST https://<?php echo PRIMARY_DOMAIN; ?>/api/v1/links \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: <?php echo $api_key ?: 'YOUR_KEY'; ?>" \
  -d '{"target_url":"https://example.com"}'

# Option B: Form Data
curl -X POST https://<?php echo PRIMARY_DOMAIN; ?>/api/v1/links \
  -H "X-API-KEY: <?php echo $api_key ?: 'YOUR_KEY'; ?>" \
  -d "target_url=https://example.com"</code></pre>
                    <div class="alert alert-info py-2 px-3 small mt-3 mb-0 border-0 rounded-3 text-dark">
                        <i class="bi bi-info-circle-fill me-1"></i> Not working? Try: <code>https://<?php echo PRIMARY_DOMAIN; ?>/api.php?action=create</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif($view === 'domains' && $user_tier === 'vip'): ?>
    <!-- DOMAINS VIEW -->
    <div class="row">
        <div class="col-md-4">
            <div class="card p-4">
                <h5 class="mb-3"><?php echo $text['dom_add_btn']; ?></h5>
                <form method="POST">
                    <input type="hidden" name="action" value="add_domain">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">DOMAIN</label>
                        <input type="text" name="domain" class="form-control" placeholder="link.mydomain.com" required>
                    </div>
                    <div class="alert alert-warning small p-2">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $text['dom_tip']; ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><?php echo $text['dom_add_btn']; ?></button>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card p-0">
                <div class="card-header bg-white py-3 px-4">
                    <h5 class="mb-0"><?php echo $text['dom_title']; ?></h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4"><?php echo $text['dom_name']; ?></th>
                                <th><?php echo $text['dom_status']; ?></th>
                                <th class="text-end pe-4"><?php echo $text['table_actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $doms = $pdo->prepare("SELECT * FROM custom_domains WHERE user_id = ?");
                            $doms->execute([$user_id]);
                            $has_dom = false;
                            while($d = $doms->fetch(PDO::FETCH_ASSOC)): $has_dom = true;
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo htmlspecialchars($d['domain']); ?></td>
                                <td>
                                    <?php if($d['status']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3"><?php echo $text['dom_verified']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3"><?php echo $text['dom_pending']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="?view=domains&del_domain=<?php echo $d['id']; ?>" class="btn btn-sm btn-light text-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(!$has_dom): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted"><?php echo $text['dom_empty']; ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif($view === 'design' && $user_tier === 'vip'): ?>
    <!-- DESIGN & BRANDING VIEW (SIMPLIFIED) -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-4 border-0 shadow-sm rounded-4">
                <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2 me-3"><i class="bi bi-palette-fill fs-4"></i></div>
                    <div>
                        <h5 class="mb-0 fw-bold"><?php echo $text['design_title']; ?></h5>
                        <p class="text-muted small mb-0">Customize the look of your interstitial pages.</p>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="update_branding_opts">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted"><?php echo $text['design_logo']; ?></label>
                        <input type="url" name="custom_logo" class="form-control bg-light border-0" placeholder="https://..." value="<?php echo htmlspecialchars(get_user_option($pdo, $user_id, 'custom_logo')); ?>">
                        <div class="form-text small"><?php echo $text['des_logo_tip']; ?></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted"><?php echo $text['ph_main_heading']; ?></label>
                        <input type="text" name="custom_heading" class="form-control bg-light border-0" placeholder="e.g. Security Check" value="<?php echo htmlspecialchars(get_user_option($pdo, $user_id, 'custom_heading')); ?>">
                        <div class="form-text small"><?php echo $text['des_heading_tip']; ?></div>
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="mb-1 fw-bold"><?php echo $text['des_remove_branding']; ?></h6>
                                <p class="text-muted small mb-0"><?php echo $text['des_remove_branding_tip']; ?></p>
                            </div>
                            <div class="form-check form-switch fs-4">
                                <input class="form-check-input" type="checkbox" name="remove_branding" value="1" id="rmBrand" <?php echo get_user_option($pdo, $user_id, 'remove_branding') === '1' ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm"><?php echo $text['btn_save']; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php elseif($view === 'security' && $user_tier === 'vip'): ?>
    <!-- Security & White-labeling VIEW -->
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card p-4">
                <h5 class="mb-4"><i class="bi bi-shield-lock-fill text-success me-2"></i> <?php echo $text['sec_title']; ?> & White-labeling</h5>
                <form method="POST">
                     <input type="hidden" name="action" value="update_security">
                     <div class="row g-4">
                         <div class="col-md-7">
                             <div class="mb-3">
                                 <label class="form-label fw-bold small"><?php echo $text['sec_ref_label']; ?></label>
                                 <textarea name="allowed_referers" class="form-control bg-light" rows="4" placeholder="<?php echo $text['sec_ref_ph']; ?>"><?php echo htmlspecialchars(get_user_option($pdo, $user_id, 'allowed_referers')); ?></textarea>
                                 <div class="form-text small"><?php echo $text['sec_ref_tip']; ?></div>
                             </div>
                         </div>
                         <div class="col-md-5">
                             <div class="mb-3">
                                 <label class="form-label fw-bold small"><?php echo $text['sec_white_label']; ?></label>
                                 <textarea name="whitelist" class="form-control bg-light" rows="4" placeholder="<?php echo $text['sec_white_ph']; ?>"><?php echo htmlspecialchars(get_user_option($pdo, $user_id, 'whitelist')); ?></textarea>
                                 <div class="form-text small"><?php echo $text['sec_white_tip']; ?></div>
                             </div>
                         </div>
                     </div>
                     <div class="mt-2 text-end">
                         <button type="submit" class="btn btn-success"><i class="bi bi-check-lg pe-1"></i> <?php echo $text['btn_save_sec']; ?></button>
                     </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php elseif($view === 'upgrade'): ?>
    <!-- UPGRADE VIEW -->
    <div class="text-center py-5">
        <h2 class="fw-bold mb-3"><?php echo $text['vip_title']; ?></h2>
        <p class="text-muted lead mb-5"><?php echo $text['vip_desc']; ?></p>

        <div class="row justify-content-center g-4">
            <!-- Monthly Plan -->
            <div class="col-md-4">
                <div class="card p-4 h-100 shadow-sm">
                   <h3 class="text-dark mt-3"><?php echo $text['plan_monthly']; ?></h3>
                   <div class="display-6 fw-bold my-3">¥<?php echo PRICE_MONTHLY; ?></div>
                   <p class="text-muted small"><?php echo $text['plan_monthly_desc']; ?></p>
                   <button onclick="openPayModal('monthly', '<?php echo PRICE_MONTHLY; ?>')" class="btn btn-outline-primary w-100 rounded-pill mt-auto"><?php echo $text['btn_pay']; ?></button>
                </div>
            </div>
            
            <!-- Quarterly Plan (Popular) -->
            <div class="col-md-4">
                <div class="card p-4 h-100 border-primary shadow-sm">
                   <div class="badge bg-primary position-absolute top-0 end-0 m-3"><?php echo $text['plan_popular']; ?></div>
                   <h3 class="text-primary mt-3"><?php echo $text['plan_quarterly']; ?></h3>
                   <div class="display-6 fw-bold my-3">¥<?php echo PRICE_QUARTERLY; ?></div>
                   <p class="text-muted small"><?php echo $text['plan_quarterly_desc']; ?></p>
                   <button onclick="openPayModal('quarterly', '<?php echo PRICE_QUARTERLY; ?>')" class="btn btn-primary w-100 rounded-pill mt-auto"><?php echo $text['btn_pay']; ?></button>
                </div>
            </div>
            
            <!-- Yearly Plan -->
            <div class="col-md-4">
                <div class="card p-4 h-100 shadow-sm">
                   <h3 class="text-success mt-3"><?php echo $text['plan_yearly']; ?></h3>
                   <div class="display-6 fw-bold my-3">¥<?php echo PRICE_YEARLY; ?></div>
                   <p class="text-muted small"><?php echo $text['plan_yearly_desc']; ?></p>
                   <button onclick="openPayModal('yearly', '<?php echo PRICE_YEARLY; ?>')" class="btn btn-outline-success w-100 rounded-pill mt-auto"><?php echo $text['btn_pay']; ?></button>
                </div>
            </div>
        </div>

        <!-- Redeem Section -->
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card p-4 bg-light">
                    <h5 class="mb-3"><?php echo $text['form_redeem_code']; ?></h5>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action" value="redeem_code">
                        <input type="text" name="code" class="form-control form-control-lg text-uppercase" placeholder="<?php echo $text['ph_redeem_code']; ?>" required>
                        <button type="submit" class="btn btn-dark px-4"><?php echo $text['btn_redeem']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif($view === 'settings'): ?>
    <!-- ACCOUNT SETTINGS -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card p-4 mb-4">
                <h5 class="mb-4 border-bottom pb-2"><?php echo $text['set_info']; ?></h5>
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label text-muted"><?php echo $text['set_username']; ?></label>
                    <div class="col-sm-9">
                        <input type="text" readonly class="form-control-plaintext fw-bold" value="<?php echo htmlspecialchars($username); ?>">
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label text-muted"><?php echo $text['set_tier']; ?></label>
                    <div class="col-sm-9 d-flex align-items-center">
                        <?php if($user_tier === 'vip'): ?>
                            <span class="badge bg-warning text-dark me-2">VIP Pro</span>
                            <span class="text-success small"><i class="bi bi-check-circle-fill"></i> <?php echo $text['vip_active']; ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary me-2">Free</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if($user_tier === 'vip'): ?>
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label text-muted"><?php echo $text['set_expiry']; ?></label>
                    <div class="col-sm-9">
                        <input type="text" readonly class="form-control-plaintext" value="<?php echo $vip_expiry; ?>">
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="card p-4">
                <h5 class="mb-3 text-primary"><i class="bi bi-shield-lock-fill me-2"></i> <?php echo $text['set_security']; ?></h5>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div class="mb-3">
                        <label class="form-label small text-muted"><?php echo $text['set_new_pwd']; ?></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted"><?php echo $text['set_confirm_pwd']; ?></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><?php echo $text['btn_update_pwd']; ?></button>
                </form>
            </div>
        </div>
    </div>
    <?php elseif($view === 'admin' && $is_admin): ?>
    <!-- SYSTEM ADMIN VIEW -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card p-4 text-center border-0 shadow-sm rounded-4">
                <div class="text-primary mb-2 display-6 fw-bold"><?php echo $total_users; ?></div>
                <div class="text-muted small fw-bold text-uppercase"><?php echo $text['admin_total_users']; ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 text-center border-0 shadow-sm rounded-4">
                <div class="text-success mb-2 display-6 fw-bold"><?php echo $total_links; ?></div>
                <div class="text-muted small fw-bold text-uppercase"><?php echo $text['admin_total_links']; ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- 1. Top Links -->
        <div class="col-lg-8">
            <div class="card p-0 border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-0">
                    <h5 class="mb-0 fw-bold text-primary"><i class="bi bi-fire me-2"></i> <?php echo $text['admin_top_links']; ?></h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th class="ps-4">Slug</th>
                                <th>Target / Owner</th>
                                <th class="text-end pe-4">Clicks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($admin_top_links as $tl): ?>
                            <tr>
                                <td class="ps-4 fw-bold">/<?php echo htmlspecialchars($tl['slug']); ?></td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px; font-size: 0.85rem"><?php echo htmlspecialchars($tl['target_url']); ?></div>
                                    <small class="text-muted">by <i class="bi bi-person"></i> <?php echo htmlspecialchars($tl['username']); ?></small>
                                </td>
                                <td class="text-end pe-4"><span class="badge bg-primary rounded-pill"><?php echo $tl['clicks']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. System Settings & Activation Codes -->
        <div class="col-lg-4">
            <!-- System Settings -->
            <div class="card p-4 border-0 shadow-sm rounded-4 mb-4">
                <h5 class="mb-4 fw-bold text-success"><i class="bi bi-gear-fill me-2"></i> <?php echo $text['admin_sys_settings']; ?></h5>
                <form method="POST">
                    <input type="hidden" name="action" value="admin_update_sys_security">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted"><?php echo $text['admin_sys_ref']; ?></label>
                        <textarea name="sys_allowed_referers" class="form-control bg-light border-0 small" rows="3"><?php echo htmlspecialchars($sys_allowed_referers); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Google Safe Browsing API Key</label>
                        <input type="text" name="sys_safe_browsing_key" class="form-control bg-light border-0 small" value="<?php echo htmlspecialchars($sys_sb_key); ?>" placeholder="API Key">
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted"><?php echo $text['admin_sys_white']; ?></label>
                        <textarea name="sys_whitelist" class="form-control bg-light border-0 small" rows="3"><?php echo htmlspecialchars($sys_whitelist); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2 rounded-pill fw-bold shadow-sm"><?php echo $text['admin_btn_save']; ?></button>
                </form>
            </div>

            <!-- Activation Code Generator -->
            <div class="card p-4 border-0 shadow-sm rounded-4">
                <h5 class="mb-3 fw-bold text-warning"><i class="bi bi-ticket-perforated me-2"></i> <?php echo $text['admin_gen_code']; ?></h5>
                <form method="POST" class="mb-3">
                    <input type="hidden" name="action" value="admin_gen_code">
                    <div class="input-group">
                        <input type="number" name="code_days" class="form-control bg-light border-0" placeholder="<?php echo $text['admin_code_days']; ?>" value="365" min="1" max="3650" required>
                        <button type="submit" class="btn btn-warning fw-bold"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </form>
                
                <?php 
                // 只显示未使用的激活码
                $unused_codes = array_filter($admin_codes, function($code) {
                    return $code['status'] == 0;
                });
                $unused_count = count($unused_codes);
                ?>
                
                <div class="small">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-bold"><?php echo $text['admin_code_list']; ?> (<?php echo $unused_count; ?>)</div>
                        <?php if ($unused_count > 0): ?>
                        <button onclick="exportCodes()" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-download"></i> <?php echo $lang === 'zh' ? '导出' : 'Export'; ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($unused_count > 0): ?>
                    <div style="max-height: 300px; overflow-y: auto;" id="codesList">
                        <?php foreach($unused_codes as $ac): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <code class="small"><?php echo $ac['code']; ?></code>
                                <button onclick="copyCode('<?php echo $ac['code']; ?>')" class="btn btn-sm btn-link p-0" title="<?php echo $lang === 'zh' ? '复制' : 'Copy'; ?>">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <span class="badge bg-success small"><?php echo $ac['days']; ?> <?php echo $lang === 'zh' ? '天' : 'days'; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-muted text-center py-3">
                        <i class="bi bi-inbox"></i> <?php echo $lang === 'zh' ? '暂无未使用的激活码' : 'No unused codes'; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. User Management -->
    <div class="card p-0 border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 px-4 border-0">
            <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2"></i> <?php echo $text['admin_user_mgmt']; ?></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="small text-muted text-uppercase">
                        <th class="ps-4"><?php echo $text['admin_username']; ?></th>
                        <th><?php echo $text['admin_tier']; ?></th>
                        <th><?php echo $text['admin_link_count']; ?></th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($admin_users as $au): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold d-flex align-items-center">
                                <?php echo htmlspecialchars($au['username']); ?>
                                <?php if($au['id'] == $user_id): ?><span class="badge bg-info text-white ms-2" style="font-size: 0.6rem">YOU</span><?php endif; ?>
                            </div>
                            <small class="text-muted" style="font-size: 0.7rem">Joined: <?php echo date('M d, Y', strtotime($au['created_at'])); ?></small>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="admin_toggle_vip">
                                <input type="hidden" name="user_id" value="<?php echo $au['id']; ?>">
                                <?php if($au['user_tier'] === 'vip'): ?>
                                    <button type="submit" class="btn p-0 border-0"><span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> VIP Pro</span></button>
                                <?php else: ?>
                                    <button type="submit" class="btn p-0 border-0"><span class="badge bg-secondary">Free</span></button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td><span class="badge bg-light text-dark border px-2"><?php echo $au['link_count']; ?></span></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="admin_toggle_status">
                                <input type="hidden" name="user_id" value="<?php echo $au['id']; ?>">
                                <?php if($au['status']): ?>
                                    <button type="submit" class="btn p-0 border-0 text-success small"><i class="bi bi-check-circle-fill"></i> Active</button>
                                <?php else: ?>
                                    <button type="submit" class="btn p-0 border-0 text-danger small"><i class="bi bi-x-circle-fill"></i> Blocked</button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            <?php if($au['id'] != $user_id): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('DELETE USER AND ALL THEIR LINKS?')">
                                <input type="hidden" name="action" value="admin_delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $au['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    function copyUrl(url) {
        navigator.clipboard.writeText(url);
        // Show lightweight toast
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '1100';
        toast.innerHTML = `<div class="toast show align-items-center text-white bg-dark border-0" role="alert"><div class="d-flex"><div class="toast-body"><?php echo $text['msg_copied']; ?></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function copyApiKey() {
        const key = document.getElementById('apiKeyField');
        if(key) {
            key.select();
            navigator.clipboard.writeText(key.value);
            alert("<?php echo $text['api_copy_success']; ?>");
        }
    }
</script>

<!-- Payment Selection Modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><?php echo $text['pay_modal_title']; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-0">
        <p class="text-muted mb-4"><?php echo $text['pay_modal_desc']; ?> <span id="payAmount" class="fw-bold text-dark"></span></p>
        <div class="d-grid gap-2">
            <a href="#" id="btnAlipay" class="btn btn-outline-primary btn-lg rounded-pill p-3 text-start">
                <i class="bi bi-alipay text-primary me-2"></i> <?php echo $text['pay_alipay']; ?>
            </a>
            <!-- LINUX DO Credit 暂时禁用
            <a href="#" id="btnEpay" class="btn btn-outline-success btn-lg rounded-pill p-3 text-start">
                <i class="bi bi-credit-card text-success me-2"></i> <?php echo $text['pay_other']; ?>
            </a>
            -->
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function openPayModal(plan, price) {
    document.getElementById('payAmount').innerText = '(CNY ' + price + ')';
    document.getElementById('btnAlipay').href = 'pay.php?method=alipay&plan=' + plan;
    document.getElementById('btnEpay').href = 'pay.php?method=epay&plan=' + plan;
    var myModal = new bootstrap.Modal(document.getElementById('payModal'));
    myModal.show();
}

function genQuickLinks() {
    const rawUrl = document.getElementById('toolRawUrl').value.trim();
    const quickBox = document.getElementById('quickLinks');
    
    if (!rawUrl.startsWith('http')) {
        quickBox.classList.add('d-none');
        return;
    }
    
    quickBox.classList.remove('d-none');
    const baseUrl = window.location.origin + window.location.pathname.replace('dashboard_pro.php', 'go_pro.php');
    
    // Base64
    const encoded = btoa(unescape(encodeURIComponent(rawUrl)));
    document.getElementById('linkBase64').value = baseUrl + '?url=' + encoded;
    
    // Direct
    document.getElementById('linkDirect').value = baseUrl + '?url=' + rawUrl;
}

function copyToClipboard(id) {
    const copyText = document.getElementById(id);
    if (!copyText.value) return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    
    // Use the copied message from translations if possible, or fallback
    alert("<?php echo $text['msg_copied'] ?? 'Copied!'; ?>");
}

// 复制单个激活码
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        alert("<?php echo $lang === 'zh' ? '已复制: ' : 'Copied: '; ?>" + code);
    });
}

// 导出所有未使用的激活码
function exportCodes() {
    const codesList = document.getElementById('codesList');
    if (!codesList) return;
    
    const codes = [];
    codesList.querySelectorAll('code').forEach(el => {
        codes.push(el.textContent);
    });
    
    if (codes.length === 0) return;
    
    // 创建文本内容
    const content = codes.join('\n');
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    
    // 创建下载链接
    const a = document.createElement('a');
    a.href = url;
    a.download = 'activation_codes_' + new Date().toISOString().slice(0,10) + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Payment Modal Logic
let selectedPaymentMethod = 'alipay';

function openPayModal(plan, price) {
    document.getElementById('payPlan').value = plan;
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    // Visually update selection
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('border-primary', 'bg-light'));
    document.getElementById('opt-' + method).classList.add('border-primary', 'bg-light');
}

function proceedToPay() {
    const plan = document.getElementById('payPlan').value;
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'pay.php';
    
    const inputPlan = document.createElement('input');
    inputPlan.type = 'hidden';
    inputPlan.name = 'plan';
    inputPlan.value = plan;
    
    const inputMethod = document.createElement('input');
    inputMethod.type = 'hidden';
    inputMethod.name = 'method';
    inputMethod.value = selectedPaymentMethod;
    
    form.appendChild(inputPlan);
    form.appendChild(inputMethod);
    document.body.appendChild(form);
    form.submit();
}
</script>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><?php echo $text['modal_pay_title']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="payPlan">
                
                <div class="d-grid gap-3">
                    <!-- Alipay -->
                    <div class="card p-3 payment-option border-primary bg-light" id="opt-alipay" onclick="selectPaymentMethod('alipay')" style="cursor:pointer">
                        <div class="d-flex align-items-center">
                            <span class="fs-2 me-3 text-primary"><i class="bi bi-alipay"></i></span>
                            <div>
                                <h6 class="mb-0 fw-bold"><?php echo $text['pay_alipay']; ?></h6>
                                <small class="text-muted"><?php echo $text['pay_alipay_desc']; ?></small>
                            </div>
                            <div class="ms-auto"><i class="bi bi-check-circle-fill text-primary"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary w-100 rounded-pill py-2 fw-bold" onclick="proceedToPay()"><?php echo $text['btn_continue_pay']; ?></button>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Social & Footer Footer -->
<div class="footer-social-section">
    <div class="container">
        <div class="social-links">
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
        <p class="text-muted small mb-0"><?php echo $text['footer']; ?></p>
    </div>
</div>

<!-- WeChat QR Popover Template -->
<div id="wechatPopoverContent" style="display:none;">
    <div class="wechat-popover-content text-center p-2">
        <img src="https://pickoala.com/img/images/2026/01/05/RM45hGH4.webp" alt="WeChat QR Code" class="img-fluid">
        <div class="mt-2 small text-dark">扫描二维码添加微信</div>
    </div>
</div>

<!-- Crisp Live Chat -->
<script type="text/javascript">
    window.$crisp=[];
    window.CRISP_WEBSITE_ID="e50e2b74-a90b-4767-8eb5-d6d2447edcb2";
    (function(){
        d=document;
        s=d.createElement("script");
        s.src="https://client.crisp.chat/l.js";
        s.async=1;
        d.getElementsByTagName("head")[0].appendChild(s);
    })();

    // Analytics Tracker
    (function() {
        const s = document.createElement('script');
        s.async = true;
        s.defer = true;
        s.src = "https://dsi.mom/tracker.js";
        s.setAttribute('data-website-id', "cmjzibwln005kx3up8l481570");
        document.body.appendChild(s);
    })();

    // WeChat Popover
    document.addEventListener('DOMContentLoaded', function() {
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
    });
</script>
</body>
</html>


