SET @email_verified_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email_verified_at'),
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER email'
);
PREPARE email_verified_statement FROM @email_verified_sql;
EXECUTE email_verified_statement;
DEALLOCATE PREPARE email_verified_statement;

UPDATE users SET email_verified_at=COALESCE(email_verified_at,created_at);

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  email_hash CHAR(64) NOT NULL,
  requested_ip_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_verification_user (tenant_id,user_id,used_at,expires_at),
  INDEX idx_email_verification_expiry (expires_at),
  CONSTRAINT fk_email_verification_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
