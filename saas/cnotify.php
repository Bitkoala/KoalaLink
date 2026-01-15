<?php
/**
 * KoalaLink SaaS - Payment Callback (EPay)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// EPay Callback Params
$pid = $_GET['pid'] ?? '';
$trade_no = $_GET['trade_no'] ?? '';
$out_trade_no = $_GET['out_trade_no'] ?? '';
$type = $_GET['type'] ?? '';
$name = $_GET['name'] ?? '';
$money = $_GET['money'] ?? '';
$trade_status = $_GET['trade_status'] ?? '';
$sign = $_GET['sign'] ?? '';
$sign_type = $_GET['sign_type'] ?? 'MD5';

// Verify Sign
$param = $_GET;
unset($param['sign']);
unset($param['sign_type']);
ksort($param);

$sign_str = '';
foreach ($param as $k => $v) {
    if ($v != '') {
        $sign_str .= $k . '=' . $v . '&';
    }
}
$sign_str = rtrim($sign_str, '&') . EPAY_KEY;
$my_sign = md5($sign_str);

if ($sign == $my_sign && $trade_status == 'TRADE_SUCCESS') {
    $pdo = init_saas_db($db_path);

    // Extract User ID from out_trade_no (last digits part of logic or lookup)
    // Here we assume out_trade_no format: YmdHis . rand . UserID
    // But better to store order in DB. For simplicity, we might parse it if we encoded it.
    // Or if we didn't store it, we rely on session or other logic? 
    // Wait, callback has no session. We need to know WHICH user paid.
    // Let's assume out_trade_no ends with UserID as implemented in pay.php

    // Extract user_id: Remove first 14 chars (YmdHis) + 3 chars (rand) -> rest is UserID
    // Format: YmdHis (14) + rand (3) + UserID
    // 20230101010101 123 5
    if (strlen($out_trade_no) > 17) {
        $user_id = substr($out_trade_no, 17);
        
        // Update User VIP
        $stmt = $pdo->prepare("SELECT vip_expiry FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_expiry = $stmt->fetchColumn();

        $days = 30; // Default
        if (strpos($name, 'Quarterly') !== false) $days = 90;
        if (strpos($name, 'Yearly') !== false) $days = 365;

        $now = date('Y-m-d H:i:s');
        $base = ($current_expiry && $current_expiry > $now) ? $current_expiry : $now;
        $new_expiry = date('Y-m-d H:i:s', strtotime($base . " + $days days"));

        $pdo->prepare("UPDATE users SET user_tier = 'vip', vip_expiry = ? WHERE id = ?")
            ->execute([$new_expiry, $user_id]);
            
        echo "success"; // Important for EPay
    } else {
        echo "fail";
    }
} else {
    echo "fail";
}
