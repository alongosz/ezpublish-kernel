SET default_storage_engine=InnoDB;
-- Set storage engine schema version number
UPDATE ezsite_data SET value='7.5.0' WHERE name='ezpublish-version';

ALTER TABLE eznotification MODIFY COLUMN data TEXT;
