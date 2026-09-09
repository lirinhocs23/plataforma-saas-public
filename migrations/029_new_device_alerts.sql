SET @new_device_alerts_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='new_device_alerts_enabled'),
  'SELECT 1',
  'ALTER TABLE users ADD COLUMN new_device_alerts_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER two_factor_enabled_at'
);
PREPARE new_device_alerts_statement FROM @new_device_alerts_sql;
EXECUTE new_device_alerts_statement;
DEALLOCATE PREPARE new_device_alerts_statement;
