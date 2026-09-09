ALTER TABLE tenant_preferences
  ADD COLUMN storefront_preset VARCHAR(50) NULL AFTER hero_cta_text;

INSERT INTO tenant_preferences (tenant_id, storefront_preset)
SELECT id,
  CASE business_type
    WHEN 'Alimentação' THEN 'alimentacao-artesanal'
    WHEN 'Moda e Vestuário' THEN 'moda-editorial'
    WHEN 'Beleza' THEN 'beleza-elegante'
    WHEN 'Pet Shop' THEN 'pet-amigavel'
    WHEN 'Casa e Decoração' THEN 'casa-acolhedora'
    WHEN 'Farmácia' THEN 'farmacia-confiavel'
    WHEN 'Esportes' THEN 'esportes-energia'
    WHEN 'Informática' THEN 'informatica-tech'
    ELSE 'comercio-versatil'
  END
FROM tenants
ON DUPLICATE KEY UPDATE storefront_preset=COALESCE(tenant_preferences.storefront_preset,VALUES(storefront_preset));
