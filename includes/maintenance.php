<?php

function runMarketplaceMaintenance(PDO $pdo): void {
    try {
        $pdo->beginTransaction();

        $expiredQuery = $pdo->query("
            SELECT reservation_id, listing_id, ticket_id, quantity
            FROM reservations
            WHERE reservation_status = 'active'
              AND expires_at <= NOW()
            FOR UPDATE
        ");

        $expiredReservations = $expiredQuery->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiredReservations as $reservation) {
            if ($reservation["ticket_id"] === null) {
                $restoreFood = $pdo->prepare("
                    UPDATE food_listing_details
                    SET quantity_available = LEAST(
                        quantity_total,
                        quantity_available + ?
                    )
                    WHERE listing_id = ?
                ");

                $restoreFood->execute([
                    $reservation["quantity"],
                    $reservation["listing_id"]
                ]);

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

            $expireReservation = $pdo->prepare("
                UPDATE reservations
                SET reservation_status = 'expired'
                WHERE reservation_id = ?
                  AND reservation_status = 'active'
            ");

            $expireReservation->execute([$reservation["reservation_id"]]);
        }

        $pdo->exec("
            UPDATE listings
            SET listing_status = 'expired'
            WHERE listing_status IN ('active', 'sold_out')
              AND pickup_or_event_deadline <= NOW()
        ");

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
