CREATE DATABASE IF NOT EXISTS lastcall;
USE lastcall;


-- ==========================================================
-- Views for Seller Analytics & Reviews Ecosystem (Phase 4)
-- ==========================================================

-- 1. Seller Analytics Summary View
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

-- 2. Unified Listing Reviews View
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
