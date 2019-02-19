-- Set storage engine schema version number
UPDATE ezsite_data SET value='7.5.0' WHERE name='ezpublish-version';

ALTER TABLE eznotification ALTER COLUMN is_pending TYPE BOOLEAN;
ALTER TABLE eznotification ALTER COLUMN is_pending SET DEFAULT true;
