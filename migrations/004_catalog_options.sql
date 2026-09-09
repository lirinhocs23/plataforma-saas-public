CREATE TABLE IF NOT EXISTS order_item_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id BIGINT UNSIGNED NOT NULL,
  option_item_id BIGINT UNSIGNED NULL,
  option_group_name VARCHAR(120) NOT NULL,
  option_item_name VARCHAR(120) NOT NULL,
  unit_price_cents INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  line_total_cents INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_order_option_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_option_item FOREIGN KEY (option_item_id) REFERENCES option_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

