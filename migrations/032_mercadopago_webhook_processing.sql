ALTER TABLE billing_provider_events
  MODIFY COLUMN status ENUM('received','processing','processed','ignored','failed') NOT NULL DEFAULT 'received',
  ADD COLUMN attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER error_code,
  ADD COLUMN last_attempt_at DATETIME NULL AFTER attempts;
