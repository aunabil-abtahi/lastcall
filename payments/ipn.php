<?php
require_once __DIR__."/../config/database.php"; require_once __DIR__."/../includes/payment_service.php";
http_response_code(200); $tran=trim($_POST['tran_id']??''); $val=trim($_POST['val_id']??'');
if($tran!=='' && $val!=='') { try { $result=finalizeSslcommerzPayment($pdo,$tran,sslcommerzValidate($val,sslcommerzConfig())); echo $result['ok']?'OK':'INVALID'; } catch(Throwable $e) { error_log('SSLCOMMERZ IPN: '.$e->getMessage()); echo 'ERROR'; } } else { echo 'INVALID'; }
