ALTER TABLE tenant_preferences
  ADD COLUMN referral_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notification_low_stock,
  ADD COLUMN referral_discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER referral_enabled,
  ADD COLUMN referral_discount_value INT UNSIGNED NOT NULL DEFAULT 10 AFTER referral_discount_type,
  ADD COLUMN referral_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER referral_discount_value,
  ADD COLUMN referral_validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER referral_minimum_cents;

ALTER TABLE customer_rewards
  MODIFY reward_kind ENUM('cashback','loyalty','referral') NOT NULL;

CREATE TABLE IF NOT EXISTS customer_referrals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  referrer_customer_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_encrypted TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_referral_customer (tenant_id,referrer_customer_id),
  UNIQUE KEY uq_customer_referral_token (tenant_id,token_hash),
  CONSTRAINT fk_customer_referral_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_referral_customer FOREIGN KEY (referrer_customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_attributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  referral_id BIGINT UNSIGNED NOT NULL,
  referred_customer_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  reward_id BIGINT UNSIGNED NULL,
  status ENUM('pending','rewarded','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  rewarded_at DATETIME NULL,
  UNIQUE KEY uq_referral_referred_customer (tenant_id,referred_customer_id),
  UNIQUE KEY uq_referral_order (tenant_id,order_id),
  INDEX idx_referral_attribution_status (tenant_id,status,created_at),
  CONSTRAINT fk_referral_attribution_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_attribution_referral FOREIGN KEY (referral_id) REFERENCES customer_referrals(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_attribution_customer FOREIGN KEY (referred_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_attribution_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_attribution_reward FOREIGN KEY (reward_id) REFERENCES customer_rewards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
