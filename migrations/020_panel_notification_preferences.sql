ALTER TABLE tenant_preferences
  ADD COLUMN notification_new_order TINYINT(1) NOT NULL DEFAULT 1 AFTER youtube_url,
  ADD COLUMN notification_cancelled_order TINYINT(1) NOT NULL DEFAULT 1 AFTER notification_new_order,
  ADD COLUMN notification_low_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER notification_cancelled_order;
