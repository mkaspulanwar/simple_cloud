-- Backup database Cloudify
-- Database: simple_cloud
-- Tanggal: 2026-06-17T14:28:28+08:00

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `file_name` varchar(255) NOT NULL,
  `owner_id` varchar(40) DEFAULT NULL,
  `extension` varchar(20) NOT NULL DEFAULT '',
  `size` bigint(20) unsigned DEFAULT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`file_name`),
  KEY `idx_files_owner_id` (`owner_id`),
  KEY `idx_files_extension` (`extension`),
  KEY `idx_files_created_at` (`created_at`),
  KEY `idx_files_updated_at` (`updated_at`),
  CONSTRAINT `fk_files_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('banner_idul_adha.jpg', 'anwar', 'jpg', '85315', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('bikini_bottom_citizen.jpg', 'anwar', 'jpg', '44007', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('dino.jpg', 'anwar', 'jpg', '120545', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('kitchen_spongebob.jpg', 'anwar', 'jpg', '76151', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('minimalist_hero.jpg', 'anwar', 'jpg', '35480', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('modern_footer.jpg', 'anwar', 'jpg', '20395', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('not_fine.jpg', 'anwar', 'jpg', '40570', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('ottune.jpg', 'anwar', 'jpg', '16391', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('spongebob_with_money.jpg', 'anwar', 'jpg', '53053', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('spongebob.jpg', 'anwar', 'jpg', '99126', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('squidy.jpg', 'anwar', 'jpg', '41209', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('Toke.jpg', 'anwar', 'jpg', '15296', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('uncle.jpg', 'anwar', 'jpg', '117792', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');
INSERT INTO `files` (`file_name`, `owner_id`, `extension`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('work_illustration.jpg', 'anwar', 'jpg', '87700', 'image/jpeg', '2026-06-17 10:24:22', '2026-06-17 10:53:22');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` varchar(40) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('superadmin','admin','user','guest') NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('admin', 'Administrator', 'admin', '1', '$2y$10$LR8GJSnC0WPhk8q9O/rsReHWklA4RMgfCG3zOTQRIVxyZ.g.PujY6', '2026-05-24 11:02:51', NULL);
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('anwar', 'M. KASPUL ANWAR', 'user', '1', '$2y$10$IlIgdpZz/eSEgmYg8slbwePKUYo33g0IFpy28rfPBA1dIfrGLhH/.', '2026-06-17 10:23:36', NULL);
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('guest', 'Guest Viewer', 'guest', '1', '$2y$10$XSkfn7wKA.119jWsBWXIUO.TRVMwsfQu9bH6mYOVtHBAisX3ViChq', '2026-05-24 11:02:51', NULL);
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('superadmin', 'Super Administrator', 'superadmin', '1', '$2y$10$Tj6fEiSj40X0XK01VHodSuHdVonZTEUl0b8jISpZ1LiBbhCffJDYm', '2026-05-24 11:02:51', NULL);
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('thegreatanwar', 'Anwar The Creator', 'user', '1', '$2y$10$aDO3r7FuuJMW0WVtBOf2a.oclIMObb4vOu/T47nx6MGPSBwhNzMha', '2026-05-24 11:15:58', NULL);

SET FOREIGN_KEY_CHECKS=1;
