CREATE TABLE IF NOT EXISTS tenant_billing_accounts (
  tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  plan_code ENUM('monthly','annual') NULL,
  status ENUM('pending','trialing','active','past_due','cancelled','expired') NOT NULL DEFAULT 'pending',
  provider VARCHAR(40) NULL,
  provider_customer_ref VARCHAR(190) NULL,
  provider_subscription_ref VARCHAR(190) NULL,
  current_period_start DATETIME NULL,
  current_period_end DATETIME NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
  last_payment_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_billing_provider_subscription (provider,provider_subscription_ref),
  INDEX idx_tenant_billing_status_period (status,current_period_end),
  CONSTRAINT fk_tenant_billing_account_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
