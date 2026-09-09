ALTER TABLE products
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER category_id,
  ADD INDEX idx_products_visual_order (tenant_id, category_id, sort_order, id);

UPDATE products
SET sort_order = id
WHERE sort_order = 0;
