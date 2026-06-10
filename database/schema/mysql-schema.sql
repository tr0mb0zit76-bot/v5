/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `city_id` bigint unsigned NOT NULL,
  `address_line` varchar(500) NOT NULL,
  `normalized_address` varchar(500) DEFAULT NULL,
  `kladr_id` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_city_id_normalized_address_index` (`city_id`,`normalized_address`),
  KEY `addresses_kladr_id_index` (`kladr_id`),
  CONSTRAINT `addresses_city_id_foreign` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_conversation_messages` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversation_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tool_calls` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tool_results` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usage` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_index` (`conversation_id`,`user_id`,`updated_at`),
  KEY `agent_conversation_messages_user_id_index` (`user_id`),
  KEY `agent_conversation_messages_conversation_id_index` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agent_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_conversations` (
  `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agent_conversations_user_id_updated_at_index` (`user_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint unsigned NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` int DEFAULT NULL,
  `disk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `extracted_data` json DEFAULT NULL,
  `vector_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_attachments_message_id_foreign` (`message_id`),
  KEY `ai_attachments_status_index` (`status`),
  CONSTRAINT `ai_attachments_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'chat',
  `context` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_conversations_user_id_foreign` (`user_id`),
  KEY `ai_conversations_session_id_index` (`session_id`),
  KEY `ai_conversations_last_activity_at_index` (`last_activity_at`),
  CONSTRAINT `ai_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_feedback_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_feedback_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `feedback_type` enum('like','dislike') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_response` json NOT NULL,
  `corrected_data` json DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `processing_time_ms` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_feedback_log_user_id_index` (`user_id`),
  KEY `ai_feedback_log_message_id_index` (`message_id`),
  KEY `ai_feedback_log_created_at_index` (`created_at`),
  CONSTRAINT `ai_feedback_log_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_feedback_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_knowledge_index`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_knowledge_index` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_type` enum('order','document','contractor','driver','kpi_pattern','message') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint unsigned NOT NULL,
  `vector_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `indexed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `source_unique` (`source_type`,`source_id`),
  KEY `ai_knowledge_index_vector_id_index` (`vector_id`),
  KEY `ai_knowledge_index_indexed_at_index` (`indexed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `role` enum('user','assistant','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `feedback` enum('like','dislike') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `feedback_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `corrected_data` json DEFAULT NULL,
  `context_used` json DEFAULT NULL,
  `sources` json DEFAULT NULL,
  `tokens_used` int DEFAULT NULL,
  `processing_time` double DEFAULT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_messages_conversation_id_foreign` (`conversation_id`),
  KEY `ai_messages_created_at_index` (`created_at`),
  CONSTRAINT `ai_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_order_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_order_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `parsed_data` json NOT NULL,
  `edited_data` json DEFAULT NULL,
  `ai_suggestions` json DEFAULT NULL,
  `status` enum('draft','confirmed','edited','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `source` enum('text','file','email','telegram') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `source_file` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_order_drafts_user_id_foreign` (`user_id`),
  KEY `ai_order_drafts_conversation_id_foreign` (`conversation_id`),
  KEY `ai_order_drafts_order_id_foreign` (`order_id`),
  KEY `ai_order_drafts_status_index` (`status`),
  CONSTRAINT `ai_order_drafts_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_order_drafts_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_order_drafts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_parser_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_parser_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `raw_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parsed_json` json DEFAULT NULL,
  `raw_response` json DEFAULT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `processing_route` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processing_time_ms` int DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_feedback` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_parser_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_parser_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ati_dictionary_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ati_dictionary_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dictionary` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ati_id` int unsigned DEFAULT NULL,
  `code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `raw` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ati_dictionary_items_dictionary_ati_id_unique` (`dictionary`,`ati_id`),
  UNIQUE KEY `ati_dictionary_items_dictionary_code_unique` (`dictionary`,`code`),
  KEY `ati_dictionary_items_dictionary_is_active_index` (`dictionary`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cargo_leg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cargo_leg` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cargo_id` bigint unsigned NOT NULL,
  `order_leg_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `status` enum('planned','loaded','unloaded','damaged','lost') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cargo_leg_cargo_id_order_leg_id_unique` (`cargo_id`,`order_leg_id`),
  KEY `cargo_leg_order_leg_id_status_index` (`order_leg_id`,`status`),
  CONSTRAINT `cargo_leg_cargo_id_foreign` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cargo_leg_order_leg_id_foreign` FOREIGN KEY (`order_leg_id`) REFERENCES `order_legs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cargos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cargos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ati_cargo_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_value` decimal(12,3) DEFAULT NULL,
  `weight_unit` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `volume` decimal(10,2) DEFAULT NULL,
  `cargo_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_type_id` int unsigned DEFAULT NULL COMMENT 'ID из словаря АТИ',
  `cargo_type_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `packing_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_count` int unsigned DEFAULT NULL,
  `pack_type_id` int unsigned DEFAULT NULL COMMENT 'ID из словаря АТИ',
  `pack_type_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_type_id` int unsigned DEFAULT NULL,
  `loading_type_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_type_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_type_items` json DEFAULT NULL,
  `truck_body_type_id` int unsigned DEFAULT NULL,
  `truck_body_type_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `truck_body_type_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `truck_body_type_items` json DEFAULT NULL,
  `trailer_type_id` int unsigned DEFAULT NULL,
  `trailer_type_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_type_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_type_items` json DEFAULT NULL,
  `pallet_count` int DEFAULT NULL,
  `belt_count` int DEFAULT NULL,
  `length` decimal(10,2) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `diameter` decimal(10,2) DEFAULT NULL,
  `is_hazardous` tinyint(1) NOT NULL DEFAULT '0',
  `hazard_class` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hs_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `needs_temperature` tinyint(1) NOT NULL DEFAULT '0',
  `temp_min` decimal(5,2) DEFAULT NULL,
  `temp_max` decimal(5,2) DEFAULT NULL,
  `needs_hydraulic` tinyint(1) NOT NULL DEFAULT '0',
  `needs_manipulator` tinyint(1) NOT NULL DEFAULT '0',
  `is_oversized` tinyint(1) NOT NULL DEFAULT '0',
  `is_fragile` tinyint(1) NOT NULL DEFAULT '0',
  `special_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `photos` json DEFAULT NULL,
  `documents` json DEFAULT NULL,
  `ati_load_id` bigint unsigned DEFAULT NULL,
  `ati_published_at` timestamp NULL DEFAULT NULL,
  `ati_response` json DEFAULT NULL,
  `ati_cargo_payload` json DEFAULT NULL,
  `source_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `source_file` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parsed_by_ai` tinyint(1) NOT NULL DEFAULT '0',
  `parsed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cargos_created_by_foreign` (`created_by`),
  KEY `cargos_updated_by_foreign` (`updated_by`),
  KEY `cargos_title_index` (`title`),
  KEY `cargos_weight_index` (`weight`),
  KEY `cargos_ati_load_id_index` (`ati_load_id`),
  KEY `cargos_order_id_foreign` (`order_id`),
  CONSTRAINT `cargos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cargos_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cargos_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `recipient_user_id` bigint unsigned DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_conversation_id_foreign` (`conversation_id`),
  KEY `chat_messages_user_id_foreign` (`user_id`),
  KEY `chat_messages_recipient_user_id_foreign` (`recipient_user_id`),
  CONSTRAINT `chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `normalized_name` varchar(255) DEFAULT NULL,
  `kladr_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_name_index` (`name`),
  KEY `cities_normalized_name_index` (`normalized_name`),
  KEY `cities_kladr_id_index` (`kladr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_activity_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_activity_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contractor_activity_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_decision_maker` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_contacts_contractor_id_foreign` (`contractor_id`),
  CONSTRAINT `contractor_contacts_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_documents_contractor_id_foreign` (`contractor_id`),
  KEY `contractor_documents_created_by_foreign` (`created_by`),
  CONSTRAINT `contractor_documents_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_interactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `channel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_interactions_contractor_id_foreign` (`contractor_id`),
  KEY `contractor_interactions_created_by_foreign` (`created_by`),
  CONSTRAINT `contractor_interactions_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_interactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('customer','carrier','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `inn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kpp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ogrn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `okpo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_form` enum('ooo','zao','ao','ip','samozanyaty','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actual_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bik` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correspondent_account` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_accounts` json DEFAULT NULL,
  `ati_profiles` json DEFAULT NULL,
  `ati_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transport_requirements` json DEFAULT NULL,
  `specializations` json DEFAULT NULL,
  `activity_types` json DEFAULT NULL,
  `rating` decimal(3,2) NOT NULL DEFAULT '0.00',
  `completed_orders` int NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `debt_limit` decimal(12,2) DEFAULT NULL,
  `debt_limit_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `stop_on_limit` tinyint(1) NOT NULL DEFAULT '0',
  `default_customer_payment_form` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_customer_payment_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_customer_payment_schedule` json DEFAULT NULL,
  `default_carrier_payment_form` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_carrier_payment_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_carrier_payment_schedule` json DEFAULT NULL,
  `cooperation_terms_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_own_company` tinyint(1) NOT NULL DEFAULT '0',
  `is_non_resident` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `owner_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `signer_name_nominative` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_name_prepositional` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_authority_basis` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_bank_swift` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_bank_account` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnaps_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_settlement_account` varchar(34) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractors_created_by_foreign` (`created_by`),
  KEY `contractors_updated_by_foreign` (`updated_by`),
  KEY `contractors_type_is_active_index` (`type`,`is_active`),
  KEY `contractors_name_index` (`name`),
  KEY `contractors_inn_index` (`inn`),
  KEY `contractors_phone_index` (`phone`),
  KEY `contractors_email_index` (`email`),
  KEY `contractors_is_active_index` (`is_active`),
  KEY `contractors_owner_id_foreign` (`owner_id`),
  CONSTRAINT `contractors_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractors_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractors_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_participants_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_participants_user_id_foreign` (`user_id`),
  CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_created_by_foreign` (`created_by`),
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drivers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `patronymic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `drivers_contractor_id_foreign` (`contractor_id`),
  KEY `drivers_phone_index` (`phone`),
  CONSTRAINT `drivers_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `finance_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `document_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'invoice or upd',
  `status` enum('draft','issued','sent','signed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_basis` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `finance_documents_order_id_foreign` (`order_id`),
  KEY `finance_documents_created_by_foreign` (`created_by`),
  KEY `finance_documents_updated_by_foreign` (`updated_by`),
  CONSTRAINT `finance_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `finance_documents_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `finance_documents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_terms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `client_price` decimal(12,2) DEFAULT NULL,
  `client_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `client_payment_terms` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contractors_costs` json DEFAULT NULL,
  `total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `margin` decimal(12,2) NOT NULL DEFAULT '0.00',
  `additional_costs` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_terms_snapshot` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `financial_terms_order_id_foreign` (`order_id`),
  CONSTRAINT `financial_terms_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_driver_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_driver_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fleet_driver_id` bigint unsigned NOT NULL,
  `document_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_driver_documents_fleet_driver_id_foreign` (`fleet_driver_id`),
  KEY `fleet_driver_documents_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `fleet_driver_documents_fleet_driver_id_foreign` FOREIGN KEY (`fleet_driver_id`) REFERENCES `fleet_drivers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_driver_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_drivers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `carrier_contractor_id` bigint unsigned NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `passport_series` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_issued_by` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_issued_at` date DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_categories` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_drivers_carrier_contractor_id_foreign` (`carrier_contractor_id`),
  CONSTRAINT `fleet_drivers_carrier_contractor_id_foreign` FOREIGN KEY (`carrier_contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_vehicle_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_vehicle_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fleet_vehicle_id` bigint unsigned NOT NULL,
  `document_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_vehicle_documents_fleet_vehicle_id_foreign` (`fleet_vehicle_id`),
  KEY `fleet_vehicle_documents_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `fleet_vehicle_documents_fleet_vehicle_id_foreign` FOREIGN KEY (`fleet_vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_vehicle_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_contractor_id` bigint unsigned NOT NULL,
  `tractor_brand` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_brand` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tractor_plate` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_plate` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_vehicles_owner_contractor_id_foreign` (`owner_contractor_id`),
  CONSTRAINT `fleet_vehicles_owner_contractor_id_foreign` FOREIGN KEY (`owner_contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kpi_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kpi_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `group` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kpi_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kpi_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kpi_thresholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `deal_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `threshold_from` decimal(5,2) NOT NULL,
  `threshold_to` decimal(5,2) NOT NULL,
  `kpi_percent` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_kpi_threshold_range` (`deal_type`,`threshold_from`,`threshold_to`),
  KEY `kpi_thresholds_deal_type_is_active_index` (`deal_type`,`is_active`),
  KEY `kpi_thresholds_threshold_from_threshold_to_index` (`threshold_from`,`threshold_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'note',
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `next_action_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_activities_lead_id_foreign` (`lead_id`),
  KEY `lead_activities_created_by_foreign` (`created_by`),
  CONSTRAINT `lead_activities_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_activities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_cargo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_cargo_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `volume_m3` decimal(10,2) DEFAULT NULL,
  `package_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_count` int unsigned DEFAULT NULL,
  `dangerous_goods` tinyint(1) NOT NULL DEFAULT '0',
  `dangerous_class` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hs_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_cargo_items_lead_id_foreign` (`lead_id`),
  CONSTRAINT `lead_cargo_items_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_offers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offer_date` date DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `payload` json DEFAULT NULL,
  `generated_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_offers_lead_id_foreign` (`lead_id`),
  KEY `lead_offers_created_by_foreign` (`created_by`),
  CONSTRAINT `lead_offers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_offers_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_route_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_route_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int unsigned NOT NULL DEFAULT '1',
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_data` json DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_route_points_lead_id_foreign` (`lead_id`),
  CONSTRAINT `lead_route_points_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counterparty_id` bigint unsigned DEFAULT NULL,
  `responsible_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transport_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unloading_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `planned_shipping_date` date DEFAULT NULL,
  `target_price` decimal(12,2) DEFAULT NULL,
  `target_currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `calculated_cost` decimal(12,2) DEFAULT NULL,
  `expected_margin` decimal(12,2) DEFAULT NULL,
  `proposal_sent_at` timestamp NULL DEFAULT NULL,
  `next_contact_at` timestamp NULL DEFAULT NULL,
  `lost_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_qualification` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_number_unique` (`number`),
  KEY `leads_status_index` (`status`),
  KEY `leads_counterparty_id_foreign` (`counterparty_id`),
  KEY `leads_responsible_id_foreign` (`responsible_id`),
  KEY `leads_created_by_foreign` (`created_by`),
  KEY `leads_updated_by_foreign` (`updated_by`),
  CONSTRAINT `leads_counterparty_id_foreign` FOREIGN KEY (`counterparty_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_responsible_id_foreign` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leg_contractor_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leg_contractor_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_leg_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` bigint unsigned NOT NULL,
  `status` enum('pending','confirmed','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leg_contractor_assignments_order_leg_id_unique` (`order_leg_id`),
  KEY `leg_contractor_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `leg_contractor_assignments_contractor_id_status_index` (`contractor_id`,`status`),
  KEY `leg_contractor_assignments_assigned_at_index` (`assigned_at`),
  CONSTRAINT `leg_contractor_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `leg_contractor_assignments_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leg_contractor_assignments_order_leg_id_foreign` FOREIGN KEY (`order_leg_id`) REFERENCES `order_legs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leg_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leg_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_leg_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `payment_form` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_schedule` json DEFAULT NULL,
  `status` enum('draft','negotiated','confirmed','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `calculated_at` timestamp NULL DEFAULT NULL,
  `calculated_by` bigint unsigned DEFAULT NULL,
  `leg_contractor_assignment_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leg_costs_order_leg_id_unique` (`order_leg_id`),
  KEY `leg_costs_calculated_by_foreign` (`calculated_by`),
  KEY `leg_costs_leg_contractor_assignment_id_foreign` (`leg_contractor_assignment_id`),
  KEY `leg_costs_status_calculated_at_index` (`status`,`calculated_at`),
  KEY `leg_costs_amount_index` (`amount`),
  CONSTRAINT `leg_costs_calculated_by_foreign` FOREIGN KEY (`calculated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `leg_costs_leg_contractor_assignment_id_foreign` FOREIGN KEY (`leg_contractor_assignment_id`) REFERENCES `leg_contractor_assignments` (`id`),
  CONSTRAINT `leg_costs_order_leg_id_foreign` FOREIGN KEY (`order_leg_id`) REFERENCES `order_legs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0.0',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `order` int NOT NULL DEFAULT '0',
  `dependencies` json DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modules_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned DEFAULT NULL,
  `entity_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order',
  `entity_id` bigint unsigned DEFAULT NULL,
  `type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'uploaded',
  `number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_pdf_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `workflow_status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_requested',
  `requires_counterparty_signature` tinyint(1) NOT NULL DEFAULT '0',
  `signed_at` timestamp NULL DEFAULT NULL,
  `signed_by` bigint unsigned DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `approval_requested_at` timestamp NULL DEFAULT NULL,
  `approval_requested_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `internal_signed_at` timestamp NULL DEFAULT NULL,
  `internal_signed_by` bigint unsigned DEFAULT NULL,
  `internal_signed_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counterparty_signed_at` timestamp NULL DEFAULT NULL,
  `counterparty_signed_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `snapshot_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_documents_template_id_foreign` (`template_id`),
  KEY `order_documents_signed_by_foreign` (`signed_by`),
  KEY `order_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `order_documents_approval_requested_by_foreign` (`approval_requested_by`),
  KEY `order_documents_approved_by_foreign` (`approved_by`),
  KEY `order_documents_rejected_by_foreign` (`rejected_by`),
  KEY `order_documents_internal_signed_by_foreign` (`internal_signed_by`),
  KEY `order_documents_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  KEY `order_documents_order_id_type_index` (`order_id`,`type`),
  KEY `order_documents_status_workflow_status_index` (`status`,`workflow_status`),
  KEY `order_documents_document_date_index` (`document_date`),
  CONSTRAINT `order_documents_approval_requested_by_foreign` FOREIGN KEY (`approval_requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_internal_signed_by_foreign` FOREIGN KEY (`internal_signed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_signed_by_foreign` FOREIGN KEY (`signed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `print_form_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_legs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_legs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `sequence` int NOT NULL DEFAULT '0',
  `type` enum('transport','storage','transshipment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'transport',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_legs_order_id_sequence_index` (`order_id`,`sequence`),
  CONSTRAINT `order_legs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `status_from` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_status_logs_order_id_foreign` (`order_id`),
  KEY `order_status_logs_created_by_foreign` (`created_by`),
  CONSTRAINT `order_status_logs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_status_logs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `loading_date` date DEFAULT NULL,
  `unloading_date` date DEFAULT NULL,
  `customer_rate` decimal(12,2) DEFAULT NULL,
  `customer_payment_form` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_payment_term` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_payment_date` date DEFAULT NULL,
  `special_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `svh_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `svh_address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_post_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_post_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_declaration_place` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_commodity_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_payment_date` date DEFAULT NULL,
  `additional_expenses` decimal(12,2) NOT NULL DEFAULT '0.00',
  `additional_expenses_payment_date` date DEFAULT NULL,
  `insurance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `kpi_percent` decimal(5,2) DEFAULT NULL,
  `delta` decimal(12,2) DEFAULT NULL,
  `salary_accrued` decimal(12,2) NOT NULL DEFAULT '0.00',
  `salary_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `payment_status` enum('pending','partial','paid','overdue','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `manual_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_updated_by` bigint unsigned DEFAULT NULL,
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `customer_id` bigint unsigned DEFAULT NULL,
  `own_company_id` bigint unsigned DEFAULT NULL,
  `own_company_bank_account_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `driver_id` bigint unsigned DEFAULT NULL,
  `ai_draft_id` bigint unsigned DEFAULT NULL,
  `ai_confidence` decimal(5,2) DEFAULT NULL,
  `ai_metadata` json DEFAULT NULL,
  `ati_response` json DEFAULT NULL,
  `ati_load_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ati_published_at` timestamp NULL DEFAULT NULL,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upd_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waybill_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_number_customer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_sent_date_customer` date DEFAULT NULL,
  `track_received_date_customer` date DEFAULT NULL,
  `track_number_carrier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_sent_date_carrier` date DEFAULT NULL,
  `track_received_date_carrier` date DEFAULT NULL,
  `order_customer_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_customer_date` date DEFAULT NULL,
  `order_carrier_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_carrier_date` date DEFAULT NULL,
  `upd_carrier_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upd_carrier_date` date DEFAULT NULL,
  `customer_contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_contact_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_contact_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `payment_statuses` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `wizard_state` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_ati_load_id_unique` (`ati_load_id`),
  KEY `orders_status_updated_by_foreign` (`status_updated_by`),
  KEY `orders_customer_id_foreign` (`customer_id`),
  KEY `orders_carrier_id_foreign` (`carrier_id`),
  KEY `orders_driver_id_foreign` (`driver_id`),
  KEY `orders_created_by_foreign` (`created_by`),
  KEY `orders_updated_by_foreign` (`updated_by`),
  KEY `orders_manager_id_order_date_index` (`manager_id`,`order_date`),
  KEY `orders_status_is_active_index` (`status`,`is_active`),
  KEY `orders_order_number_index` (`order_number`),
  KEY `orders_company_code_index` (`company_code`),
  KEY `orders_order_date_index` (`order_date`),
  KEY `orders_loading_date_index` (`loading_date`),
  KEY `orders_unloading_date_index` (`unloading_date`),
  KEY `orders_status_index` (`status`),
  KEY `orders_ai_draft_id_index` (`ai_draft_id`),
  KEY `orders_own_company_id_foreign` (`own_company_id`),
  CONSTRAINT `orders_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_own_company_id_foreign` FOREIGN KEY (`own_company_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_status_updated_by_foreign` FOREIGN KEY (`status_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `party` enum('customer','carrier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('prepayment','final') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `invoice_number` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_partial` tinyint(1) NOT NULL DEFAULT '0',
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `status` enum('pending','paid','overdue','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `counterparty_id` bigint unsigned DEFAULT NULL,
  `parent_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_schedules_order_id_party_type_index` (`order_id`,`party`,`type`),
  KEY `payment_schedules_status_index` (`status`),
  KEY `payment_schedules_counterparty_id_foreign` (`counterparty_id`),
  KEY `payment_schedules_parent_payment_id_foreign` (`parent_payment_id`),
  CONSTRAINT `payment_schedules_counterparty_id_foreign` FOREIGN KEY (`counterparty_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_schedules_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_schedules_parent_payment_id_foreign` FOREIGN KEY (`parent_payment_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `print_form_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `print_form_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order',
  `document_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `party` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `contractor_id` bigint unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `file_disk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vue_component` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_view` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_internal_signature` tinyint(1) NOT NULL DEFAULT '1',
  `requires_counterparty_signature` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `version` int unsigned NOT NULL DEFAULT '1',
  `settings` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `print_form_templates_code_unique` (`code`),
  KEY `print_form_templates_document_type_index` (`document_type`),
  KEY `print_form_templates_document_group_index` (`document_group`),
  KEY `print_form_templates_party_index` (`party`),
  KEY `print_form_templates_is_active_index` (`is_active`),
  KEY `print_form_templates_created_by_foreign` (`created_by`),
  KEY `print_form_templates_updated_by_foreign` (`updated_by`),
  KEY `print_form_templates_contractor_id_foreign` (`contractor_id`),
  KEY `print_form_templates_entity_type_index` (`entity_type`),
  KEY `print_form_templates_source_type_index` (`source_type`),
  KEY `print_form_templates_is_default_index` (`is_default`),
  CONSTRAINT `print_form_templates_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `print_form_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `print_form_templates_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `permissions` json DEFAULT NULL,
  `visibility_areas` json DEFAULT NULL,
  `visibility_scopes` json DEFAULT NULL,
  `columns_config` json DEFAULT NULL,
  `default_mobile_nav_keys` json DEFAULT NULL,
  `has_signing_authority` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `route_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `route_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_leg_id` bigint unsigned NOT NULL,
  `address_id` bigint unsigned DEFAULT NULL,
  `type` enum('loading','unloading','transit','customs','warehouse') NOT NULL DEFAULT 'transit',
  `sequence` int NOT NULL DEFAULT '0',
  `address` varchar(500) DEFAULT NULL,
  `normalized_data` json DEFAULT NULL,
  `kladr_id` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `planned_time_from` time DEFAULT NULL,
  `planned_time_to` time DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `actual_time` time DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `instructions` text,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `sender_contact` varchar(255) DEFAULT NULL,
  `sender_phone` varchar(50) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_contact` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `route_points_address_id_foreign` (`address_id`),
  KEY `route_points_order_leg_id_sequence_index` (`order_leg_id`,`sequence`),
  KEY `route_points_order_leg_id_type_index` (`order_leg_id`,`type`),
  CONSTRAINT `route_points_address_id_foreign` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `route_points_order_leg_id_foreign` FOREIGN KEY (`order_leg_id`) REFERENCES `order_legs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_accruals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_accruals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `order_date_snapshot` date DEFAULT NULL,
  `delta_snapshot` decimal(14,2) NOT NULL DEFAULT '0.00',
  `salary_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `customer_rate_snapshot` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid_customer_amount_at_accrual` decimal(14,2) NOT NULL DEFAULT '0.00',
  `payable_amount_computed` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid_amount_fact` decimal(14,2) NOT NULL DEFAULT '0.00',
  `unpaid_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_accruals_unique_period_user_order` (`period_id`,`user_id`,`order_id`),
  KEY `salary_accruals_period_id_user_id_index` (`period_id`,`user_id`),
  KEY `salary_accruals_period_id_order_id_index` (`period_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_coefficients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_coefficients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manager_id` bigint unsigned NOT NULL,
  `base_salary` int NOT NULL DEFAULT '0',
  `bonus_percent` int NOT NULL DEFAULT '0',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_manager_active` (`manager_id`,`effective_from`),
  KEY `salary_coefficients_manager_id_is_active_index` (`manager_id`,`is_active`),
  KEY `salary_coefficients_effective_from_effective_to_index` (`effective_from`,`effective_to`),
  CONSTRAINT `salary_coefficients_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_payout_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_payout_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payout_id` bigint unsigned NOT NULL,
  `accrual_id` bigint unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_payout_allocations_payout_id_accrual_id_unique` (`payout_id`,`accrual_id`),
  KEY `salary_payout_allocations_accrual_id_foreign` (`accrual_id`),
  CONSTRAINT `salary_payout_allocations_accrual_id_foreign` FOREIGN KEY (`accrual_id`) REFERENCES `salary_accruals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_payout_allocations_payout_id_foreign` FOREIGN KEY (`payout_id`) REFERENCES `salary_payouts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_payouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payout_date` date NOT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'salary',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_payouts_period_id_user_id_index` (`period_id`,`user_id`),
  KEY `salary_payouts_payout_date_index` (`payout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_periods_unique_period` (`period_start`,`period_end`,`period_type`),
  KEY `salary_periods_period_start_period_end_index` (`period_start`,`period_end`),
  KEY `salary_periods_period_type_status_index` (`period_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_book_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_book_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `markdown_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_book_articles_created_by_foreign` (`created_by`),
  KEY `sales_book_articles_updated_by_foreign` (`updated_by`),
  KEY `sales_book_articles_parent_id_sort_order_index` (`parent_id`,`sort_order`),
  CONSTRAINT `sales_book_articles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_book_articles_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `sales_book_articles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_book_articles_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_nodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_version_id` bigint unsigned NOT NULL,
  `client_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `capture_field_codes` json DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `canvas_x` int DEFAULT NULL,
  `canvas_y` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_script_nodes_sales_script_version_id_client_key_unique` (`sales_script_version_id`,`client_key`),
  CONSTRAINT `sales_script_nodes_sales_script_version_id_foreign` FOREIGN KEY (`sales_script_version_id`) REFERENCES `sales_script_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_capture_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_capture_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_script_capture_fields_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_node_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_node_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `capture_field_codes` json DEFAULT NULL,
  `default_transitions` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_script_node_templates_created_by_foreign` (`created_by`),
  CONSTRAINT `sales_script_node_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_play_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_play_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_play_session_id` bigint unsigned NOT NULL,
  `type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_script_node_id` bigint unsigned DEFAULT NULL,
  `sales_script_reaction_class_id` bigint unsigned DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sales_script_play_events_sales_script_play_session_id_foreign` (`sales_script_play_session_id`),
  KEY `sales_script_play_events_sales_script_node_id_foreign` (`sales_script_node_id`),
  KEY `sales_script_play_events_sales_script_reaction_class_id_foreign` (`sales_script_reaction_class_id`),
  CONSTRAINT `sales_script_play_events_sales_script_node_id_foreign` FOREIGN KEY (`sales_script_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_script_play_events_sales_script_play_session_id_foreign` FOREIGN KEY (`sales_script_play_session_id`) REFERENCES `sales_script_play_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_script_play_events_sales_script_reaction_class_id_foreign` FOREIGN KEY (`sales_script_reaction_class_id`) REFERENCES `sales_script_reaction_classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_play_session_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_play_session_field_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_play_session_id` bigint unsigned NOT NULL,
  `sales_script_capture_field_id` bigint unsigned NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at_node_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sspsfv_session_field_unique` (`sales_script_play_session_id`,`sales_script_capture_field_id`),
  KEY `sspsfv_capture_field_fk` (`sales_script_capture_field_id`),
  KEY `sspsfv_captured_node_fk` (`captured_at_node_id`),
  KEY `sspsfv_session_id_fk` (`sales_script_play_session_id`),
  CONSTRAINT `sspsfv_captured_node_fk` FOREIGN KEY (`captured_at_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sspsfv_capture_field_fk` FOREIGN KEY (`sales_script_capture_field_id`) REFERENCES `sales_script_capture_fields` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sspsfv_session_id_fk` FOREIGN KEY (`sales_script_play_session_id`) REFERENCES `sales_script_play_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_play_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_play_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `sales_script_version_id` bigint unsigned NOT NULL,
  `current_node_id` bigint unsigned DEFAULT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `is_trainer` tinyint(1) NOT NULL DEFAULT '0',
  `trainer_profile_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_profile_title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_profile_context` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `training_role_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager_seller',
  `trainer_assistant_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `trainer_dialog_quality` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_score` tinyint unsigned DEFAULT NULL,
  `trainer_ai_role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_reaction_class_id` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_script_play_sessions_user_id_foreign` (`user_id`),
  KEY `sales_script_play_sessions_sales_script_version_id_foreign` (`sales_script_version_id`),
  KEY `sales_script_play_sessions_current_node_id_foreign` (`current_node_id`),
  KEY `sales_script_play_sessions_primary_reaction_class_id_foreign` (`primary_reaction_class_id`),
  KEY `sales_script_play_sessions_is_trainer_index` (`is_trainer`),
  KEY `sales_script_play_sessions_trainer_score_index` (`trainer_score`),
  KEY `sales_script_play_sessions_training_role_mode_index` (`training_role_mode`),
  KEY `sales_script_play_sessions_trainer_dialog_quality_index` (`trainer_dialog_quality`),
  CONSTRAINT `sales_script_play_sessions_current_node_id_foreign` FOREIGN KEY (`current_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_script_play_sessions_primary_reaction_class_id_foreign` FOREIGN KEY (`primary_reaction_class_id`) REFERENCES `sales_script_reaction_classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_script_play_sessions_sales_script_version_id_foreign` FOREIGN KEY (`sales_script_version_id`) REFERENCES `sales_script_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_script_play_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_reaction_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_reaction_classes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_script_reaction_classes_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_trainer_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_trainer_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_play_session_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `peer_reaction` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_peer_reaction` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sstm_user` (`user_id`),
  KEY `sstm_sess_id_idx` (`sales_script_play_session_id`,`id`),
  CONSTRAINT `fk_sstm_play_session` FOREIGN KEY (`sales_script_play_session_id`) REFERENCES `sales_script_play_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sstm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_transitions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_transitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_version_id` bigint unsigned NOT NULL,
  `from_node_id` bigint unsigned NOT NULL,
  `to_node_id` bigint unsigned NOT NULL,
  `sales_script_reaction_class_id` bigint unsigned DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_script_transitions_sales_script_version_id_foreign` (`sales_script_version_id`),
  KEY `sales_script_transitions_from_node_id_foreign` (`from_node_id`),
  KEY `sales_script_transitions_to_node_id_foreign` (`to_node_id`),
  KEY `sales_script_transitions_sales_script_reaction_class_id_foreign` (`sales_script_reaction_class_id`),
  CONSTRAINT `sales_script_transitions_from_node_id_foreign` FOREIGN KEY (`from_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_script_transitions_sales_script_reaction_class_id_foreign` FOREIGN KEY (`sales_script_reaction_class_id`) REFERENCES `sales_script_reaction_classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_script_transitions_sales_script_version_id_foreign` FOREIGN KEY (`sales_script_version_id`) REFERENCES `sales_script_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_script_transitions_to_node_id_foreign` FOREIGN KEY (`to_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_script_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL DEFAULT '1',
  `published_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `entry_node_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_script_versions_sales_script_id_version_number_unique` (`sales_script_id`,`version_number`),
  CONSTRAINT `sales_script_versions_sales_script_id_foreign` FOREIGN KEY (`sales_script_id`) REFERENCES `sales_scripts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_scripts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_scripts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `channel` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `disk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_attachments_task_id_index` (`task_id`),
  KEY `task_attachments_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_checklist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_checklist_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_checklist_items_task_id_index` (`task_id`),
  KEY `task_checklist_items_is_done_index` (`is_done`),
  KEY `task_checklist_items_created_by_index` (`created_by`),
  KEY `task_checklist_items_completed_by_index` (`completed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_comments_task_id_index` (`task_id`),
  KEY `task_comments_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `task_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_events_task_id_index` (`task_id`),
  KEY `task_events_user_id_index` (`user_id`),
  KEY `task_events_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `due_at` timestamp NULL DEFAULT NULL,
  `sla_deadline_at` timestamp NULL DEFAULT NULL,
  `sla_escalated_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `responsible_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_number_unique` (`number`),
  KEY `tasks_status_index` (`status`),
  KEY `tasks_priority_index` (`priority`),
  KEY `tasks_due_at_index` (`due_at`),
  KEY `tasks_created_by_index` (`created_by`),
  KEY `tasks_responsible_id_index` (`responsible_id`),
  KEY `tasks_lead_id_index` (`lead_id`),
  KEY `tasks_order_id_index` (`order_id`),
  KEY `tasks_contractor_id_index` (`contractor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `has_signing_authority` tinyint(1) NOT NULL DEFAULT '0',
  `ai_preferences` json DEFAULT NULL,
  `mobile_nav_keys` json DEFAULT NULL,
  `ui_preferences` json DEFAULT NULL,
  `ai_learning_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_index` (`role_id`),
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vat_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vat_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_percent` decimal(5,2) NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vat_rates_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'create_logist_v5_plus_schema',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'0001_01_01_000001_create_cache_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'0001_01_01_000002_create_jobs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_30_110520_create_sessions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_01_01_000001_create_modules_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_31_112755_add_visibility_areas_to_roles_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_31_115339_create_contractor_contacts_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_31_115339_create_contractor_documents_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_31_115339_create_contractor_interactions_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_31_122655_add_is_own_company_to_contractors_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_04_01_120000_enhance_orders_for_wizard_module',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_30_115230_create_modules_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_04_02_070620_add_visibility_scopes_to_roles_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_04_02_124107_create_core_auth_tables',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_04_02_125119_add_lead_id_to_orders_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_04_02_125119_create_lead_activities_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_04_02_125119_create_lead_cargo_items_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_04_02_125119_create_lead_offers_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_04_02_125119_create_leads_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_04_02_125223_create_lead_route_points_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_04_02_131448_ensure_lead_id_on_orders_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_04_02_131731_add_lead_foreign_keys',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'create_all_tables',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'0001_01_01_000000_create_roles_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'0001_01_01_000001_create_users_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'0001_01_01_000002_create_password_reset_tokens_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_04_03_180000_add_print_forms_workflow_foundation',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_04_03_230000_add_credit_policy_and_default_terms_to_contractors_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_04_04_000000_ensure_contractor_operational_schema',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_04_04_000100_ensure_order_operational_schema',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_04_04_010000_add_default_payment_schedules_to_contractors_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_04_04_020000_add_profile_fields_to_contractors_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_04_04_030000_create_contractor_activity_types_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_04_04_040000_extend_roles_and_print_form_templates',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_04_04_041000_add_user_signing_authority_flag',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_04_04_143300_add_signer_fields_to_contractors_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_04_04_143305_add_cargo_party_fields_to_orders_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_04_05_073733_create_finance_documents_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_04_06_073149_add_sender_recipient_fields_to_route_points_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_04_06_073336_remove_cargo_party_fields_from_orders_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_04_06_074400_create_route_points_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_04_06_074500_add_sender_recipient_fields_to_route_points_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_04_06_173545_add_payment_date_columns_to_orders_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_04_06_170440_add_sender_recipient_fields_to_existing_route_points_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_04_07_074150_add_owner_id_to_contractors_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_04_07_163658_create_agent_conversations_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_04_08_074317_create_leg_contractor_assignments_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_04_08_074349_create_leg_costs_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_04_08_074422_update_order_documents_table_for_tracking',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_04_08_074932_update_orders_table_for_new_architecture',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_04_08_080236_update_orders_table_for_new_architecture',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_04_08_092752_add_foreign_key_to_contractor_documents_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_04_08_093017_add_foreign_key_to_contractor_interactions_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_04_08_093353_add_foreign_key_to_contractor_contacts_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_04_08_115322_backfill_leg_assignments_from_financial_terms',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_04_08_150021_create_agent_conversations_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_04_08_160432_add_payment_terms_snapshot_to_financial_terms_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_04_08_161912_add_wizard_state_to_orders_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_04_09_094901_create_salary_accruals_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_04_09_094901_create_salary_payouts_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_04_09_094901_create_salary_periods_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_04_09_094902_create_salary_payout_allocations_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_04_09_120214_create_tasks_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_04_09_122244_create_task_checklist_items_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_04_09_122244_create_task_comments_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_04_09_122244_create_task_events_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_04_09_122245_create_task_attachments_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_04_09_164118_create_notifications_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_04_09_165331_create_conversations_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_04_09_171741_add_title_and_created_by_to_conversations_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_04_09_180000_add_recipient_user_id_to_chat_messages_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_04_10_100000_add_workflow_status_to_order_documents_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'create_all_tables_add_owner_id_to_contractors_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_04_10_062741_add_counterparty_id_to_payment_schedules_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_04_13_103755_add_partial_payment_fields_to_payment_schedules_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_04_13_104605_add_payment_status_to_orders_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_04_14_110229_ensure_messenger_schema_is_complete',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_04_14_112803_rebuild_order_documents_as_unified_registry',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_14_172016_add_bank_accounts_and_non_resident_to_contractors_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_14_190000_create_fleet_module_tables',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_16_053137_add_sla_columns_to_tasks_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_04_18_124939_add_invoice_number_to_payment_schedules_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_04_10_120302_create_sales_scripts_module_tables',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_04_10_120648_relax_sales_script_play_sessions_foreign_keys',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_04_20_120000_add_mobile_nav_preferences',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_04_20_165607_create_sales_book_articles_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_04_20_185734_add_canvas_position_to_sales_script_nodes_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_04_30_135148_add_trainer_fields_to_sales_script_play_sessions_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_04_30_135155_create_sales_script_trainer_messages_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_05_02_161556_add_training_role_mode_to_sales_script_play_sessions_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_05_03_090044_create_ati_dictionary_items_and_extend_cargos_for_ati',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_05_03_090135_backfill_ati_cargo_fields_from_legacy_columns',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_05_03_093904_add_ati_transport_requirement_fields_to_cargos',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_05_03_095420_add_ati_transport_requirement_item_lists_to_cargos',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_05_03_131055_add_signer_position_and_contact_decision_maker_fields',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_05_03_140000_add_trainer_assistant_instructions_and_dialog_quality_to_sales_script_play_sessions_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_03_31_100000_ensure_logistics_core_tables_for_fresh_install',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_05_01_120000_add_trainer_ai_role_to_sales_script_play_sessions_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_05_06_120000_create_vat_rates_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_05_06_140000_add_role_id_to_users_when_missing',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_05_10_112158_add_non_resident_correspondent_bank_fields_to_contractors_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_05_10_113009_add_cnaps_code_to_contractors_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_05_10_120000_create_currencies_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_05_10_121156_add_non_resident_corr_settlement_account_to_contractors_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_05_15_100000_add_peer_reaction_to_sales_script_trainer_messages_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_05_16_120000_add_auto_peer_reaction_to_sales_script_trainer_messages_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_06_12_100000_drop_legacy_sites_widgets_and_address_junction_tables',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_06_10_195759_add_tags_to_sales_script_nodes_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_06_10_200147_create_sales_script_editor_extensions_tables',33);
