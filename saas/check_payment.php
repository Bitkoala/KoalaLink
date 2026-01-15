<?php
/**
 * 支付状态检查接口
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$order_no = $_GET['order_no'] ?? '';

if (empty($order_no)) {
    echo json_encode(['paid' => false]);
    exit;
}

$pdo = init_saas_db($db_path);

// 检查订单状态
$stmt = $pdo->prepare("SELECT status FROM orders WHERE order_no = ?");
$stmt->execute([$order_no]);
$status = $stmt->fetchColumn();

echo json_encode(['paid' => ($status == 1)]);
