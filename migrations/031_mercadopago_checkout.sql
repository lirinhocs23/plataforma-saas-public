ALTER TABLE tenant_billing_accounts
  ADD COLUMN checkout_reference CHAR(64) NULL AFTER provider_subscription_ref,
  ADD COLUMN provider_checkout_url VARCHAR(2048) NULL AFTER checkout_reference,
  ADD COLUMN checkout_started_at DATETIME NULL AFTER provider_checkout_url,
  ADD UNIQUE KEY uq_tenant_billing_checkout_reference (checkout_reference);
