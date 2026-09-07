<?php
require_once __DIR__."/../config/database.php"; require_once __DIR__."/../includes/payment_service.php";
$tran=trim($_POST['tran_id']??''); if($tran!=='') markSslcommerzPaymentClosed($pdo,$tran,'cancelled',$_POST);
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="../assets/css/style.css"><title>Payment Cancelled</title></head><body><main class="auth-page"><section class="form-card"><h1>Payment Cancelled</h1><p class="form-intro">No payment was completed. Your reserved item has been released.</p><a class="primary-link" href="../index.php">Back to LastCall</a></section></main></body></html>
