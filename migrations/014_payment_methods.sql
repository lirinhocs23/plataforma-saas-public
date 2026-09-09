ALTER TABLE tenant_preferences
  ADD COLUMN payment_pix_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER free_delivery_above_cents,
  ADD COLUMN payment_cash_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_pix_enabled,
  ADD COLUMN payment_debit_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_cash_enabled,
  ADD COLUMN payment_credit_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_debit_enabled,
  ADD COLUMN payment_pix_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_credit_enabled,
  ADD COLUMN payment_cash_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_pix_minimum_cents,
  ADD COLUMN payment_debit_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_cash_minimum_cents,
  ADD COLUMN payment_credit_minimum_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_debit_minimum_cents;
