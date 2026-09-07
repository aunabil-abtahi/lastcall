USE lastcall;

-- Locations
INSERT INTO locations (city, area, address_line) VALUES
('Dhaka', 'Dhanmondi', 'Dhanmondi 27, Dhaka'),
('Chittagong', 'GEC Circle', 'GEC Circle, Chittagong');

-- Users
-- Demo credentials:
-- admin@lastcall.test : admin12345
-- All other demo accounts (food, events, buyer, ticket) : password
INSERT INTO users (
    full_name, email, phone, password_hash, role,
    location_id, terms_accepted, terms_version, terms_accepted_at
) VALUES
(
    'LastCall Admin',
    'admin@lastcall.test',
    '01700000001',
    '$2y$10$81RrDyfa9P6YXqtqIT1UHumI26WjAptIFsiX9Lqcv0BFRXObEc0o2',
    'admin',
    1,
    1,
    'v1.0',
    NOW()
),
(
    'Dhaka Food House',
    'food@lastcall.test',
    '01700000002',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'seller',
    1,
    1,
    'v1.0',
    NOW()
),
(
    'Dhaka Live Events',
    'events@lastcall.test',
    '01700000003',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'seller',
    1,
    1,
    'v1.0',
    NOW()
),
(
    'Rahim Ahmed',
    'buyer@lastcall.test',
    '01700000004',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'buyer',
    1,
    1,
    'v1.0',
    NOW()
),
(
    'Karim Hasan',
    'ticket@lastcall.test',
    '01700000005',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'seller',
    2,
    1,
    'v1.0',
    NOW()
);

-- Approved seller profiles
INSERT INTO seller_profiles (
    user_id, seller_type, business_name,
    verification_status, verified_by, verified_at
) VALUES
(2, 'food_business', 'Dhaka Food House', 'approved', 1, NOW()),
(3, 'event_organizer', 'Dhaka Live Events', 'approved', 1, NOW()),
(5, 'individual_ticket_seller', 'Karim Ticket Resale', 'approved', 1, NOW());

-- Event
INSERT INTO events (
    organizer_id, location_id, event_name, description,
    venue_name, event_start_at, event_status
) VALUES
(
    3,
    1,
    'Dhaka Music Night',
    'An evening concert featuring local artists.',
    'Army Stadium',
    DATE_ADD(NOW(), INTERVAL 1 DAY),
    'upcoming'
);

-- Food listing
INSERT INTO listings (
    seller_id, location_id, listing_type, title, description,
    original_price, pickup_or_event_deadline, listing_status
) VALUES
(
    2,
    1,
    'food',
    'Chicken Biryani Meal Box',
    'Fresh surplus chicken biryani meal box. Pickup only.',
    300.00,
    DATE_ADD(NOW(), INTERVAL 3 HOUR),
    'active'
);

INSERT INTO food_listing_details (
    listing_id, food_category, quantity_total,
    quantity_available, pickup_start_at
) VALUES
(1, 'meal', 15, 15, DATE_ADD(NOW(), INTERVAL 30 MINUTE));

-- Food discount rules
INSERT INTO discount_rules (
    listing_id, threshold_minutes, discount_percent
) VALUES
(1, 120, 10.00),
(1, 60, 25.00),
(1, 30, 40.00);

-- Ticket owned by Karim
INSERT INTO tickets (
    event_id, current_owner_id, ticket_code, ticket_type,
    original_price, verification_status, availability_status
) VALUES
(
    1,
    5,
    'DMS-2026-001',
    'General Admission',
    1000.00,
    'verified',
    'available'
);

-- Ticket resale listing
INSERT INTO listings (
    seller_id, location_id, listing_type, title, description,
    original_price, pickup_or_event_deadline, listing_status
) VALUES
(
    5,
    1,
    'ticket',
    'Dhaka Music Night Ticket',
    'Verified general-admission ticket for resale.',
    900.00,
    DATE_ADD(NOW(), INTERVAL 20 HOUR),
    'active'
);

INSERT INTO ticket_listings (listing_id, ticket_id)
VALUES (2, 1);

-- Follow and preference examples
INSERT INTO followed_sellers (follower_id, seller_id)
VALUES (4, 2);

INSERT INTO user_preferences (
    user_id, preferred_listing_type,
    preferred_category, preferred_city, preferred_area
) VALUES
(4, 'food', 'meal', 'Dhaka', 'Dhanmondi');