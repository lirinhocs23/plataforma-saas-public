ALTER TABLE tenants
  ADD COLUMN suspended_at DATETIME NULL AFTER trial_ends_at,
  ADD COLUMN suspension_reason VARCHAR(500) NULL AFTER suspended_at,
  ADD INDEX idx_tenants_suspended (suspended_at);

