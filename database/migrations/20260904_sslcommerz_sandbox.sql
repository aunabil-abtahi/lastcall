ALTER TABLE orders
    MODIFY order_status ENUM('pending_payment', 'completed', 'failed', 'cancelled')
    NOT NULL DEFAULT 'pending_payment';

ALTER TABLE payments
    MODIFY payment_status ENUM('pending', 'paid', 'failed', 'cancelled')
    NOT NULL DEFAULT 'pending',
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'BDT' AFTER payment_status,
    ADD COLUMN validation_status ENUM('pending', 'validated', 'failed')
        NOT NULL DEFAULT 'pending' AFTER currency,
    ADD COLUMN card_type VARCHAR(100) NULL AFTER transaction_reference,
    ADD COLUMN gateway_response LONGTEXT NULL AFTER card_type,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
