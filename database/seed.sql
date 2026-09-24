


-- Locations
INSERT INTO locations (city, area, address_line) VALUES
('Dhaka', 'Dhanmondi', 'Dhanmondi 27, Dhaka'),
('Chittagong', 'GEC Circle', 'GEC Circle, Chittagong'),
('Dhaka', 'Gulshan', 'Gulshan 1 Avenue, Dhaka'),
('Dhaka', 'Banani', 'Banani 11, Dhaka'),
('Dhaka', 'Mirpur', 'Mirpur 10 Circle, Dhaka');

-- Users
-- Demo credentials:
-- admin@lastcall.test : admin12345
-- All other demo accounts (food, events, buyer, ticket) : password
INSERT INTO users (
    full_name, email, phone, password_hash, role,
    location_id, terms_accepted, terms_version, terms_accepted_at
) VALUES
(
    'LastCall Admin', 'admin@lastcall.test', '01700000001',
    '$2y$10$i2lLInC1YXP6KePfJhFw2u0lLOJ1gwoxl.rbj.eAZcJ8DV3YGqjiG', 'admin', 1, 1, 'v1.0', NOW()
),
(
    'Dhaka Food House', 'food@lastcall.test', '01700000002',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 1, 1, 'v1.0', NOW()
),
(
    'Dhaka Live Events', 'events@lastcall.test', '01700000003',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 1, 1, 'v1.0', NOW()
),
(
    'Rahim Ahmed', 'buyer@lastcall.test', '01700000004',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', 1, 1, 'v1.0', NOW()
),
(
    'Karim Hasan', 'ticket@lastcall.test', '01700000005',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 2, 1, 'v1.0', NOW()
),
(
    'Gulshan Bakery', 'bakery@lastcall.test', '01700000006',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 3, 1, 'v1.0', NOW()
),
(
    'Arena Sports', 'sports@lastcall.test', '01700000007',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 5, 1, 'v1.0', NOW()
),
(
    'Tech BD Conferences', 'tech@lastcall.test', '01700000008',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 4, 1, 'v1.0', NOW()
),
(
    'Burger Town', 'burger@lastcall.test', '01700000009',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seller', 4, 1, 'v1.0', NOW()
);

-- Approved seller profiles
INSERT INTO seller_profiles (
    user_id, seller_type, business_name, verification_status, verified_by, verified_at
) VALUES
(2, 'food_business', 'Dhaka Food House', 'approved', 1, NOW()),
(3, 'event_organizer', 'Dhaka Live Events', 'approved', 1, NOW()),
(5, 'individual_ticket_seller', 'Karim Ticket Resale', 'approved', 1, NOW()),
(6, 'food_business', 'Gulshan Artisan Bakery', 'approved', 1, NOW()),
(7, 'event_organizer', 'Arena Sports Complex', 'approved', 1, NOW()),
(8, 'event_organizer', 'Tech BD Conferences', 'approved', 1, NOW()),
(9, 'food_business', 'Burger Town', 'approved', 1, NOW());

-- Events
INSERT INTO events (
    organizer_id, location_id, event_name, description, venue_name, event_start_at, event_status
) VALUES
(3, 1, 'Dhaka Music Night', 'An evening concert featuring local artists.', 'Army Stadium', DATE_ADD(NOW(), INTERVAL 1 DAY), 'upcoming'),
(3, 3, 'Comedy Gala 2026', 'Stand-up comedy by top comedians.', 'Gulshan Club', DATE_ADD(NOW(), INTERVAL 3 DAY), 'upcoming'),
(7, 5, 'Football Championship Cup', 'Local football club tournament finals.', 'Mirpur Stadium', DATE_ADD(NOW(), INTERVAL 2 DAY), 'upcoming'),
(8, 4, 'Tech Summit Dhaka', 'Annual developer and tech summit.', 'Banani Convention Center', DATE_ADD(NOW(), INTERVAL 5 DAY), 'upcoming');

