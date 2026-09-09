SET @two_factor_enabled_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='two_factor_enabled'),
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified_at, ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_enabled'
);
PREPARE two_factor_enabled_statement FROM @two_factor_enabled_sql;
EXECUTE two_factor_enabled_statement;
DEALLOCATE PREPARE two_factor_enabled_statement;

CREATE TABLE IF NOT EXISTS login_two_factor_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  challenge_token_hash CHAR(64) NOT NULL UNIQUE,
  code_hash CHAR(64) NOT NULL,
  requested_ip_hash CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_two_factor_user (tenant_id,user_id,consumed_at,expires_at),
  INDEX idx_two_factor_expiry (expires_at),
  CONSTRAINT fk_two_factor_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_two_factor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
