<?php
/**
 * KoalaLink SaaS - Payment Gateway Router
 * Supports: Alipay F2F (Native) & EPay (YiPay)
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['saas_user_id'])) {
    header("Location: auth.php");
    exit;
}

// 支持 POST (来自 Modal) 和 GET
$req = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$method = $req['method'] ?? 'alipay';
$plan = $req['plan'] ?? 'monthly';
$money = 0;
$name = '';
$days = 0;

switch ($plan) {
    case 'monthly':
        $money = PRICE_MONTHLY;
        $name = 'VIP Monthly Subscription';
        break;
    case 'quarterly':
        $money = PRICE_QUARTERLY;
        $name = 'VIP Quarterly Subscription';
        break;
    case 'yearly':
        $money = PRICE_YEARLY;
        $name = 'VIP Yearly Subscription';
        break;
    default:
        exit('Invalid Plan');
}

$user_id = $_SESSION['saas_user_id'];
$out_trade_no = date("YmdHis") . mt_rand(100, 999) . $user_id;
$pdo = init_saas_db($db_path);

// --- ALIPAY F2F LOGIC ---
if ($method === 'alipay') {
    if (empty(ALIPAY_APP_ID) || empty(ALIPAY_PRIVATE_KEY)) {
        exit('Alipay not configured.');
    }

    $notify_url = (isset($_SERVER['HTTPS'])?'https':'http').'://' . $_SERVER['HTTP_HOST'] . '/notify.php';
    
    // Native Alipay Signature Helper
    class AlipayHelper {
        public static function sign($params, $privateKey) {
            ksort($params);
            $stringToBeSigned = "";
            $i = 0;
            foreach ($params as $k => $v) {
                if (false === checkEmpty($v) && "@" != substr($v, 0, 1)) {
                    if ($i == 0) $stringToBeSigned .= "$k" . "=" . "$v";
                    else $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                    $i++;
                }
            }
            unset($k, $v);
            
            $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($privateKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
            
            openssl_sign($stringToBeSigned, $sign, $res, OPENSSL_ALGO_SHA256);
            return base64_encode($sign);
        }
    }
    
    function checkEmpty($value) {
        if (!isset($value)) return true;
        if ($value === null) return true;
        if (trim($value) === "") return true;
        return false;
    }

    $biz_content = json_encode([
        'out_trade_no' => $out_trade_no,
        'total_amount' => $money,
        'subject' => $name,
        'product_code' => 'FACE_TO_FACE_PAYMENT'
    ]);

    $params = [
        'app_id' => ALIPAY_APP_ID,
        'method' => 'alipay.trade.precreate',
        'format' => 'JSON',
        'charset' => 'utf-8',
        'sign_type' => 'RSA2',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0',
        'notify_url' => $notify_url,
        'biz_content' => $biz_content
    ];

    $params['sign'] = AlipayHelper::sign($params, ALIPAY_PRIVATE_KEY);
    
    // 创建订单记录
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_no, plan, amount, status) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$user_id, $out_trade_no, $plan, $money]);
    
    // Send Request
    $url = 'https://openapi.alipay.com/gateway.do';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($response, true);
    $qr_code = $res['alipay_trade_precreate_response']['qr_code'] ?? '';

    if ($qr_code) {
        // 检测设备类型
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_mobile = preg_match('/(android|iphone|ipad|mobile)/i', $ua);
        
        if ($is_mobile) {
            // 移动端：直接唤起支付宝 APP
            header("Location: " . $qr_code);
            exit;
        } else {
            // 桌面端：显示二维码
            ?>
            <!DOCTYPE html>
            <html lang="zh-CN">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>支付宝扫码支付</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
                <style>
                    body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
                    .payment-card { background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
                    #qrcode { display: inline-block; padding: 20px; background: white; border-radius: 10px; }
                    .pulse { animation: pulse 2s infinite; }
                    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
                </style>
            </head>
            <body class="d-flex align-items-center justify-content-center">
                <div class="payment-card p-5 text-center" style="max-width: 450px; width: 100%;">
                    <div class="mb-4">
                        <i class="bi bi-alipay text-primary" style="font-size: 3rem;"></i>
                        <h3 class="mt-3 fw-bold">支付宝扫码支付</h3>
                        <p class="text-muted"><?php echo htmlspecialchars($name); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <h2 class="text-primary fw-bold">¥<?php echo $money; ?></h2>
                    </div>
                    
                    <div id="qrcode" class="mb-4 d-inline-block"></div>
                    
                    <p class="text-muted small mb-4">
                        <i class="bi bi-phone pulse"></i> 请使用支付宝扫一扫
                    </p>
                    
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle"></i> 支付成功后将自动开通 VIP
                    </div>
                    
                    <div class="mt-4">
                        <a href="dashboard_pro.php?view=upgrade" class="btn btn-outline-secondary">返回</a>
                    </div>
                </div>
                
                <script>
                    // 生成二维码
                    new QRCode(document.getElementById("qrcode"), {
                        text: "<?php echo $qr_code; ?>",
                        width: 200,
                        height: 200
                    });
                    
                    // 轮询支付状态
                    let checkCount = 0;
                    const maxChecks = 60; // 最多检查 3 分钟
                    
                    function checkPaymentStatus() {
                        if (checkCount++ >= maxChecks) {
                            clearInterval(pollInterval);
                            return;
                        }
                        
                        fetch('check_payment.php?order_no=<?php echo $out_trade_no; ?>')
                            .then(r => r.json())
                            .then(data => {
                                if (data.paid) {
                                    clearInterval(pollInterval);
                                    window.location.href = 'dashboard_pro.php?view=upgrade&msg=payment_success';
                                }
                            })
                            .catch(e => console.error('Check failed:', e));
                    }
                    
                    const pollInterval = setInterval(checkPaymentStatus, 3000);
                </script>
            </body>
            </html>
            <?php
            exit;
        }
    } else {
        echo "Error creating order: " . ($res['alipay_trade_precreate_response']['sub_msg'] ?? 'Unknown Error');
        if (isset($res['error_response'])) {
             echo "<br>API Error: " . $res['error_response']['sub_msg'];
        }
    }

} else {
    exit('Invalid payment method selected.');
}



