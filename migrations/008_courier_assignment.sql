ALTER TABLE orders
  ADD COLUMN assigned_courier_id BIGINT UNSIGNED NULL AFTER status,
  ADD INDEX idx_orders_courier (tenant_id,assigned_courier_id,status,created_at),
  ADD CONSTRAINT fk_orders_courier FOREIGN KEY (assigned_courier_id) REFERENCES users(id) ON DELETE SET NULL;

