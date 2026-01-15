<?php
/**
 * 支付回调处理 - 支持支付宝和 LINUX DO Credit
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = init_saas_db($db_path);

// 判断回调类型
if (isset($_POST['trade_status']) || isset($_GET['trade_status'])) {
    // 支付宝回调
    $params = $_POST ?: $_GET;
    
    // 验证签名
    $sign = $params['sign'] ?? '';
    $sign_type = $params['sign_type'] ?? '';
    unset($params['sign'], $params['sign_type']);
    
    ksort($params);
    $sign_str = '';
    foreach ($params as $k => $v) {
        if ($v !== '' && $k != 'sign' && $k != 'sign_type') {
            $sign_str .= $k . '=' . $v . '&';
        }
    }
    $sign_str = rtrim($sign_str, '&');
    
    // 使用支付宝公钥验证签名
    $res = "-----BEGIN PUBLIC KEY-----\n" .
        wordwrap(ALIPAY_PUBLIC_KEY, 64, "\n", true) .
        "\n-----END PUBLIC KEY-----";
    
    $verify = openssl_verify($sign_str, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
    
    if ($verify !== 1) {
        exit('FAIL: Invalid signature');
    }
    
    // 验证交易状态
    if ($params['trade_status'] !== 'TRADE_SUCCESS') {
        exit('FAIL: Trade not successful');
    }
    
    $out_trade_no = $params['out_trade_no'];
    $trade_no = $params['trade_no'];
    $total_amount = $params['total_amount'];
    
    // 检查订单是否已处理
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = ?");
    $stmt->execute([$out_trade_no]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        exit('FAIL: Order not found');
    }
    
    if ($order['status'] == 1) {
        exit('success'); // 已处理
    }
    
    // 更新订单状态
    $stmt = $pdo->prepare("UPDATE orders SET status = 1, ali_trade_no = ?, paid_at = datetime('now') WHERE order_no = ?");
    $stmt->execute([$trade_no, $out_trade_no]);
    
    // 开通 VIP
    $user_id = $order['user_id'];
    $plan = $order['plan'];
    
    $days_map = [
        'monthly' => 30,
        'quarterly' => 90,
        'yearly' => 365
    ];
    
    $days = $days_map[$plan] ?? 0;
    
    if ($days > 0) {
        $stmt = $pdo->prepare("SELECT vip_expiry FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_expiry = $stmt->fetchColumn();
        
        if ($current_expiry && strtotime($current_expiry) > time()) {
            $new_expiry = date('Y-m-d H:i:s', strtotime($current_expiry) + ($days * 86400));
        } else {
            $new_expiry = date('Y-m-d H:i:s', time() + ($days * 86400));
        }
        
        $stmt = $pdo->prepare("UPDATE users SET user_tier = 'vip', vip_expiry = ? WHERE id = ?");
        $stmt->execute([$new_expiry, $user_id]);
    }
    
    exit('success');
    
} else {
    // 非 Alipay 回调
    exit('FAIL: Unknown callback');
}
