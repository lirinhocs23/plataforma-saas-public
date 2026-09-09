ALTER TABLE option_groups
    ADD COLUMN pricing_strategy ENUM('sum','highest') NOT NULL DEFAULT 'sum' AFTER max_choices;


