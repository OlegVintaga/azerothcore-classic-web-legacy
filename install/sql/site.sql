-- ============================================================
-- SITE (wow.ru web-wrapper) — веб-схема для AzerothCore 3.3.5
-- Имена таблиц — те, что реально читает/пишет код этой сборки
-- (website_* + наследие: voting*, web_*, world_entries, ...).
-- MySQL 8: InnoDB, utf8mb4, без zero-dates.
-- Игровые базы (acore_auth / acore_characters / acore_world)
-- не изменяются — на них только VIEW (realmlist, uptime).
-- Плейсхолдер {{AUTH_DB}} подставляет установщик.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- website_accounts — метаданные аккаунта на сайте
-- ----------------------------
DROP TABLE IF EXISTS `website_accounts`;
CREATE TABLE `website_accounts` (
  `account_id` int unsigned NOT NULL,
  `g_id` smallint unsigned NOT NULL DEFAULT '2',
  `character_id` int unsigned DEFAULT NULL,
  `character_name` varchar(12) DEFAULT NULL,
  `display_name` varchar(25) NOT NULL DEFAULT '',
  `avatar` varchar(60) DEFAULT NULL,
  `gender` tinyint NOT NULL DEFAULT '0',
  `homepage` varchar(100) DEFAULT NULL,
  `icq` varchar(12) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `signature` text,
  `hideemail` tinyint(1) NOT NULL DEFAULT '1',
  `hideprofile` tinyint(1) NOT NULL DEFAULT '0',
  `theme` smallint unsigned NOT NULL DEFAULT '0',
  `forum_posts` int unsigned NOT NULL DEFAULT '0',
  `registration_ip` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `activation_code` varchar(40) DEFAULT NULL,
  `msn` varchar(255) DEFAULT NULL,
  `secretq1` varchar(300) NOT NULL DEFAULT '0',
  `secreta1` varchar(300) NOT NULL DEFAULT '0',
  `secretq2` varchar(300) NOT NULL DEFAULT '0',
  `secreta2` varchar(300) NOT NULL DEFAULT '0',
  `vip` int NOT NULL DEFAULT '0',
  `donator` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Наследие: account_extend (админ-компоненты старой школы)
-- ----------------------------
DROP TABLE IF EXISTS `account_extend`;
CREATE TABLE `account_extend` (
  `account_id` int unsigned NOT NULL,
  `character_id` int unsigned DEFAULT NULL,
  `character_name` varchar(12) DEFAULT NULL,
  `g_id` smallint unsigned NOT NULL DEFAULT '2',
  `avatar` varchar(60) DEFAULT NULL,
  `gender` tinyint NOT NULL DEFAULT '0',
  `homepage` varchar(100) DEFAULT NULL,
  `icq` varchar(12) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `signature` text,
  `hideemail` tinyint(1) NOT NULL DEFAULT '1',
  `hideprofile` tinyint(1) NOT NULL DEFAULT '0',
  `theme` smallint unsigned NOT NULL DEFAULT '0',
  `forum_posts` int unsigned NOT NULL DEFAULT '0',
  `registration_ip` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `activation_code` varchar(40) DEFAULT NULL,
  `msn` varchar(255) DEFAULT NULL,
  `secretq1` varchar(300) NOT NULL DEFAULT '0',
  `secreta1` varchar(300) NOT NULL DEFAULT '0',
  `secretq2` varchar(300) NOT NULL DEFAULT '0',
  `secreta2` varchar(300) NOT NULL DEFAULT '0',
  `vip` int NOT NULL DEFAULT '0',
  `donator` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- website_account_groups — группы и права
-- ----------------------------
DROP TABLE IF EXISTS `website_account_groups`;
CREATE TABLE `website_account_groups` (
  `g_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `g_title` varchar(32) NOT NULL,
  `g_prefix` char(6) DEFAULT NULL,
  `g_is_admin` tinyint(1) DEFAULT '0',
  `g_is_supadmin` tinyint(1) DEFAULT '0',
  `g_use_search` tinyint(1) DEFAULT '0',
  `g_view_profile` tinyint(1) DEFAULT '0',
  `g_post_new_topics` tinyint(1) DEFAULT '0',
  `g_reply_other_topics` tinyint(1) DEFAULT '0',
  `g_use_attach` tinyint(1) DEFAULT '0',
  `g_edit_own_posts` tinyint(1) DEFAULT '0',
  `g_delete_own_posts` tinyint(1) DEFAULT '0',
  `g_delete_own_topics` tinyint(1) DEFAULT '0',
  `g_forum_moderate` tinyint(1) NOT NULL DEFAULT '0',
  `g_use_pm` tinyint(1) DEFAULT '0',
  `g_gal_view` tinyint(1) NOT NULL DEFAULT '0',
  `g_gal_upload` tinyint(1) DEFAULT '0',
  `g_gal_download` tinyint(1) DEFAULT '0',
  `g_gal_moderate` tinyint(1) DEFAULT '0',
  `g_gal_balanceon` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`g_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `website_account_groups` VALUES
(1, 'Guests', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0),
(2, 'Members', NULL, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, 0, 1),
(3, 'Administrators', '+', 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(4, 'Root Admins', '$', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(5, 'Banned', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0),
(6, 'Moderators', NULL, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 1, 1, 0);

DROP TABLE IF EXISTS `account_groups`;
CREATE TABLE `account_groups` (
  `g_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `g_title` varchar(32) NOT NULL,
  `g_prefix` char(6) DEFAULT NULL,
  `g_is_admin` tinyint(1) DEFAULT '0',
  `g_is_supadmin` tinyint(1) DEFAULT '0',
  `g_use_search` tinyint(1) DEFAULT '0',
  `g_view_profile` tinyint(1) DEFAULT '0',
  `g_post_new_topics` tinyint(1) DEFAULT '0',
  `g_reply_other_topics` tinyint(1) DEFAULT '0',
  `g_use_attach` tinyint(1) DEFAULT '0',
  `g_edit_own_posts` tinyint(1) DEFAULT '0',
  `g_delete_own_posts` tinyint(1) DEFAULT '0',
  `g_delete_own_topics` tinyint(1) DEFAULT '0',
  `g_forum_moderate` tinyint(1) NOT NULL DEFAULT '0',
  `g_use_pm` tinyint(1) DEFAULT '0',
  `g_gal_view` tinyint(1) NOT NULL DEFAULT '0',
  `g_gal_upload` tinyint(1) DEFAULT '0',
  `g_gal_download` tinyint(1) DEFAULT '0',
  `g_gal_moderate` tinyint(1) DEFAULT '0',
  `g_gal_balanceon` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`g_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `account_groups` VALUES
(1, 'Guests', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0),
(2, 'Members', NULL, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 1, 0, 1),
(3, 'Administrators', '+', 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(4, 'Root Admins', '$', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0),
(5, 'Banned', NULL, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);

-- ----------------------------
-- website_account_keys — ключи куки-входа
-- ----------------------------
DROP TABLE IF EXISTS `website_account_keys`;
CREATE TABLE `website_account_keys` (
  `id` int unsigned NOT NULL,
  `key` varchar(40) DEFAULT NULL,
  `assign_time` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- website_online / online — кто на сайте
-- ----------------------------
DROP TABLE IF EXISTS `website_online`;
CREATE TABLE `website_online` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `user_name` varchar(200) NOT NULL DEFAULT 'Guest',
  `user_ip` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `logged` int NOT NULL DEFAULT '0',
  `currenturl` varchar(255) NOT NULL DEFAULT './',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `online`;
CREATE TABLE `online` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `user_name` varchar(200) NOT NULL DEFAULT 'Guest',
  `user_ip` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `logged` int NOT NULL DEFAULT '0',
  `currenturl` varchar(255) NOT NULL DEFAULT './',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- website_pms / pms — личные сообщения
-- ----------------------------
DROP TABLE IF EXISTS `website_pms`;
CREATE TABLE `website_pms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int unsigned NOT NULL DEFAULT '0',
  `subject` varchar(255) NOT NULL,
  `message` text,
  `sender_id` int unsigned NOT NULL DEFAULT '0',
  `posted` int unsigned NOT NULL DEFAULT '0',
  `sender_ip` varchar(15) DEFAULT '0.0.0.0',
  `showed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pms`;
CREATE TABLE `pms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int unsigned NOT NULL DEFAULT '0',
  `subject` varchar(255) NOT NULL,
  `message` text,
  `sender_id` int unsigned NOT NULL DEFAULT '0',
  `posted` int unsigned NOT NULL DEFAULT '0',
  `sender_ip` varchar(15) DEFAULT '0.0.0.0',
  `showed` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Ключи регистрации
-- ----------------------------
DROP TABLE IF EXISTS `website_regkeys`;
CREATE TABLE `website_regkeys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(40) NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `site_regkeys`;
CREATE TABLE `site_regkeys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(40) NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- website_captcha — капча регистрации
-- ----------------------------
DROP TABLE IF EXISTS `website_captcha`;
CREATE TABLE `website_captcha` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(200) NOT NULL DEFAULT '',
  `filekey` varchar(200) NOT NULL DEFAULT '',
  `key` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `acc_creation_captcha`;
CREATE TABLE `acc_creation_captcha` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(200) NOT NULL DEFAULT '',
  `filekey` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Галерея
-- ----------------------------
DROP TABLE IF EXISTS `website_gallery`;
CREATE TABLE `website_gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `img` text,
  `comment` text,
  `autor` text,
  `date` date DEFAULT NULL,
  `cat` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `website_gallery_ssotd`;
CREATE TABLE `website_gallery_ssotd` (
  `image` varchar(50) NOT NULL,
  `date` varchar(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gallery`;
CREATE TABLE `gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `img` text,
  `comment` text,
  `autor` text,
  `date` date DEFAULT NULL,
  `cat` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gallery_ssotd`;
CREATE TABLE `gallery_ssotd` (
  `image` varchar(50) NOT NULL,
  `date` varchar(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Настройки реальмов (доступ к world/characters)
-- ----------------------------
DROP TABLE IF EXISTS `website_realm_settings`;
CREATE TABLE `website_realm_settings` (
  `id_realm` int unsigned NOT NULL,
  `dbuser` varchar(25) NOT NULL,
  `dbpass` varchar(25) NOT NULL,
  `dbhost` varchar(25) NOT NULL,
  `dbport` varchar(5) NOT NULL,
  `dbname` varchar(25) NOT NULL,
  `chardbname` varchar(25) NOT NULL,
  `template` varchar(50) DEFAULT NULL,
  `ra_address` varchar(25) NOT NULL DEFAULT '127.0.0.1',
  `ra_port` int DEFAULT '3443',
  `ra_user` varchar(255) NOT NULL DEFAULT '',
  `ra_pass` varchar(255) NOT NULL DEFAULT '',
  `uptime` datetime DEFAULT NULL,
  PRIMARY KEY (`id_realm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `realm_settings`;
CREATE TABLE `realm_settings` (
  `id_realm` int unsigned NOT NULL,
  `dbuser` varchar(25) NOT NULL,
  `dbpass` varchar(25) NOT NULL,
  `dbhost` varchar(25) NOT NULL,
  `dbport` varchar(5) NOT NULL,
  `dbname` varchar(25) NOT NULL,
  `chardbname` varchar(25) NOT NULL,
  `template` varchar(50) DEFAULT NULL,
  `ra_address` varchar(25) NOT NULL DEFAULT '127.0.0.1',
  `ra_port` int DEFAULT '3443',
  `ra_user` varchar(255) NOT NULL DEFAULT '',
  `ra_pass` varchar(255) NOT NULL DEFAULT '',
  `uptime` datetime DEFAULT NULL,
  PRIMARY KEY (`id_realm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- AJAX-чат (компонент chat) — из chat.sql, под MySQL 8
-- ----------------------------
DROP TABLE IF EXISTS `ajax_chat_online`;
CREATE TABLE `ajax_chat_online` (
  `userID` int NOT NULL,
  `userName` varchar(64) NOT NULL,
  `userRole` tinyint NOT NULL,
  `channel` int NOT NULL,
  `dateTime` datetime NOT NULL,
  `ip` varbinary(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `ajax_chat_messages`;
CREATE TABLE `ajax_chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userID` int NOT NULL,
  `userName` varchar(64) NOT NULL,
  `userRole` tinyint NOT NULL,
  `channel` int NOT NULL,
  `dateTime` datetime NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `text` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `ajax_chat_bans`;
CREATE TABLE `ajax_chat_bans` (
  `userID` int NOT NULL,
  `userName` varchar(64) NOT NULL,
  `dateTime` datetime NOT NULL,
  `ip` varbinary(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `ajax_chat_invitations`;
CREATE TABLE `ajax_chat_invitations` (
  `userID` int NOT NULL,
  `channel` int NOT NULL,
  `dateTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- ----------------------------
-- Голосовалки
-- ----------------------------
DROP TABLE IF EXISTS `voting`;
CREATE TABLE `voting` (
  `user_ip` varchar(30) NOT NULL,
  `sites` int unsigned NOT NULL DEFAULT '0',
  `time` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voting_points`;
CREATE TABLE `voting_points` (
  `id` int unsigned NOT NULL,
  `points` smallint unsigned NOT NULL DEFAULT '0',
  `date` varchar(8) NOT NULL DEFAULT '',
  `date_points` tinyint unsigned NOT NULL DEFAULT '0',
  `times_voted` smallint NOT NULL DEFAULT '0',
  `points_earned` bigint NOT NULL DEFAULT '0',
  `points_spent` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voting_rewards`;
CREATE TABLE `voting_rewards` (
  `id` smallint NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `quanity` int NOT NULL,
  `cost` int NOT NULL,
  `quality` int NOT NULL,
  `reward_text` varchar(255) NOT NULL,
  `realmid` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voting_sites`;
CREATE TABLE `voting_sites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(255) NOT NULL,
  `votelink` varchar(255) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `points` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Прочее веб
-- ----------------------------
DROP TABLE IF EXISTS `world_entries`;
CREATE TABLE `world_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `db_name` varchar(255) NOT NULL,
  `last_inc` int NOT NULL,
  `last_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `world_entries` VALUES
(1, 'character', 0, 0),
(2, 'item_instance', 0, 0),
(3, 'mail', 0, 0);

DROP TABLE IF EXISTS `web_misc`;
CREATE TABLE `web_misc` (
  `id_misc` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) DEFAULT NULL,
  `text` varchar(200) DEFAULT NULL,
  `urls` text,
  `image` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id_misc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `web_donations`;
CREATE TABLE `web_donations` (
  `id_donation` int unsigned NOT NULL AUTO_INCREMENT,
  `id_account` int unsigned NOT NULL,
  `value` varchar(45) NOT NULL DEFAULT '0',
  `date` date DEFAULT NULL,
  `hide` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_donation`,`id_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `donations_template`;
CREATE TABLE `donations_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donation` varchar(255) NOT NULL,
  `items` varchar(4000) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `description` varchar(255) NOT NULL,
  `itemset` varchar(1000) NOT NULL,
  `realm` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `account_pass`;
CREATE TABLE `account_pass` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='чистые пароли при use_purepass_table (включено осознанно)';

-- ----------------------------
-- PayPal (наследие donate-модуля)
-- ----------------------------
DROP TABLE IF EXISTS `paypal_cart_info`;
CREATE TABLE `paypal_cart_info` (
  `txnid` varchar(30) NOT NULL DEFAULT '',
  `itemname` varchar(255) NOT NULL DEFAULT '',
  `itemnumber` varchar(50) DEFAULT NULL,
  `os0` varchar(20) DEFAULT NULL,
  `on0` varchar(50) DEFAULT NULL,
  `os1` varchar(20) DEFAULT NULL,
  `on1` varchar(50) DEFAULT NULL,
  `quantity` char(3) NOT NULL DEFAULT '',
  `invoice` varchar(255) NOT NULL DEFAULT '',
  `custom` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `paypal_payment_info`;
CREATE TABLE `paypal_payment_info` (
  `firstname` varchar(100) NOT NULL DEFAULT '',
  `lastname` varchar(100) NOT NULL DEFAULT '',
  `buyer_email` varchar(100) NOT NULL DEFAULT '',
  `street` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(50) NOT NULL DEFAULT '',
  `state` char(3) NOT NULL DEFAULT '',
  `zipcode` varchar(11) NOT NULL DEFAULT '',
  `memo` varchar(255) DEFAULT NULL,
  `itemname` varchar(255) DEFAULT NULL,
  `itemnumber` varchar(50) DEFAULT NULL,
  `os0` varchar(20) DEFAULT NULL,
  `on0` varchar(50) DEFAULT NULL,
  `os1` varchar(20) DEFAULT NULL,
  `on1` varchar(50) DEFAULT NULL,
  `quantity` char(3) DEFAULT NULL,
  `paymentdate` varchar(50) NOT NULL DEFAULT '',
  `paymenttype` varchar(10) NOT NULL DEFAULT '',
  `txnid` varchar(30) NOT NULL DEFAULT '',
  `mc_gross` varchar(6) NOT NULL DEFAULT '',
  `mc_fee` varchar(5) NOT NULL DEFAULT '',
  `paymentstatus` varchar(15) NOT NULL DEFAULT '',
  `pendingreason` varchar(10) DEFAULT NULL,
  `txntype` varchar(10) NOT NULL DEFAULT '',
  `tax` varchar(10) DEFAULT NULL,
  `mc_currency` varchar(5) NOT NULL DEFAULT '',
  `reasoncode` varchar(20) NOT NULL DEFAULT '',
  `custom` varchar(255) NOT NULL DEFAULT '',
  `country` varchar(20) NOT NULL DEFAULT '',
  `datecreation` date DEFAULT NULL,
  `item_given` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `paypal_subscription_info`;
CREATE TABLE `paypal_subscription_info` (
  `subscr_id` varchar(255) NOT NULL DEFAULT '',
  `sub_event` varchar(50) NOT NULL DEFAULT '',
  `subscr_date` varchar(255) NOT NULL DEFAULT '',
  `subscr_effective` varchar(255) NOT NULL DEFAULT '',
  `period1` varchar(255) NOT NULL DEFAULT '',
  `period2` varchar(255) NOT NULL DEFAULT '',
  `period3` varchar(255) NOT NULL DEFAULT '',
  `amount1` varchar(255) NOT NULL DEFAULT '',
  `amount2` varchar(255) NOT NULL DEFAULT '',
  `amount3` varchar(255) NOT NULL DEFAULT '',
  `mc_amount1` varchar(255) NOT NULL DEFAULT '',
  `mc_amount2` varchar(255) NOT NULL DEFAULT '',
  `mc_amount3` varchar(255) NOT NULL DEFAULT '',
  `recurring` varchar(255) NOT NULL DEFAULT '',
  `reattempt` varchar(255) NOT NULL DEFAULT '',
  `retry_at` varchar(255) NOT NULL DEFAULT '',
  `recur_times` varchar(255) NOT NULL DEFAULT '',
  `username` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) DEFAULT NULL,
  `payment_txn_id` varchar(50) NOT NULL DEFAULT '',
  `subscriber_emailaddress` varchar(255) NOT NULL DEFAULT '',
  `datecreation` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Форум (f_*) — состав колонок выведен из components/forum/*
-- ----------------------------
DROP TABLE IF EXISTS `f_categories`;
CREATE TABLE `f_categories` (
  `cat_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(100) NOT NULL DEFAULT '',
  `cat_disp_position` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `f_forums`;
CREATE TABLE `f_forums` (
  `forum_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `cat_id` smallint unsigned NOT NULL DEFAULT '0',
  `forum_name` varchar(150) NOT NULL DEFAULT '',
  `forum_desc` text,
  `disp_position` smallint NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `quick_reply` tinyint(1) NOT NULL DEFAULT '1',
  `num_topics` int unsigned NOT NULL DEFAULT '0',
  `num_posts` int unsigned NOT NULL DEFAULT '0',
  `last_topic_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`forum_id`),
  KEY `idx_cat_id` (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `f_topics`;
CREATE TABLE `f_topics` (
  `topic_id` int unsigned NOT NULL AUTO_INCREMENT,
  `forum_id` smallint unsigned NOT NULL DEFAULT '0',
  `topic_name` varchar(255) NOT NULL DEFAULT '',
  `topic_poster_id` int unsigned NOT NULL DEFAULT '0',
  `topic_poster` varchar(255) NOT NULL DEFAULT '',
  `topic_posted` int unsigned NOT NULL DEFAULT '0',
  `last_post` int unsigned NOT NULL DEFAULT '0',
  `last_post_id` int unsigned NOT NULL DEFAULT '0',
  `last_poster` varchar(255) NOT NULL DEFAULT '',
  `num_replies` int unsigned NOT NULL DEFAULT '0',
  `num_views` int unsigned NOT NULL DEFAULT '0',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`topic_id`),
  KEY `idx_forum_id` (`forum_id`),
  KEY `idx_last_post` (`last_post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `f_posts`;
CREATE TABLE `f_posts` (
  `post_id` int unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` int unsigned NOT NULL DEFAULT '0',
  `poster` varchar(255) NOT NULL DEFAULT '',
  `poster_id` int unsigned NOT NULL DEFAULT '0',
  `poster_character_id` int unsigned NOT NULL DEFAULT '0',
  `poster_ip` varchar(15) NOT NULL DEFAULT '0.0.0.0',
  `message` text,
  `posted` int unsigned NOT NULL DEFAULT '0',
  `edited` int unsigned NOT NULL DEFAULT '0',
  `edited_by` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`post_id`),
  KEY `idx_topic_id` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `f_markread`;
CREATE TABLE `f_markread` (
  `marker_member_id` int unsigned NOT NULL,
  `marker_forum_id` smallint unsigned NOT NULL,
  `marker_topics_read` text,
  `marker_last_update` int unsigned NOT NULL DEFAULT '0',
  `marker_unread` int NOT NULL DEFAULT '0',
  `marker_last_cleared` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`marker_member_id`,`marker_forum_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `f_attachs`;
CREATE TABLE `f_attachs` (
  `attach_id` int unsigned NOT NULL AUTO_INCREMENT,
  `attach_file` varchar(255) NOT NULL DEFAULT '',
  `attach_location` varchar(255) NOT NULL DEFAULT '',
  `attach_date` int unsigned NOT NULL DEFAULT '0',
  `attach_tid` int unsigned NOT NULL DEFAULT '0',
  `attach_member_id` int unsigned NOT NULL DEFAULT '0',
  `attach_filesize` int unsigned NOT NULL DEFAULT '0',
  `attach_hits` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`attach_id`),
  KEY `idx_attach_member_id` (`attach_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- дефолтные категория/форум (код форума без них пустой, но работает)
INSERT INTO `f_categories` VALUES (1, 'Форум', 1);
INSERT INTO `f_forums` VALUES (1, 1, 'Общий форум', 'Общий раздел форума', 1, 0, 0, 1, 0, 0, 0);

-- ----------------------------
-- VIEW на игровые read-only таблицы (создаёт установщик,
-- {{AUTH_DB}} заменяется на имя auth-базы из формы)
-- Код читает realmlist/uptime через веб-соединение.
-- ----------------------------
DROP VIEW IF EXISTS `realmlist`;
CREATE VIEW `realmlist` AS
SELECT `r`.*, `r`.`flag` AS `realmflags`
FROM `{{AUTH_DB}}`.`realmlist` AS `r`;

DROP VIEW IF EXISTS `uptime`;
CREATE VIEW `uptime` AS
SELECT * FROM `{{AUTH_DB}}`.`uptime`;

DROP VIEW IF EXISTS `account_access`;
CREATE VIEW `account_access` AS
SELECT * FROM `{{AUTH_DB}}`.`account_access`;

DROP TABLE IF EXISTS `website_version`;
CREATE TABLE `website_version` (
  `version` int NOT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `website_version` VALUES (1);

SET FOREIGN_KEY_CHECKS=1;
