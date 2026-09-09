ALTER TABLE dining_tables
  ADD COLUMN access_token_encrypted TEXT NULL AFTER access_token_hash,
  ADD UNIQUE KEY uq_dining_table_token (access_token_hash);

ALTER TABLE orders
  MODIFY COLUMN order_type ENUM('delivery','pickup','dine_in') NOT NULL;

