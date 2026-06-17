ALTER TABLE `files`
  ADD COLUMN `extension` VARCHAR(20) NOT NULL DEFAULT '' AFTER `owner_id`;

UPDATE `files`
SET `extension` = LOWER(SUBSTRING_INDEX(`file_name`, '.', -1))
WHERE `file_name` LIKE '%.%';

ALTER TABLE `files`
  ADD INDEX `idx_files_extension` (`extension`);

ALTER TABLE `files`
  ADD INDEX `idx_files_created_at` (`created_at`);

ALTER TABLE `files`
  ADD INDEX `idx_files_updated_at` (`updated_at`);
