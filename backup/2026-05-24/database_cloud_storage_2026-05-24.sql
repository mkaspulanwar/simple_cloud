-- Backup database Cloudify
-- Database: simple_cloud
-- Tanggal: 2026-05-24T23:42:42+08:00

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `file_name` varchar(255) NOT NULL,
  `owner_id` varchar(40) DEFAULT NULL,
  `size` bigint(20) unsigned DEFAULT NULL,
  `mime` varchar(120) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`file_name`),
  KEY `idx_files_owner_id` (`owner_id`),
  CONSTRAINT `fk_files_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('Jembatan_Charles_di_Praha.jpg', 'superadmin', NULL, NULL, '2026-05-23 22:27:45', '2026-05-23 22:27:45');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-a-r-2157678326-37486134.jpg', 'admin', NULL, NULL, '2026-05-23 22:52:03', '2026-05-23 22:52:03');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-ayala-9140-50868.jpg', 'thegreatanwar', '974060', 'image/jpeg', '2026-05-24 16:28:03', NULL);
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-donovan-kelly-110228397-26834515.jpg', 'admin', NULL, NULL, '2026-05-23 22:51:10', '2026-05-23 22:51:10');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-fatma-nur-yildirim-kuzlak-2156450046-34213954.jpg', 'thegreatanwar', '1741035', 'image/jpeg', '2026-05-24 22:51:33', NULL);
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-lovely-ruby-2151623858-34072847.jpg', 'admin', NULL, NULL, '2026-05-23 22:51:29', '2026-05-23 22:51:29');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-oscar-m-344441239-19794203.jpg', 'thegreatanwar', '3119402', 'image/jpeg', '2026-05-24 16:28:03', NULL);
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-paulie-ivicic-568819821-17135781.jpg', 'thegreatanwar', '1605605', 'image/jpeg', '2026-05-24 16:28:03', NULL);
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-russell-butcher-2935498-31147719.jpg', 'admin', NULL, NULL, '2026-05-23 22:51:52', '2026-05-23 22:51:52');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-willianjusten-21207396.jpg', 'admin', NULL, NULL, '2026-05-23 22:51:42', '2026-05-23 22:51:42');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('pexels-zfxmql-36746834.jpg', 'admin', NULL, NULL, '2026-05-23 22:51:20', '2026-05-23 22:51:20');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('Sydney_City_Bridge.jpg', 'admin', NULL, NULL, '2026-05-24 10:44:56', '2026-05-24 11:04:52');
INSERT INTO `files` (`file_name`, `owner_id`, `size`, `mime`, `created_at`, `updated_at`) VALUES ('Travel_with_the_lenses.jpg', 'superadmin', NULL, NULL, '2026-05-23 22:27:54', '2026-05-23 22:27:54');

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
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('guest', 'Guest Viewer', 'guest', '1', '$2y$10$XSkfn7wKA.119jWsBWXIUO.TRVMwsfQu9bH6mYOVtHBAisX3ViChq', '2026-05-24 11:02:51', NULL);
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('superadmin', 'Super Administrator', 'superadmin', '1', '$2y$10$Tj6fEiSj40X0XK01VHodSuHdVonZTEUl0b8jISpZ1LiBbhCffJDYm', '2026-05-24 11:02:51', NULL);
INSERT INTO `users` (`id`, `name`, `role`, `active`, `password_hash`, `created_at`, `updated_at`) VALUES ('thegreatanwar', 'Anwar The Creator', 'user', '1', '$2y$10$aDO3r7FuuJMW0WVtBOf2a.oclIMObb4vOu/T47nx6MGPSBwhNzMha', '2026-05-24 11:15:58', NULL);

SET FOREIGN_KEY_CHECKS=1;