-- Listings
INSERT INTO listings (
    seller_id, location_id, listing_type, title, description, image_url,
    original_price, pickup_or_event_deadline, listing_status
) VALUES
-- 1. Food
(2, 1, 'food', 'Chicken Biryani Meal Box', 'Fresh surplus chicken biryani meal box. Pickup only.', 'assets/uploads/listings/biryani_box.jpg', 300.00, DATE_ADD(NOW(), INTERVAL 3 HOUR), 'active'),
-- 2. Ticket
(5, 1, 'ticket', 'Dhaka Music Night Ticket', 'Verified general-admission ticket for resale.', 'assets/uploads/listings/concert_ticket.jpg', 900.00, DATE_ADD(NOW(), INTERVAL 20 HOUR), 'active'),
-- 3. Food
(6, 3, 'food', 'Assorted Sourdough Box', 'End of day surplus sourdough bread and baguettes.', 'assets/uploads/listings/sourdough_box.jpg', 500.00, DATE_ADD(NOW(), INTERVAL 2 HOUR), 'active'),
-- 4. Food
(6, 3, 'food', 'Eclairs & Macarons Set', 'Perfectly fine pastries from today''s batch.', 'assets/uploads/listings/eclair_macaron.jpg', 800.00, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'active'),
-- 5. Ticket
(7, 5, 'ticket', 'VIP Football Cup Pass', 'Front row seating for the championship finals.', 'assets/uploads/listings/football_cup.jpg', 1500.00, DATE_ADD(NOW(), INTERVAL 45 HOUR), 'active'),
-- 6. Ticket
(8, 4, 'ticket', 'Tech Summit Entry', 'Full access pass to all tech talks.', 'assets/uploads/listings/tech_summit.jpg', 2000.00, DATE_ADD(NOW(), INTERVAL 4 DAY), 'active'),
-- 7. Food
(9, 4, 'food', 'BBQ Chicken Value Meal', 'Leftover BBQ chicken set with sides.', 'assets/uploads/listings/bbq_chicken.jpg', 450.00, DATE_ADD(NOW(), INTERVAL 5 HOUR), 'active'),
-- 8. Food
(2, 1, 'food', 'Fresh Juice Trio', '3 bottles of fresh pressed juice.', 'assets/uploads/listings/juice_trio.jpg', 250.00, DATE_ADD(NOW(), INTERVAL 2 HOUR), 'active');

-- Food listing details
INSERT INTO food_listing_details (listing_id, food_category, quantity_total, quantity_available, pickup_start_at) VALUES
(1, 'meal', 15, 15, DATE_ADD(NOW(), INTERVAL 30 MINUTE)),
(3, 'bakery', 10, 10, DATE_ADD(NOW(), INTERVAL 15 MINUTE)),
(4, 'bakery', 5, 5, DATE_ADD(NOW(), INTERVAL 30 MINUTE)),
(7, 'meal', 8, 8, DATE_ADD(NOW(), INTERVAL 45 MINUTE)),
(8, 'beverage', 20, 20, DATE_ADD(NOW(), INTERVAL 10 MINUTE));

-- Food discount rules
INSERT INTO discount_rules (listing_id, threshold_minutes, discount_percent) VALUES
(1, 120, 10.00), (1, 60, 25.00), (1, 30, 40.00),
(3, 60, 30.00), (3, 30, 50.00),
(4, 120, 20.00), (4, 60, 40.00),
(7, 180, 15.00), (7, 60, 30.00),
(8, 60, 50.00);

-- Tickets
INSERT INTO tickets (event_id, current_owner_id, ticket_code, ticket_type, original_price, verification_status, availability_status) VALUES
(1, 5, 'DMS-2026-001', 'General Admission', 1000.00, 'verified', 'available'),
(3, 7, 'FBC-2026-VIP', 'VIP Seating', 1500.00, 'verified', 'available'),
(4, 8, 'TECH-2026-GEN', 'General Entry', 2000.00, 'verified', 'available');

-- Ticket listings
INSERT INTO ticket_listings (listing_id, ticket_id) VALUES
(2, 1),
(5, 2),
(6, 3);

-- Follow and preference examples
INSERT INTO followed_sellers (follower_id, seller_id) VALUES 
(4, 2), (4, 6), (4, 8);

INSERT INTO user_preferences (user_id, preferred_listing_type, preferred_category, preferred_city, preferred_area) VALUES
(4, 'food', 'meal', 'Dhaka', 'Dhanmondi');
