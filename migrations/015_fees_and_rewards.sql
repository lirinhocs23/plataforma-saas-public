ALTER TABLE tenant_preferences
  ADD COLUMN service_fee_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER service_fee_percent,
  ADD COLUMN convenience_fee_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER service_fee_enabled,
  ADD COLUMN convenience_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER convenience_fee_enabled,
  ADD COLUMN convenience_fee_pix TINYINT(1) NOT NULL DEFAULT 0 AFTER convenience_fee_percent,
  ADD COLUMN convenience_fee_cash TINYINT(1) NOT NULL DEFAULT 0 AFTER convenience_fee_pix,
  ADD COLUMN convenience_fee_debit TINYINT(1) NOT NULL DEFAULT 0 AFTER convenience_fee_cash,
  ADD COLUMN convenience_fee_credit TINYINT(1) NOT NULL DEFAULT 1 AFTER convenience_fee_debit,
  ADD COLUMN cashback_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER convenience_fee_credit,
  ADD COLUMN cashback_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER cashback_enabled,
  ADD COLUMN cashback_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER cashback_percent,
  ADD COLUMN cashback_validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER cashback_minimum_cents,
  ADD COLUMN loyalty_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER cashback_validity_days,
  ADD COLUMN loyalty_every_orders SMALLINT UNSIGNED NOT NULL DEFAULT 5 AFTER loyalty_enabled,
  ADD COLUMN loyalty_discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER loyalty_every_orders,
  ADD COLUMN loyalty_discount_value INT UNSIGNED NOT NULL DEFAULT 10 AFTER loyalty_discount_type,
  ADD COLUMN loyalty_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER loyalty_discount_value,
  ADD COLUMN loyalty_validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER loyalty_minimum_cents;

ALTER TABLE orders
  ADD COLUMN service_fee_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER delivery_fee_cents,
  ADD COLUMN convenience_fee_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER service_fee_cents;

CREATE TABLE IF NOT EXISTS customer_rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  source_order_id BIGINT UNSIGNED NOT NULL,
  reward_kind ENUM('cashback','loyalty') NOT NULL,
  customer_phone_hash CHAR(64) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  code_encrypted TEXT NOT NULL,
  discount_type ENUM('percent','fixed') NOT NULL,
  discount_value INT UNSIGNED NOT NULL,
  minimum_cents INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  redeemed_order_id BIGINT UNSIGNED NULL,
  redeemed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_reward_code (tenant_id,code_hash),
  UNIQUE KEY uq_customer_reward_source (source_order_id,reward_kind),
  INDEX idx_customer_reward_lookup (tenant_id,customer_phone_hash,expires_at,redeemed_at),
  CONSTRAINT fk_customer_reward_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_reward_source FOREIGN KEY (source_order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_reward_redemption FOREIGN KEY (redeemed_order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
