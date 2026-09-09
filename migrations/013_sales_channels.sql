ALTER TABLE tenant_preferences
  ADD COLUMN delivery_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_scheduled_orders,
  ADD COLUMN pickup_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER delivery_enabled,
  ADD COLUMN delivery_time_min SMALLINT UNSIGNED NOT NULL DEFAULT 35 AFTER pickup_enabled,
  ADD COLUMN delivery_time_max SMALLINT UNSIGNED NOT NULL DEFAULT 50 AFTER delivery_time_min,
  ADD COLUMN pickup_time_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 20 AFTER delivery_time_max,
  ADD COLUMN free_delivery_above_cents INT UNSIGNED NULL AFTER pickup_time_minutes;
