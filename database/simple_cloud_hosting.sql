CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `role` ENUM('superadmin', 'admin', 'user', 'guest') NOT NULL DEFAULT 'user',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `files` (
  `file_name` VARCHAR(255) NOT NULL,
  `owner_id` VARCHAR(40) NULL,
  `extension` VARCHAR(20) NOT NULL DEFAULT '',
  `size` BIGINT UNSIGNED NULL,
  `mime` VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`file_name`),
  INDEX `idx_files_owner_id` (`owner_id`),
  INDEX `idx_files_extension` (`extension`),
  INDEX `idx_files_created_at` (`created_at`),
  INDEX `idx_files_updated_at` (`updated_at`),
  CONSTRAINT `fk_files_owner`
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`) VALUES
('superadmin', 'Super Administrator', 'superadmin', 1, '$2y$10$Tj6fEiSj40X0XK01VHodSuHdVonZTEUl0b8jISpZ1LiBbhCffJDYm'),
('admin', 'Administrator', 'admin', 1, '$2y$10$LR8GJSnC0WPhk8q9O/rsReHWklA4RMgfCG3zOTQRIVxyZ.g.PujY6'),
('guest', 'Guest Viewer', 'guest', 1, '$2y$10$XSkfn7wKA.119jWsBWXIUO.TRVMwsfQu9bH6mYOVtHBAisX3ViChq')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `role` = VALUES(`role`),
  `active` = VALUES(`active`),
  `password_hash` = VALUES(`password_hash`);

INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `created_at`, `updated_at`) VALUES
('Jembatan_Charles_di_Praha.jpg', 'superadmin', 'jpg', '2026-05-23 22:27:45', '2026-05-23 22:27:45'),
('Travel_with_the_lenses.jpg', 'superadmin', 'jpg', '2026-05-23 22:27:54', '2026-05-23 22:27:54'),
('pexels-donovan-kelly-110228397-26834515.jpg', 'admin', 'jpg', '2026-05-23 22:51:10', '2026-05-23 22:51:10'),
('pexels-zfxmql-36746834.jpg', 'admin', 'jpg', '2026-05-23 22:51:20', '2026-05-23 22:51:20'),
('pexels-lovely-ruby-2151623858-34072847.jpg', 'admin', 'jpg', '2026-05-23 22:51:29', '2026-05-23 22:51:29'),
('pexels-willianjusten-21207396.jpg', 'admin', 'jpg', '2026-05-23 22:51:42', '2026-05-23 22:51:42'),
('pexels-russell-butcher-2935498-31147719.jpg', 'admin', 'jpg', '2026-05-23 22:51:52', '2026-05-23 22:51:52'),
('pexels-a-r-2157678326-37486134.jpg', 'admin', 'jpg', '2026-05-23 22:52:03', '2026-05-23 22:52:03'),
('pexels-paulie-ivicic-568819821-17135781.jpg', 'admin', 'jpg', '2026-05-24 10:44:56', '2026-05-24 10:44:56')
ON DUPLICATE KEY UPDATE
  `owner_id` = VALUES(`owner_id`),
  `extension` = VALUES(`extension`);
