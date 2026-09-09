ALTER TABLE tenant_preferences
  ADD COLUMN social_links_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER storefront_preset,
  ADD COLUMN instagram_url VARCHAR(500) NULL AFTER social_links_enabled,
  ADD COLUMN facebook_url VARCHAR(500) NULL AFTER instagram_url,
  ADD COLUMN tiktok_url VARCHAR(500) NULL AFTER facebook_url,
  ADD COLUMN youtube_url VARCHAR(500) NULL AFTER tiktok_url;

CREATE TABLE IF NOT EXISTS tenant_quick_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL,
  url VARCHAR(2048) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#F26B21',
  emoji VARCHAR(16) NOT NULL DEFAULT '🔗',
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenant_quick_links_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  KEY idx_tenant_quick_links_order (tenant_id, active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
