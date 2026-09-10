CREATE DATABASE IF NOT EXISTS lastcall;
USE lastcall;


-- 1. Locations
CREATE TABLE locations (
    location_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(100) NOT NULL,
    area VARCHAR(100) NOT NULL,
    address_line VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_location_city_area (city, area)
) ENGINE=InnoDB;

-- 2. Users
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer',
    location_id INT UNSIGNED NULL,

    terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
    terms_version VARCHAR(30) NULL,
    terms_accepted_at DATETIME NULL,

    account_status ENUM('active', 'suspended', 'blocked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Seller verification profiles
CREATE TABLE seller_profiles (
    seller_profile_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,

    seller_type ENUM(
        'food_business',
        'event_organizer',
        'individual_ticket_seller'
    ) NOT NULL,

    business_name VARCHAR(150) NULL,
    verification_document VARCHAR(255) NULL,

    verification_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_seller_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_seller_verified_by
        FOREIGN KEY (verified_by) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4. Events
CREATE TABLE events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,

    event_name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    venue_name VARCHAR(150) NOT NULL,
    event_start_at DATETIME NOT NULL,

    event_status ENUM('upcoming', 'cancelled', 'completed') NOT NULL DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_event_organizer
        FOREIGN KEY (organizer_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_event_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT,

    INDEX idx_event_start (event_start_at)
) ENGINE=InnoDB;

-- 5. Main listing table
CREATE TABLE listings (
    listing_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,

    listing_type ENUM('food', 'ticket') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,

    original_price DECIMAL(10,2) NOT NULL,
    pickup_or_event_deadline DATETIME NOT NULL,

    listing_status ENUM(
        'draft',
        'active',
        'sold_out',
        'expired',
        'removed'
    ) NOT NULL DEFAULT 'draft',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_listing_seller
        FOREIGN KEY (seller_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_listing_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON DELETE RESTRICT,

    INDEX idx_listing_search (
        listing_type,
        listing_status,
        pickup_or_event_deadline
    ),

    INDEX idx_listing_seller (seller_id)
) ENGINE=InnoDB;

-- 6. Food-specific listing information
CREATE TABLE food_listing_details (
    listing_id INT UNSIGNED PRIMARY KEY,
    food_category ENUM(
        'meal',
        'bakery',
        'beverage',
        'snack',
        'other'
    ) NOT NULL,

    quantity_total INT UNSIGNED NOT NULL,
    quantity_available INT UNSIGNED NOT NULL,
    pickup_start_at DATETIME NULL,

    CONSTRAINT chk_food_quantity
        CHECK (quantity_available <= quantity_total),

    CONSTRAINT fk_food_listing
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Dynamic discount rules
CREATE TABLE discount_rules (
    discount_rule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,

    threshold_minutes INT UNSIGNED NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_discount_percent
        CHECK (discount_percent BETWEEN 0 AND 100),

    CONSTRAINT fk_discount_listing
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id)
        ON DELETE CASCADE,

    UNIQUE KEY unique_listing_threshold (listing_id, threshold_minutes)
) ENGINE=InnoDB;

-- 8. Individual event tickets
CREATE TABLE tickets (
    ticket_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    current_owner_id INT UNSIGNED NOT NULL,

    ticket_code VARCHAR(100) NOT NULL UNIQUE,
    ticket_type VARCHAR(100) NOT NULL,
    original_price DECIMAL(10,2) NOT NULL,

    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    availability_status ENUM('available', 'reserved', 'sold', 'invalid') NOT NULL DEFAULT 'available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ticket_event
        FOREIGN KEY (event_id) REFERENCES events(event_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_ticket_owner
        FOREIGN KEY (current_owner_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    INDEX idx_ticket_owner (current_owner_id)
) ENGINE=InnoDB;

-- 9. Connects a ticket to its marketplace listing
CREATE TABLE ticket_listings (
    listing_id INT UNSIGNED PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL UNIQUE,

    CONSTRAINT fk_ticket_listing_listing
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_ticket_listing_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 10. Five-minute reservations
CREATE TABLE reservations (
    reservation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NULL,

    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    reserved_price DECIMAL(10,2) NOT NULL,

    reservation_status ENUM('active', 'expired', 'completed', 'cancelled')
        NOT NULL DEFAULT 'active',

    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,

    CONSTRAINT fk_reservation_buyer
        FOREIGN KEY (buyer_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_reservation_listing
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_reservation_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id)
        ON DELETE RESTRICT,

    INDEX idx_reservation_expiry (reservation_status, expires_at)
) ENGINE=InnoDB;

-- 11. Orders
CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT UNSIGNED NOT NULL,
    reservation_id INT UNSIGNED NULL UNIQUE,

    total_amount DECIMAL(10,2) NOT NULL,
    order_status ENUM('pending_payment', 'completed', 'failed', 'cancelled')
        NOT NULL DEFAULT 'pending_payment',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,

    CONSTRAINT fk_order_buyer
        FOREIGN KEY (buyer_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_order_reservation
        FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id)
        ON DELETE SET NULL,

    INDEX idx_order_buyer (buyer_id)
) ENGINE=InnoDB;

-- 12. Order items
CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NULL,

    item_title VARCHAR(200) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_order_item_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_order_item_listing
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_order_item_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 13. Payments
CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL UNIQUE,

    payment_method ENUM('mock', 'sslcommerz') NOT NULL DEFAULT 'mock',
    payment_status ENUM('pending', 'paid', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    currency CHAR(3) NOT NULL DEFAULT 'BDT',
    validation_status ENUM('pending', 'validated', 'failed') NOT NULL DEFAULT 'pending',
    transaction_reference VARCHAR(150) NULL UNIQUE,
    card_type VARCHAR(100) NULL,
    gateway_response LONGTEXT NULL,

    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payment_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. Ticket ownership history
CREATE TABLE ticket_ownership_history (
    ownership_history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,

    from_user_id INT UNSIGNED NULL,
    to_user_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,

    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_history_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_history_from_user
        FOREIGN KEY (from_user_id) REFERENCES users(user_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_history_to_user
        FOREIGN KEY (to_user_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_history_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- 15. Reviews after completed orders
CREATE TABLE reviews (
    review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL UNIQUE,
    buyer_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,

    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_rating
        CHECK (rating BETWEEN 1 AND 5),

    CONSTRAINT fk_review_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_review_buyer
        FOREIGN KEY (buyer_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_review_seller
        FOREIGN KEY (seller_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    INDEX idx_review_seller (seller_id)
) ENGINE=InnoDB;

-- 16. Reports and disputes
CREATE TABLE reports (
    report_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT UNSIGNED NOT NULL,
    reported_user_id INT UNSIGNED NULL,
    listing_id INT UNSIGNED NULL,

    report_reason VARCHAR(255) NOT NULL,
    report_status ENUM('open', 'reviewing', 'resolved', 'dismissed')
        NOT NULL DEFAULT 'open',

    reviewed_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,

    CONSTRAINT fk_report_reporter
        FOREIGN KEY (reporter_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_report_user
        FOREIGN KEY (reported_user_id) REFERENCES users(user_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_report_listing
        FOREIGN KEY (listing_id) REFERENCES listings(listing_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_report_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
        ON DELETE SET NULL,

    INDEX idx_report_status (report_status)
) ENGINE=InnoDB;

-- 17. Followed sellers
CREATE TABLE followed_sellers (
    follower_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (follower_id, seller_id),

    CONSTRAINT fk_follow_follower
        FOREIGN KEY (follower_id) REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_follow_seller
        FOREIGN KEY (seller_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 18. Buyer preferences for simple recommendations
CREATE TABLE user_preferences (
    preference_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,

    preferred_listing_type ENUM('food', 'ticket') NOT NULL,
    preferred_category VARCHAR(100) NULL,
    preferred_city VARCHAR(100) NULL,
    preferred_area VARCHAR(100) NULL,

    CONSTRAINT fk_preference_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================================
-- 19. Views for Seller Analytics & Reviews Ecosystem
-- ==========================================================

-- Seller Analytics Summary View
-- Computes real-time sales KPIs, listing counts, and reputation metrics per seller
DROP VIEW IF EXISTS view_seller_analytics;
CREATE VIEW view_seller_analytics AS
SELECT
    sp.user_id AS seller_id,
    sp.seller_type,
    sp.business_name,
    -- Listing statistics
    COALESCE(list_agg.total_listings, 0) AS total_listings,
    COALESCE(list_agg.active_listings, 0) AS active_listings,
    -- Sales & revenue statistics
    COALESCE(order_agg.total_revenue, 0.00) AS total_revenue,
    COALESCE(order_agg.completed_orders, 0) AS completed_orders,
    COALESCE(order_agg.total_items_sold, 0) AS total_items_sold,
    -- Customer feedback statistics
    COALESCE(review_agg.average_rating, 0.0) AS average_rating,
    COALESCE(review_agg.review_count, 0) AS review_count
FROM seller_profiles sp
LEFT JOIN (
    SELECT
        seller_id,
        COUNT(listing_id) AS total_listings,
        COUNT(CASE WHEN listing_status = 'active' AND pickup_or_event_deadline > NOW() THEN 1 END) AS active_listings
    FROM listings
    GROUP BY seller_id
) list_agg ON list_agg.seller_id = sp.user_id
LEFT JOIN (
    SELECT
        l.seller_id,
        SUM(CASE WHEN o.order_status = 'completed' THEN oi.subtotal ELSE 0 END) AS total_revenue,
        COUNT(DISTINCT CASE WHEN o.order_status = 'completed' THEN o.order_id END) AS completed_orders,
        SUM(CASE WHEN o.order_status = 'completed' THEN oi.quantity ELSE 0 END) AS total_items_sold
    FROM order_items oi
    JOIN listings l ON l.listing_id = oi.listing_id
    JOIN orders o ON o.order_id = oi.order_id
    GROUP BY l.seller_id
) order_agg ON order_agg.seller_id = sp.user_id
LEFT JOIN (
    SELECT
        seller_id,
        ROUND(AVG(rating), 1) AS average_rating,
        COUNT(review_id) AS review_count
    FROM reviews
    GROUP BY seller_id
) review_agg ON review_agg.seller_id = sp.user_id;

-- Unified Listing Reviews View
-- Joins reviews with buyer details, seller profiles, and purchased items for display
DROP VIEW IF EXISTS view_listing_reviews;
CREATE VIEW view_listing_reviews AS
SELECT
    r.review_id,
    r.order_id,
    r.rating,
    r.review_text,
    r.created_at,
    r.seller_id,
    sp.business_name AS seller_business_name,
    r.buyer_id,
    u.full_name AS buyer_name,
    oi.listing_id,
    oi.item_title
FROM reviews r
JOIN users u ON u.user_id = r.buyer_id
JOIN seller_profiles sp ON sp.user_id = r.seller_id
JOIN order_items oi ON oi.order_id = r.order_id;
