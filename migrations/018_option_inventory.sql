ALTER TABLE option_items
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN sku VARCHAR(80) NULL AFTER name,
    ADD COLUMN stock_quantity INT NULL AFTER price_cents;

UPDATE option_items oi
JOIN option_groups og ON og.id = oi.group_id
SET oi.tenant_id = og.tenant_id
WHERE oi.tenant_id IS NULL;

ALTER TABLE option_items
    MODIFY tenant_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_option_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    ADD UNIQUE KEY uq_option_items_tenant_sku (tenant_id, sku),
    ADD KEY idx_option_items_tenant_stock (tenant_id, active, stock_quantity),
    ADD CONSTRAINT chk_option_items_stock CHECK (stock_quantity IS NULL OR stock_quantity >= 0);

ALTER TABLE order_item_options
    ADD COLUMN option_sku VARCHAR(80) NULL AFTER option_item_name;
