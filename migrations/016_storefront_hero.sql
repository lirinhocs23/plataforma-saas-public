ALTER TABLE tenant_preferences
  ADD COLUMN hero_title VARCHAR(120) NULL AFTER banner_path,
  ADD COLUMN hero_highlight VARCHAR(120) NULL AFTER hero_title,
  ADD COLUMN hero_cta_text VARCHAR(60) NULL AFTER hero_highlight;
