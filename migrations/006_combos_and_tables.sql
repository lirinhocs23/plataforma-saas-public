CREATE TABLE IF NOT EXISTS order_combo_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  combo_id BIGINT UNSIGNED NULL,
  combo_name VARCHAR(160) NOT NULL,
  unit_price_cents INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  line_total_cents INT UNSIGNED NOT NULL,
  CONSTRAINT fk_order_combo_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_combo_combo FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS table_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  table_id BIGINT UNSIGNED NOT NULL,
  opened_by BIGINT UNSIGNED NULL,
  closed_by BIGINT UNSIGNED NULL,
  customer_name VARCHAR(160) NULL,
  status ENUM('open','awaiting_payment','closed','cancelled') NOT NULL DEFAULT 'open',
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  INDEX idx_table_session_tenant (tenant_id,status,opened_at),
  CONSTRAINT fk_table_session_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_table_session_table FOREIGN KEY (table_id) REFERENCES dining_tables(id) ON DELETE RESTRICT,
  CONSTRAINT fk_table_session_opened FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_table_session_closed FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS table_session_orders (
  session_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (session_id,order_id),
  UNIQUE KEY uq_table_order (order_id),
  CONSTRAINT fk_table_order_session FOREIGN KEY (session_id) REFERENCES table_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_table_order_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

