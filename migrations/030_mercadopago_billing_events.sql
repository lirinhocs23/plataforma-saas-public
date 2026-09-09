CREATE TABLE IF NOT EXISTS billing_provider_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  provider_event_key VARCHAR(190) NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  resource_ref VARCHAR(190) NULL,
  tenant_id BIGINT UNSIGNED NULL,
  request_id VARCHAR(190) NULL,
  payload_hash CHAR(64) NOT NULL,
  status ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  error_code VARCHAR(80) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  UNIQUE KEY uq_billing_provider_event (provider,provider_event_key),
  INDEX idx_billing_provider_event_tenant (tenant_id,received_at),
  CONSTRAINT fk_billing_provider_event_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

