ALTER TABLE orders
  ADD COLUMN tracking_expires_at DATETIME NULL AFTER tracking_token_hash,
  ADD COLUMN tracking_revoked_at DATETIME NULL AFTER tracking_expires_at,
  ADD INDEX idx_orders_tracking_expiry (tracking_expires_at, tracking_revoked_at);

UPDATE orders
SET tracking_expires_at = DATE_ADD(created_at, INTERVAL 30 DAY)
WHERE tracking_expires_at IS NULL;

ALTER TABLE orders
  MODIFY tracking_expires_at DATETIME NOT NULL;
