<?php

require_once __DIR__ . "/../config/payment.php";

function generateLastCallTransactionId(): string {
    return "LC" . date("ymdHis") . strtoupper(bin2hex(random_bytes(4)));
}

function sslcommerzPost(array $payload, array $config): array {
    $handle = curl_init($config["init_url"]);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $body = curl_exec($handle);
    $error = curl_error($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($body === false || $error !== "" || $statusCode !== 200) {
        error_log("SSLCOMMERZ initiation failed: " . $error . " HTTP " . $statusCode);
        throw new RuntimeException("The Sandbox gateway is unavailable. Please try again later.");
    }

    $response = json_decode($body, true);
    if (!is_array($response) || empty($response["GatewayPageURL"])) {
        error_log("SSLCOMMERZ initiation returned an invalid response.");
        throw new RuntimeException("The Sandbox gateway did not provide a payment page.");
    }

    return $response;
}

function sslcommerzValidate(string $validationId, array $config): array {
    $url = $config["validation_url"] . "?" . http_build_query([
        "val_id" => $validationId,
        "store_id" => $config["store_id"],
        "store_passwd" => $config["store_password"],
        "v" => 1,
        "format" => "json"
    ]);

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $body = curl_exec($handle);
    $error = curl_error($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($body === false || $error !== "" || $statusCode !== 200) {
        error_log("SSLCOMMERZ validation failed: " . $error . " HTTP " . $statusCode);
        throw new RuntimeException("Could not validate the Sandbox transaction.");
    }

    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException("SSLCOMMERZ returned an invalid validation response.");
    }

    return $response;
}

function releaseGatewayReservation(PDO $pdo, array $reservation, string $status): void {
    if ($reservation["ticket_id"] === null) {
        $restoreFood = $pdo->prepare("
            UPDATE food_listing_details
            SET quantity_available = LEAST(quantity_total, quantity_available + ?)
            WHERE listing_id = ?
        ");
        $restoreFood->execute([$reservation["quantity"], $reservation["listing_id"]]);

        $restoreListing = $pdo->prepare("
            UPDATE listings l
            JOIN food_listing_details f ON f.listing_id = l.listing_id
            SET l.listing_status = CASE
                WHEN l.pickup_or_event_deadline <= NOW() THEN 'expired'
                WHEN f.quantity_available > 0 THEN 'active'
                ELSE 'sold_out'
            END
            WHERE l.listing_id = ?
              AND l.listing_status IN ('active', 'sold_out')
        ");
        $restoreListing->execute([$reservation["listing_id"]]);
    } else {
        $releaseTicket = $pdo->prepare("
            UPDATE tickets
            SET availability_status = 'available'
            WHERE ticket_id = ?
              AND availability_status = 'reserved'
        ");
        $releaseTicket->execute([$reservation["ticket_id"]]);
    }

    $updateReservation = $pdo->prepare("
        UPDATE reservations
        SET reservation_status = ?
        WHERE reservation_id = ?
          AND reservation_status = 'active'
    ");
    $updateReservation->execute([$status, $reservation["reservation_id"]]);
}

function markSslcommerzPaymentClosed(PDO $pdo, string $transactionId, string $status, array $gatewayData = []): bool {
    $orderStatus = $status === "cancelled" ? "cancelled" : "failed";

    try {
        $pdo->beginTransaction();

        $paymentQuery = $pdo->prepare("
            SELECT p.payment_id, p.payment_status, o.order_id,
                   r.reservation_id, r.listing_id, r.ticket_id, r.quantity
            FROM payments p
            JOIN orders o ON o.order_id = p.order_id
            JOIN reservations r ON r.reservation_id = o.reservation_id
            WHERE p.transaction_reference = ?
            FOR UPDATE
        ");
        $paymentQuery->execute([$transactionId]);
        $payment = $paymentQuery->fetch(PDO::FETCH_ASSOC);

        if (!$payment || $payment["payment_status"] === "paid") {
            $pdo->rollBack();
            return false;
        }

        $updatePayment = $pdo->prepare("
            UPDATE payments
            SET payment_status = ?,
                validation_status = 'failed',
                gateway_response = ?
            WHERE payment_id = ?
        ");
        $updatePayment->execute([
            $status,
            json_encode($gatewayData),
            $payment["payment_id"]
        ]);

        $updateOrder = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
        $updateOrder->execute([$orderStatus, $payment["order_id"]]);

        releaseGatewayReservation($pdo, $payment, "cancelled");
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Could not close SSLCOMMERZ payment: " . $e->getMessage());
        return false;
    }
}

function finalizeSslcommerzPayment(PDO $pdo, string $transactionId, array $validation): array {
    try {
        $pdo->beginTransaction();

        $paymentQuery = $pdo->prepare("
            SELECT p.payment_id, p.order_id, p.payment_status, p.currency,
                   o.buyer_id, o.total_amount, o.order_status,
                   r.reservation_id, r.listing_id, r.ticket_id, r.quantity,
                   r.reservation_status, r.expires_at,
                   t.current_owner_id AS ticket_owner_id,
                   t.availability_status AS ticket_availability
            FROM payments p
            JOIN orders o ON o.order_id = p.order_id
            JOIN reservations r ON r.reservation_id = o.reservation_id
            LEFT JOIN tickets t ON t.ticket_id = r.ticket_id
            WHERE p.transaction_reference = ?
            FOR UPDATE
        ");
        $paymentQuery->execute([$transactionId]);
        $payment = $paymentQuery->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            $pdo->rollBack();
            return ["ok" => false, "message" => "Unknown payment transaction."];
        }

        if ($payment["payment_status"] === "paid") {
            $pdo->rollBack();
            return ["ok" => true, "order_id" => $payment["order_id"], "already_paid" => true];
        }

        $validationStatus = strtoupper((string) ($validation["status"] ?? ""));
        $expectedAmount = number_format((float) $payment["total_amount"], 2, ".", "");
        $validatedAmount = number_format((float) ($validation["amount"] ?? -1), 2, ".", "");
        $valid = in_array($validationStatus, ["VALID", "VALIDATED"], true)
            && hash_equals($transactionId, (string) ($validation["tran_id"] ?? ""))
            && hash_equals($expectedAmount, $validatedAmount)
            && strtoupper((string) ($validation["currency"] ?? "")) === $payment["currency"]
            && (string) ($validation["risk_level"] ?? "0") !== "1";

        if (!$valid || $payment["reservation_status"] !== "active" || strtotime($payment["expires_at"]) <= time()) {
            $updatePayment = $pdo->prepare("
                UPDATE payments
                SET payment_status = 'failed', validation_status = 'failed', gateway_response = ?
                WHERE payment_id = ?
            ");
            $updatePayment->execute([json_encode($validation), $payment["payment_id"]]);
            $pdo->prepare("UPDATE orders SET order_status = 'failed' WHERE order_id = ?")
                ->execute([$payment["order_id"]]);
            releaseGatewayReservation($pdo, $payment, "cancelled");
            $pdo->commit();
            return ["ok" => false, "message" => "Payment validation did not match this order."];
        }

        if ($payment["ticket_id"] !== null) {
            if ($payment["ticket_availability"] !== "reserved") {
                $pdo->rollBack();
                return ["ok" => false, "message" => "This ticket is no longer reserved for the order."];
            }

            $transferTicket = $pdo->prepare("
                UPDATE tickets
                SET current_owner_id = ?, availability_status = 'sold'
                WHERE ticket_id = ?
                  AND current_owner_id = ?
                  AND availability_status = 'reserved'
            ");
            $transferTicket->execute([
                $payment["buyer_id"],
                $payment["ticket_id"],
                $payment["ticket_owner_id"]
            ]);

            if ($transferTicket->rowCount() !== 1) {
                $pdo->rollBack();
                return ["ok" => false, "message" => "Ticket ownership could not be transferred."];
            }
        }

        $updatePayment = $pdo->prepare("
            UPDATE payments
            SET payment_method = 'sslcommerz', payment_status = 'paid',
                validation_status = 'validated', card_type = ?,
                gateway_response = ?, paid_at = NOW()
            WHERE payment_id = ?
        ");
        $updatePayment->execute([
            $validation["card_type"] ?? null,
            json_encode($validation),
            $payment["payment_id"]
        ]);

        $pdo->prepare("UPDATE orders SET order_status = 'completed', completed_at = NOW() WHERE order_id = ?")
            ->execute([$payment["order_id"]]);
        $pdo->prepare("UPDATE reservations SET reservation_status = 'completed' WHERE reservation_id = ?")
            ->execute([$payment["reservation_id"]]);

        if ($payment["ticket_id"] !== null) {
            $pdo->prepare("
                INSERT INTO ticket_ownership_history (ticket_id, from_user_id, to_user_id, order_id)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $payment["ticket_id"],
                $payment["ticket_owner_id"],
                $payment["buyer_id"],
                $payment["order_id"]
            ]);
            $pdo->prepare("UPDATE listings SET listing_status = 'sold_out' WHERE listing_id = ?")
                ->execute([$payment["listing_id"]]);
        }

        $pdo->commit();
        return ["ok" => true, "order_id" => $payment["order_id"], "already_paid" => false];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Could not finalize SSLCOMMERZ payment: " . $e->getMessage());
        return ["ok" => false, "message" => "Could not finalize this payment safely."];
    }
}
