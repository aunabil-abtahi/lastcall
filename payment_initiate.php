<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/maintenance.php";
require_once __DIR__ . "/includes/payment_service.php";

if (!isLoggedIn() || $_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: index.php"); exit; }
require_csrf();
runMarketplaceMaintenance($pdo);
$buyerId = $_SESSION["user_id"];
$reservationId = (int) ($_POST["reservation_id"] ?? 0);

try {
    $config = sslcommerzConfig();
    $pdo->beginTransaction();
    $query = $pdo->prepare("
        SELECT r.reservation_id,r.listing_id,r.ticket_id,r.quantity,r.reserved_price,r.reservation_status,r.expires_at,
               l.title,l.listing_type,u.full_name,u.email,u.phone,loc.address_line,loc.city,loc.area
        FROM reservations r JOIN listings l ON l.listing_id=r.listing_id
        JOIN users u ON u.user_id=r.buyer_id LEFT JOIN locations loc ON loc.location_id=u.location_id
        WHERE r.reservation_id=? AND r.buyer_id=? FOR UPDATE
    ");
    $query->execute([$reservationId,$buyerId]); $r=$query->fetch(PDO::FETCH_ASSOC);
    if (!$r || $r['reservation_status']!=='active' || strtotime($r['expires_at'])<=time()) throw new RuntimeException('This reservation has expired.');
    $existing=$pdo->prepare("SELECT o.order_id,p.payment_id FROM orders o LEFT JOIN payments p ON p.order_id=o.order_id WHERE o.reservation_id=? FOR UPDATE");
    $existing->execute([$reservationId]); $old=$existing->fetch(PDO::FETCH_ASSOC);
    $tran=generateLastCallTransactionId();
    if ($old) {
        $pdo->prepare("UPDATE orders SET order_status='pending_payment' WHERE order_id=?")->execute([$old['order_id']]);
        $pdo->prepare("UPDATE payments SET payment_method='sslcommerz',payment_status='pending',validation_status='pending',transaction_reference=?,gateway_response=NULL WHERE payment_id=?")->execute([$tran,$old['payment_id']]);
        $orderId=$old['order_id'];
    } else {
        $pdo->prepare("INSERT INTO orders (buyer_id,reservation_id,total_amount,order_status) VALUES (?,?,?,'pending_payment')")->execute([$buyerId,$reservationId,$r['reserved_price']]);
        $orderId=$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO order_items (order_id,listing_id,ticket_id,item_title,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?,?)")->execute([$orderId,$r['listing_id'],$r['ticket_id'],$r['title'],$r['quantity'],(float)$r['reserved_price']/(int)$r['quantity'],$r['reserved_price']]);
        $pdo->prepare("INSERT INTO payments (order_id,payment_method,payment_status,currency,validation_status,transaction_reference) VALUES (?,'sslcommerz','pending','BDT','pending',?)")->execute([$orderId,$tran]);
    }
    $pdo->commit();
    $base=$config['app_url'];
    $payload=['store_id'=>$config['store_id'],'store_passwd'=>$config['store_password'],'total_amount'=>number_format((float)$r['reserved_price'],2,'.',''),'currency'=>'BDT','tran_id'=>$tran,'success_url'=>$base.'/payments/success.php','fail_url'=>$base.'/payments/fail.php','cancel_url'=>$base.'/payments/cancel.php','ipn_url'=>$config['ipn_url'] ?: $base.'/payments/ipn.php','cus_name'=>$r['full_name'],'cus_email'=>$r['email'],'cus_add1'=>$r['address_line'] ?: ($r['area'].', '.$r['city']),'cus_city'=>$r['city'],'cus_country'=>'Bangladesh','cus_phone'=>$r['phone'],'product_name'=>$r['title'],'product_category'=>$r['listing_type'],'product_profile'=>'general','shipping_method'=>'NO','value_a'=>(string)$orderId];
    $response=sslcommerzPost($payload,$config);
    header('Location: '.$response['GatewayPageURL']); exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Payment initiation: '.$e->getMessage());
    $_SESSION['payment_error']='Could not start the Sandbox payment. Check credentials and try again.';
    header('Location: checkout.php?reservation_id='.$reservationId); exit;
}
