ALTER TABLE tenants
  ADD COLUMN business_segment_code VARCHAR(40) NULL AFTER business_type;

UPDATE tenants SET business_segment_code = CASE business_type
  WHEN 'Alimentação' THEN 'alimentacao' WHEN 'Moda e Vestuário' THEN 'moda'
  WHEN 'Beleza' THEN 'beleza' WHEN 'Pet Shop' THEN 'pet-shop'
  WHEN 'Casa e Decoração' THEN 'casa-decoracao' WHEN 'Farmácia' THEN 'farmacia'
  WHEN 'Esportes' THEN 'esportes' WHEN 'Informática' THEN 'informatica'
  ELSE 'outro' END
WHERE business_segment_code IS NULL;

ALTER TABLE tenants
  MODIFY COLUMN business_segment_code VARCHAR(40) NOT NULL DEFAULT 'outro';

