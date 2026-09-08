/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `event_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` json DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_events_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `activity_events_subject_occurred_idx` (`subject_type`,`subject_id`,`occurred_at`),
  KEY `activity_events_event_type_occurred_at_index` (`event_type`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `ai_interaction_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_interaction_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `feature` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT '1',
  `tool_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt_fingerprint` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_prompt_redacted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assistant_reply_redacted` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tools_used` json DEFAULT NULL,
  `tool_rounds` smallint unsigned DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `tokens_prompt` int unsigned DEFAULT NULL,
  `tokens_completion` int unsigned DEFAULT NULL,
  `error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_interaction_events_feature_event_type_created_at_index` (`feature`,`event_type`,`created_at`),
  KEY `ai_interaction_events_outcome_created_at_index` (`outcome`,`created_at`),
  KEY `ai_interaction_events_prompt_fingerprint_created_at_index` (`prompt_fingerprint`,`created_at`),
  KEY `ai_interaction_events_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `ai_interaction_events_tool_name_created_at_index` (`tool_name`,`created_at`),
  CONSTRAINT `ai_interaction_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `ai_tool_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_tool_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tool` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `arguments` json DEFAULT NULL,
  `ok` tinyint(1) NOT NULL DEFAULT '1',
  `error_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_tool_audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `ai_tool_audit_logs_tool_created_at_index` (`tool`,`created_at`),
  CONSTRAINT `ai_tool_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `budget_opex_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_opex_articles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed_monthly',
  `amount_monthly` decimal(14,2) NOT NULL DEFAULT '0.00',
  `percent_of_margin` decimal(5,2) DEFAULT NULL,
  `ramp_months` tinyint unsigned DEFAULT NULL COMMENT 'Только первые N месяцев; null — каждый месяц',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `management_expense_category_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `budget_opex_articles_management_expense_category_id_foreign` (`management_expense_category_id`),
  CONSTRAINT `budget_opex_articles_management_expense_category_id_foreign` FOREIGN KEY (`management_expense_category_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_plan_snapshot_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_plan_snapshot_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_id` bigint unsigned NOT NULL,
  `month` date NOT NULL,
  `opex_article_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `article_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `planned_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `budget_plan_snapshot_lines_opex_article_id_foreign` (`opex_article_id`),
  KEY `budget_plan_snapshot_lines_category_id_foreign` (`category_id`),
  KEY `budget_plan_snapshot_lines_snapshot_id_month_index` (`snapshot_id`,`month`),
  KEY `budget_plan_snapshot_lines_snapshot_id_category_id_index` (`snapshot_id`,`category_id`),
  CONSTRAINT `budget_plan_snapshot_lines_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_plan_snapshot_lines_opex_article_id_foreign` FOREIGN KEY (`opex_article_id`) REFERENCES `budget_opex_articles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_plan_snapshot_lines_snapshot_id_foreign` FOREIGN KEY (`snapshot_id`) REFERENCES `budget_plan_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_plan_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_plan_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scenario_id` bigint unsigned NOT NULL,
  `period_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `approved_at` timestamp NOT NULL,
  `approved_by_user_id` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `budget_plan_snapshots_scenario_id_foreign` (`scenario_id`),
  KEY `budget_plan_snapshots_approved_by_user_id_foreign` (`approved_by_user_id`),
  KEY `budget_plan_snapshots_period_start_period_end_index` (`period_start`,`period_end`),
  KEY `budget_plan_snapshots_approved_at_index` (`approved_at`),
  CONSTRAINT `budget_plan_snapshots_approved_by_user_id_foreign` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_plan_snapshots_scenario_id_foreign` FOREIGN KEY (`scenario_id`) REFERENCES `budget_scenarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_sales_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_sales_targets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scenario_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `period_month` date NOT NULL,
  `metric` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `planned_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `budget_sales_targets_unique` (`scenario_id`,`user_id`,`period_month`,`metric`),
  KEY `budget_sales_targets_user_id_foreign` (`user_id`),
  KEY `budget_sales_targets_scenario_id_period_month_index` (`scenario_id`,`period_month`),
  CONSTRAINT `budget_sales_targets_scenario_id_foreign` FOREIGN KEY (`scenario_id`) REFERENCES `budget_scenarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_sales_targets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_scenarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_scenarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_scenario_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Основной',
  `plan_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'company',
  `inputs` json NOT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `budget_scenarios_updated_by_user_id_foreign` (`updated_by_user_id`),
  KEY `budget_scenarios_parent_scenario_id_foreign` (`parent_scenario_id`),
  CONSTRAINT `budget_scenarios_parent_scenario_id_foreign` FOREIGN KEY (`parent_scenario_id`) REFERENCES `budget_scenarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budget_scenarios_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_process_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_process_stages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_process_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `stage_goal` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `success_criteria` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sales_script_id` bigint unsigned DEFAULT NULL,
  `sequence` int unsigned NOT NULL DEFAULT '0',
  `duration_days` smallint unsigned NOT NULL DEFAULT '0',
  `is_terminal` tinyint(1) NOT NULL DEFAULT '0',
  `terminal_outcome` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_create_task` tinyint(1) NOT NULL DEFAULT '0',
  `task_title_template` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_description_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `task_due_days_offset` smallint unsigned NOT NULL DEFAULT '0',
  `task_priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `no_reply_nudge_days` smallint unsigned DEFAULT NULL,
  `nudge_triggers` json DEFAULT NULL,
  `ledger_idle_nudge_days` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `automated_actions` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `business_process_stages_business_process_id_sequence_index` (`business_process_id`,`sequence`),
  KEY `business_process_stages_sales_script_id_foreign` (`sales_script_id`),
  CONSTRAINT `business_process_stages_business_process_id_foreign` FOREIGN KEY (`business_process_id`) REFERENCES `business_processes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `business_process_stages_sales_script_id_foreign` FOREIGN KEY (`sales_script_id`) REFERENCES `sales_scripts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_processes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_processes_slug_unique` (`slug`)
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
  `dimension_unit` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'm',
  `length_value` decimal(12,3) DEFAULT NULL,
  `width_value` decimal(12,3) DEFAULT NULL,
  `height_value` decimal(12,3) DEFAULT NULL,
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
DROP TABLE IF EXISTS `chat_message_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_message_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_message_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `disk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint unsigned NOT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_message_attachments_chat_message_id_foreign` (`chat_message_id`),
  KEY `chat_message_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `chat_message_attachments_chat_message_id_foreign` FOREIGN KEY (`chat_message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_message_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_message_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_user_id` bigint unsigned DEFAULT NULL,
  `reply_to_message_id` bigint unsigned DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `message_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_messages_client_id_unique` (`conversation_id`,`user_id`,`client_message_id`),
  KEY `chat_messages_conversation_id_foreign` (`conversation_id`),
  KEY `chat_messages_user_id_foreign` (`user_id`),
  KEY `chat_messages_recipient_user_id_foreign` (`recipient_user_id`),
  KEY `chat_messages_order_id_foreign` (`order_id`),
  KEY `chat_messages_reply_to_message_id_foreign` (`reply_to_message_id`),
  CONSTRAINT `chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_reply_to_message_id_foreign` FOREIGN KEY (`reply_to_message_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL,
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
DROP TABLE IF EXISTS `commercial_ai_suggestion_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commercial_ai_suggestion_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `suggestion_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `suggestion_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_thread_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `rating` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `payload` json DEFAULT NULL,
  `rated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commercial_ai_suggestion_logs_suggestion_key_unique` (`suggestion_key`),
  KEY `commercial_ai_suggestion_logs_user_id_suggestion_type_index` (`user_id`,`suggestion_type`),
  KEY `commercial_ai_suggestion_logs_mail_thread_id_index` (`mail_thread_id`),
  KEY `commercial_ai_suggestion_logs_lead_id_index` (`lead_id`),
  CONSTRAINT `commercial_ai_suggestion_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_initiative_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_initiative_dependencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_initiative_id` bigint unsigned NOT NULL,
  `blocked_milestone_id` bigint unsigned NOT NULL,
  `depends_on_milestone_id` bigint unsigned NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'finish_to_start',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_milestone_dependency_unique` (`blocked_milestone_id`,`depends_on_milestone_id`),
  KEY `company_initiative_dependencies_company_initiative_id_index` (`company_initiative_id`),
  KEY `company_initiative_dependencies_blocked_milestone_id_index` (`blocked_milestone_id`),
  KEY `company_initiative_dependencies_depends_on_milestone_id_index` (`depends_on_milestone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_initiative_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_initiative_milestones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_initiative_id` bigint unsigned NOT NULL,
  `responsible_id` bigint unsigned DEFAULT NULL,
  `task_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `done_criteria` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `completed_on` date DEFAULT NULL,
  `progress_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_initiative_milestones_company_initiative_id_index` (`company_initiative_id`),
  KEY `company_initiative_milestones_responsible_id_index` (`responsible_id`),
  KEY `company_initiative_milestones_task_id_index` (`task_id`),
  KEY `company_initiative_milestones_status_index` (`status`),
  KEY `company_initiative_milestones_priority_index` (`priority`),
  KEY `company_initiative_milestones_starts_on_index` (`starts_on`),
  KEY `company_initiative_milestones_ends_on_index` (`ends_on`),
  KEY `company_initiative_milestones_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_initiatives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_initiatives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `goal` text COLLATE utf8mb4_unicode_ci,
  `expected_result` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `direction` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `owner_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `planned_budget_amount` decimal(15,2) DEFAULT NULL,
  `budget_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `management_expense_category_id` bigint unsigned DEFAULT NULL,
  `budget_notes` text COLLATE utf8mb4_unicode_ci,
  `progress_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `risk_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `risk_summary` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_initiatives_status_index` (`status`),
  KEY `company_initiatives_priority_index` (`priority`),
  KEY `company_initiatives_direction_index` (`direction`),
  KEY `company_initiatives_starts_on_index` (`starts_on`),
  KEY `company_initiatives_ends_on_index` (`ends_on`),
  KEY `company_initiatives_owner_id_index` (`owner_id`),
  KEY `company_initiatives_created_by_index` (`created_by`),
  KEY `company_initiatives_management_expense_category_id_index` (`management_expense_category_id`),
  KEY `company_initiatives_risk_level_index` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `phones` json DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_traklo_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_decision_maker` tinyint(1) NOT NULL DEFAULT '0',
  `role_in_deal` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `communication_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_driver` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `contractor_enrichment_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_enrichment_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sources_json` json DEFAULT NULL,
  `dossier_json` json DEFAULT NULL,
  `proposed_drafts_json` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_enrichment_runs_created_by_foreign` (`created_by`),
  KEY `contractor_enrichment_runs_contractor_id_status_index` (`contractor_id`,`status`),
  KEY `contractor_enrichment_runs_contractor_id_finished_at_index` (`contractor_id`,`finished_at`),
  CONSTRAINT `contractor_enrichment_runs_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_enrichment_runs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_insight_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_insight_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `field_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposed_value` json NOT NULL,
  `source_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `source_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_insight_drafts_reviewed_by_foreign` (`reviewed_by`),
  KEY `contractor_insight_drafts_contractor_id_status_index` (`contractor_id`,`status`),
  KEY `contractor_insight_drafts_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `contractor_insight_drafts_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_insight_drafts_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_interactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `contractor_contact_id` bigint unsigned DEFAULT NULL,
  `contacted_at` timestamp NULL DEFAULT NULL,
  `next_contact_at` timestamp NULL DEFAULT NULL,
  `channel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objection_tags` json DEFAULT NULL,
  `merge_to_portrait` tinyint(1) NOT NULL DEFAULT '0',
  `mail_message_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_interactions_contractor_id_foreign` (`contractor_id`),
  KEY `contractor_interactions_created_by_foreign` (`created_by`),
  KEY `contractor_interactions_contractor_contact_id_foreign` (`contractor_contact_id`),
  KEY `contractor_interactions_mail_message_id_foreign` (`mail_message_id`),
  CONSTRAINT `contractor_interactions_contractor_contact_id_foreign` FOREIGN KEY (`contractor_contact_id`) REFERENCES `contractor_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractor_interactions_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_interactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractor_interactions_mail_message_id_foreign` FOREIGN KEY (`mail_message_id`) REFERENCES `mail_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_portraits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_portraits` (
  `contractor_id` bigint unsigned NOT NULL,
  `communication_style` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `price_sensitivity` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `preferred_channel` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `decision_cadence` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `relationship_trust` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `success_criteria` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `typical_objections` json DEFAULT NULL,
  `internal_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `coverage_pct` tinyint unsigned NOT NULL DEFAULT '0',
  `portrait_updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`contractor_id`),
  KEY `contractor_portraits_updated_by_foreign` (`updated_by`),
  CONSTRAINT `contractor_portraits_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_portraits_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_print_form_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_print_form_change_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `party` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'basic_terms',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_approval',
  `payload` json DEFAULT NULL,
  `manager_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `yurik_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `task_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_print_form_change_requests_submitted_by_foreign` (`submitted_by`),
  KEY `contractor_print_form_change_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `contractor_print_form_change_requests_contractor_id_status_index` (`contractor_id`,`status`),
  KEY `contractor_print_form_change_requests_party_status_index` (`party`,`status`),
  CONSTRAINT `contractor_print_form_change_requests_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_print_form_change_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractor_print_form_change_requests_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_risk_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_risk_assessments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `contractor_risk_snapshot_id` bigint unsigned DEFAULT NULL,
  `model_version` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `outcome` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draft_score` tinyint unsigned DEFAULT NULL,
  `draft_grade` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draft_tier` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draft_recommended_debt_limit_rub` int unsigned DEFAULT NULL,
  `draft_recommended_postpayment_days` tinyint unsigned DEFAULT NULL,
  `applied_debt_limit_rub` decimal(14,2) DEFAULT NULL,
  `applied_postpayment_days` tinyint unsigned DEFAULT NULL,
  `applied_schedule_target` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edit_delta` json DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submission_reason` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_risk_assessments_contractor_risk_snapshot_id_foreign` (`contractor_risk_snapshot_id`),
  KEY `contractor_risk_assessments_approved_by_foreign` (`approved_by`),
  KEY `cra_contractor_status_idx` (`contractor_id`,`status`,`created_at`),
  KEY `contractor_risk_assessments_submitted_by_foreign` (`submitted_by`),
  CONSTRAINT `contractor_risk_assessments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractor_risk_assessments_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contractor_risk_assessments_contractor_risk_snapshot_id_foreign` FOREIGN KEY (`contractor_risk_snapshot_id`) REFERENCES `contractor_risk_snapshots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contractor_risk_assessments_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractor_risk_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractor_risk_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_id` bigint unsigned NOT NULL,
  `inn` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_version` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_data` json NOT NULL,
  `scoring_result` json NOT NULL,
  `checko_from_cache` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contractor_risk_snapshots_contractor_id_created_at_index` (`contractor_id`,`created_at`),
  CONSTRAINT `contractor_risk_snapshots_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contractors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('customer','carrier','contractor','both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `inn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kpp` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ogrn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `okpo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_form` enum('ooo','zao','ao','ip','samozanyaty','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actual_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_address_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actual_address_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_address_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_sync_domains` json DEFAULT NULL,
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `default_customer_norms_penalties` json DEFAULT NULL,
  `default_carrier_payment_form` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_carrier_payment_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_carrier_payment_schedule` json DEFAULT NULL,
  `default_carrier_norms_penalties` json DEFAULT NULL,
  `cooperation_terms_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `work_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_development',
  `work_pause_is_automatic` tinyint(1) NOT NULL DEFAULT '0',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `is_own_company` tinyint(1) NOT NULL DEFAULT '0',
  `is_non_resident` tinyint(1) NOT NULL DEFAULT '0',
  `has_english_requisites` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `owner_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `signer_name_nominative` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_name_prepositional` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_authority_basis` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edo_provider` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edo_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_name_nominative_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_name_prepositional_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_position_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_authority_basis_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_bank_swift` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_bank_account` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnaps_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `non_resident_corr_settlement_account` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
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
  `channel` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `contractor_id` bigint unsigned DEFAULT NULL,
  `external_party` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_staff_user_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `posting_policy` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'members',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_created_by_foreign` (`created_by`),
  KEY `conversations_contractor_id_foreign` (`contractor_id`),
  KEY `conversations_primary_staff_user_id_foreign` (`primary_staff_user_id`),
  KEY `conversations_counterparty_thread_idx` (`channel`,`contractor_id`,`external_party`,`primary_staff_user_id`),
  CONSTRAINT `conversations_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_primary_staff_user_id_foreign` FOREIGN KEY (`primary_staff_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currencies_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `department_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `department_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `receives_approvals` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_user_user_id_department_id_unique` (`user_id`,`department_id`),
  KEY `dept_user_dept_approvals_idx` (`department_id`,`receives_approvals`),
  CONSTRAINT `department_user_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `department_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `disposition_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disposition_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `slot` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disposition_entries_order_id_date_slot_unique` (`order_id`,`date`,`slot`),
  KEY `disposition_entries_recorded_by_foreign` (`recorded_by`),
  KEY `disposition_entries_date_slot_index` (`date`,`slot`),
  CONSTRAINT `disposition_entries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disposition_entries_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
DROP TABLE IF EXISTS `external_user_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `external_user_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contractor_contact_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned NOT NULL,
  `external_party` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `external_user_invites_contractor_contact_id_foreign` (`contractor_contact_id`),
  KEY `external_user_invites_contractor_id_foreign` (`contractor_id`),
  KEY `external_user_invites_created_by_foreign` (`created_by`),
  KEY `external_user_invites_user_id_foreign` (`user_id`),
  KEY `eui_token_revoked_idx` (`token_hash`,`revoked_at`),
  KEY `eui_expires_at_idx` (`expires_at`),
  CONSTRAINT `external_user_invites_contractor_contact_id_foreign` FOREIGN KEY (`contractor_contact_id`) REFERENCES `contractor_contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `external_user_invites_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `external_user_invites_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `external_user_invites_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
  `client_payment_terms` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
DROP TABLE IF EXISTS `fleet_container_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_container_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fleet_container_id` bigint unsigned NOT NULL,
  `document_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_container_documents_fleet_container_id_foreign` (`fleet_container_id`),
  KEY `fleet_container_documents_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `fleet_container_documents_fleet_container_id_foreign` FOREIGN KEY (`fleet_container_id`) REFERENCES `fleet_containers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fleet_container_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_containers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_containers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_contractor_id` bigint unsigned NOT NULL,
  `container_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_code` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `container_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fleet_containers_owner_contractor_id_container_number_unique` (`owner_contractor_id`,`container_number`),
  KEY `fleet_containers_container_number_index` (`container_number`),
  CONSTRAINT `fleet_containers_owner_contractor_id_foreign` FOREIGN KEY (`owner_contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `fleet_trip_cost_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_trip_cost_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fleet_trip_id` bigint unsigned NOT NULL,
  `cost_category` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fleet_trip_cost_lines_fleet_trip_id_foreign` (`fleet_trip_id`),
  KEY `fleet_trip_cost_lines_cost_category_index` (`cost_category`),
  CONSTRAINT `fleet_trip_cost_lines_fleet_trip_id_foreign` FOREIGN KEY (`fleet_trip_id`) REFERENCES `fleet_trips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fleet_trips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fleet_trips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `order_leg_stage` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_slot` tinyint unsigned DEFAULT NULL,
  `fleet_vehicle_id` bigint unsigned DEFAULT NULL,
  `fleet_driver_id` bigint unsigned DEFAULT NULL,
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `estimated_cost` decimal(14,2) DEFAULT NULL,
  `total_cost` decimal(14,2) DEFAULT NULL,
  `planned_km` int unsigned DEFAULT NULL,
  `actual_km` int unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fleet_trips_order_leg_slot_unique` (`order_id`,`order_leg_stage`,`carrier_slot`),
  KEY `fleet_trips_fleet_vehicle_id_foreign` (`fleet_vehicle_id`),
  KEY `fleet_trips_fleet_driver_id_foreign` (`fleet_driver_id`),
  KEY `fleet_trips_status_index` (`status`),
  CONSTRAINT `fleet_trips_fleet_driver_id_foreign` FOREIGN KEY (`fleet_driver_id`) REFERENCES `fleet_drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fleet_trips_fleet_vehicle_id_foreign` FOREIGN KEY (`fleet_vehicle_id`) REFERENCES `fleet_vehicles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fleet_trips_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `grid_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grid_views` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `grid_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_user_id` bigint unsigned NOT NULL,
  `visibility` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'private',
  `shared_with` json DEFAULT NULL,
  `column_state` json DEFAULT NULL,
  `filter_state` json DEFAULT NULL,
  `sort_state` json DEFAULT NULL,
  `quick_search` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_pinned_sidebar` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grid_views_owner_user_id_foreign` (`owner_user_id`),
  KEY `grid_views_grid_key_owner_user_id_index` (`grid_key`,`owner_user_id`),
  KEY `grid_views_is_pinned_sidebar_sort_order_index` (`is_pinned_sidebar`,`sort_order`),
  CONSTRAINT `grid_views_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_cost_pp1291_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_cost_pp1291_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_fee_rub` int unsigned NOT NULL DEFAULT '150000',
  `age_coefficients` json NOT NULL,
  `decree_reference` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ПП РФ № 1291',
  `effective_from` date DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `import_cost_pp1291_categories_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_cost_reference_syncs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_cost_reference_syncs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `items_updated` int unsigned NOT NULL DEFAULT '0',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `synced_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_cost_reference_syncs_source_synced_at_index` (`source`,`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_cost_tn_ved_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_cost_tn_ved_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_display` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `duty_percent` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `vat_percent` decimal(8,4) NOT NULL DEFAULT '22.0000',
  `pp1291_category_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_utilization_fee` tinyint(1) NOT NULL DEFAULT '1',
  `duty_source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'config',
  `eec_payload` json DEFAULT NULL,
  `eec_synced_at` timestamp NULL DEFAULT NULL,
  `kodtnved_payload` json DEFAULT NULL,
  `kodtnved_synced_at` timestamp NULL DEFAULT NULL,
  `alta_payload` json DEFAULT NULL,
  `alta_synced_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `import_cost_tn_ved_entries_code_unique` (`code`),
  KEY `import_cost_tn_ved_entries_pp1291_category_key_index` (`pp1291_category_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `improvement_adoptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `improvement_adoptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `experiment_id` bigint unsigned NOT NULL,
  `hypothesis_id` bigint unsigned NOT NULL,
  `target_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint unsigned DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` json DEFAULT NULL,
  `adopted_by` bigint unsigned NOT NULL,
  `adopted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `improvement_adoptions_experiment_id_foreign` (`experiment_id`),
  KEY `improvement_adoptions_hypothesis_id_foreign` (`hypothesis_id`),
  KEY `improvement_adoptions_adopted_by_foreign` (`adopted_by`),
  CONSTRAINT `improvement_adoptions_adopted_by_foreign` FOREIGN KEY (`adopted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `improvement_adoptions_experiment_id_foreign` FOREIGN KEY (`experiment_id`) REFERENCES `improvement_experiments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `improvement_adoptions_hypothesis_id_foreign` FOREIGN KEY (`hypothesis_id`) REFERENCES `improvement_hypotheses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `improvement_experiment_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `improvement_experiment_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `experiment_id` bigint unsigned NOT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `variant` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `outcome` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_at` timestamp NOT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `improvement_experiment_assignments_experiment_id_lead_id_unique` (`experiment_id`,`lead_id`),
  KEY `improvement_experiment_assignments_lead_id_foreign` (`lead_id`),
  CONSTRAINT `improvement_experiment_assignments_experiment_id_foreign` FOREIGN KEY (`experiment_id`) REFERENCES `improvement_experiments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `improvement_experiment_assignments_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `improvement_experiments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `improvement_experiments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hypothesis_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `variant_a` json NOT NULL,
  `variant_b` json NOT NULL,
  `metric_key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'win_rate',
  `assignment_mode` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'managers',
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `cohort` json DEFAULT NULL,
  `result_snapshot` json DEFAULT NULL,
  `verdict` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verdict_note` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `improvement_experiments_hypothesis_id_foreign` (`hypothesis_id`),
  KEY `improvement_experiments_created_by_foreign` (`created_by`),
  KEY `improvement_experiments_decided_by_foreign` (`decided_by`),
  KEY `improvement_experiments_status_starts_on_index` (`status`,`starts_on`),
  CONSTRAINT `improvement_experiments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `improvement_experiments_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `improvement_experiments_hypothesis_id_foreign` FOREIGN KEY (`hypothesis_id`) REFERENCES `improvement_hypotheses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `improvement_hypotheses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `improvement_hypotheses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `signal_id` bigint unsigned DEFAULT NULL,
  `pipeline_run_id` bigint unsigned DEFAULT NULL,
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_reason` text COLLATE utf8mb4_unicode_ci,
  `impact` tinyint unsigned DEFAULT NULL,
  `confidence` tinyint unsigned DEFAULT NULL,
  `ease` tinyint unsigned DEFAULT NULL,
  `score` decimal(8,2) DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'llm_pipeline',
  `fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `improvement_hypotheses_signal_id_foreign` (`signal_id`),
  KEY `improvement_hypotheses_pipeline_run_id_foreign` (`pipeline_run_id`),
  KEY `improvement_hypotheses_created_by_foreign` (`created_by`),
  KEY `improvement_hypotheses_reviewed_by_foreign` (`reviewed_by`),
  KEY `improvement_hypotheses_status_score_index` (`status`,`score`),
  KEY `improvement_hypotheses_fingerprint_index` (`fingerprint`),
  CONSTRAINT `improvement_hypotheses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `improvement_hypotheses_pipeline_run_id_foreign` FOREIGN KEY (`pipeline_run_id`) REFERENCES `improvement_pipeline_runs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `improvement_hypotheses_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `improvement_hypotheses_signal_id_foreign` FOREIGN KEY (`signal_id`) REFERENCES `improvement_signals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `improvement_pipeline_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `improvement_pipeline_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `signals_used` int unsigned NOT NULL DEFAULT '0',
  `hypotheses_created` int unsigned NOT NULL DEFAULT '0',
  `duration_ms` int unsigned DEFAULT NULL,
  `error_summary` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `improvement_signals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `improvement_signals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sales',
  `kind` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rules',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `improvement_signals_domain_status_index` (`domain`,`status`),
  KEY `improvement_signals_kind_created_at_index` (`kind`,`created_at`)
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
DROP TABLE IF EXISTS `kpi_deduction_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kpi_deduction_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT '100',
  `customer_payment_form` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_positive_vat_required` tinyint(1) NOT NULL DEFAULT '0',
  `customer_vat_rate_percent` decimal(5,2) DEFAULT NULL,
  `carrier_rule` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_payment_forms` json DEFAULT NULL,
  `carrier_vat_rate_percent` decimal(5,2) DEFAULT NULL,
  `deduction_primary_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
  `deduction_secondary_percent` decimal(6,2) DEFAULT NULL,
  `margin_supplement_percent` decimal(6,2) DEFAULT NULL,
  `margin_supplement_carrier_vat_percent` decimal(5,2) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kpi_deduction_rules_active_period_index` (`is_active`,`effective_from`,`effective_to`),
  KEY `kpi_deduction_rules_priority_index` (`priority`)
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
DROP TABLE IF EXISTS `lead_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `disk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_attachments_lead_id_index` (`lead_id`),
  KEY `lead_attachments_user_id_index` (`user_id`)
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
  `metadata` json DEFAULT NULL,
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
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offer_date` date DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `payload` json DEFAULT NULL,
  `generated_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `last_mail_thread_id` bigint unsigned DEFAULT NULL,
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
DROP TABLE IF EXISTS `lead_process_stage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_process_stage_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `business_process_stage_id` bigint unsigned NOT NULL,
  `entered_at` timestamp NOT NULL,
  `exited_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_process_stage_logs_business_process_stage_id_foreign` (`business_process_stage_id`),
  KEY `lead_process_stage_logs_lead_id_entered_at_index` (`lead_id`,`entered_at`),
  CONSTRAINT `lead_process_stage_logs_business_process_stage_id_foreign` FOREIGN KEY (`business_process_stage_id`) REFERENCES `business_process_stages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_process_stage_logs_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_rate_quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_rate_quotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `load_board_offer_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `carrier_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate` decimal(14,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `payment_form` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `comment` text COLLATE utf8mb4_unicode_ci,
  `selected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_rate_quotes_contractor_id_foreign` (`contractor_id`),
  KEY `lead_rate_quotes_load_board_offer_id_foreign` (`load_board_offer_id`),
  KEY `lead_rate_quotes_created_by_foreign` (`created_by`),
  KEY `lead_rate_quotes_lead_id_status_index` (`lead_id`,`status`),
  KEY `lead_rate_quotes_source_index` (`source`),
  KEY `lead_rate_quotes_status_index` (`status`),
  CONSTRAINT `lead_rate_quotes_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_rate_quotes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_rate_quotes_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_rate_quotes_load_board_offer_id_foreign` FOREIGN KEY (`load_board_offer_id`) REFERENCES `load_board_offers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_route_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_route_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stage` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'leg_1',
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
  `business_process_id` bigint unsigned DEFAULT NULL,
  `business_process_stage_id` bigint unsigned DEFAULT NULL,
  `process_started_at` timestamp NULL DEFAULT NULL,
  `stage_entered_at` timestamp NULL DEFAULT NULL,
  `stage_due_at` timestamp NULL DEFAULT NULL,
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
  `customer_payment_form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_payment_form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculated_cost` decimal(12,2) DEFAULT NULL,
  `expected_margin` decimal(12,2) DEFAULT NULL,
  `proposal_sent_at` timestamp NULL DEFAULT NULL,
  `next_contact_at` timestamp NULL DEFAULT NULL,
  `lost_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `close_outcome_primary_flag` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `close_outcome_secondary_flags` json DEFAULT NULL,
  `lead_qualification` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `performers` json DEFAULT NULL,
  `precalculation` json DEFAULT NULL,
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
  KEY `leads_business_process_id_foreign` (`business_process_id`),
  KEY `leads_business_process_stage_id_foreign` (`business_process_stage_id`),
  CONSTRAINT `leads_business_process_id_foreign` FOREIGN KEY (`business_process_id`) REFERENCES `business_processes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_business_process_stage_id_foreign` FOREIGN KEY (`business_process_stage_id`) REFERENCES `business_process_stages` (`id`) ON DELETE SET NULL,
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
  `carrier_slot` tinyint unsigned NOT NULL DEFAULT '1',
  `contractor_id` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` bigint unsigned NOT NULL,
  `status` enum('pending','confirmed','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leg_contractor_assignments_order_leg_id_carrier_slot_unique` (`order_leg_id`,`carrier_slot`),
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
DROP TABLE IF EXISTS `load_board_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `load_board_offers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `load_board_post_id` bigint unsigned NOT NULL,
  `carrier_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal_crm',
  `carrier_rate` decimal(14,2) NOT NULL,
  `carrier_rate_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `payment_form` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_date` date DEFAULT NULL,
  `carrier_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conditions` text COLLATE utf8mb4_unicode_ci,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `selected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `load_board_offers_carrier_id_foreign` (`carrier_id`),
  KEY `load_board_offers_load_board_post_id_status_index` (`load_board_post_id`,`status`),
  KEY `load_board_offers_created_by_status_index` (`created_by`,`status`),
  KEY `load_board_offers_status_index` (`status`),
  CONSTRAINT `load_board_offers_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_offers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `load_board_offers_load_board_post_id_foreign` FOREIGN KEY (`load_board_post_id`) REFERENCES `load_board_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `load_board_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `load_board_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `seller_id` bigint unsigned NOT NULL,
  `buyer_id` bigint unsigned DEFAULT NULL,
  `accepted_offer_id` bigint unsigned DEFAULT NULL,
  `accepted_by` bigint unsigned DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `priority` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `loading_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unloading_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_date` date DEFAULT NULL,
  `unloading_date` date DEFAULT NULL,
  `cargo_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ati_cargo_name` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_weight` decimal(10,2) DEFAULT NULL,
  `cargo_volume` decimal(10,2) DEFAULT NULL,
  `cargo_type_id` int unsigned DEFAULT NULL,
  `cargo_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_type_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pack_type_id` int unsigned DEFAULT NULL,
  `package_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pack_type_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_count` int unsigned DEFAULT NULL,
  `loading_type_id` int unsigned DEFAULT NULL,
  `loading_type_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_type_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_type_items` json DEFAULT NULL,
  `truck_body_type_id` int unsigned DEFAULT NULL,
  `truck_body_type_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `truck_body_type_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `truck_body_type_items` json DEFAULT NULL,
  `trailer_type_id` int unsigned DEFAULT NULL,
  `trailer_type_code` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_type_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_type_items` json DEFAULT NULL,
  `length` decimal(10,2) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `diameter` decimal(10,2) DEFAULT NULL,
  `is_hazardous` tinyint(1) NOT NULL DEFAULT '0',
  `hazard_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `needs_temperature` tinyint(1) NOT NULL DEFAULT '0',
  `temp_min` decimal(6,2) DEFAULT NULL,
  `temp_max` decimal(6,2) DEFAULT NULL,
  `is_oversized` tinyint(1) NOT NULL DEFAULT '0',
  `is_fragile` tinyint(1) NOT NULL DEFAULT '0',
  `hs_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ati_cargo_payload` json DEFAULT NULL,
  `transport_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_rate` decimal(14,2) DEFAULT NULL,
  `customer_rate_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `target_carrier_rate` decimal(14,2) DEFAULT NULL,
  `payment_form` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requirements` text COLLATE utf8mb4_unicode_ci,
  `seller_comment` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `taken_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `load_board_posts_lead_id_foreign` (`lead_id`),
  KEY `load_board_posts_order_id_foreign` (`order_id`),
  KEY `load_board_posts_customer_id_foreign` (`customer_id`),
  KEY `load_board_posts_seller_id_status_index` (`seller_id`,`status`),
  KEY `load_board_posts_buyer_id_status_index` (`buyer_id`,`status`),
  KEY `load_board_posts_loading_date_priority_index` (`loading_date`,`priority`),
  KEY `load_board_posts_status_index` (`status`),
  KEY `load_board_posts_priority_index` (`priority`),
  KEY `load_board_posts_accepted_offer_id_foreign` (`accepted_offer_id`),
  KEY `load_board_posts_accepted_by_foreign` (`accepted_by`),
  CONSTRAINT `load_board_posts_accepted_by_foreign` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_posts_accepted_offer_id_foreign` FOREIGN KEY (`accepted_offer_id`) REFERENCES `load_board_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_posts_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_posts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_posts_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_posts_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_posts_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `load_board_rate_observations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `load_board_rate_observations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `load_board_post_id` bigint unsigned NOT NULL,
  `load_board_offer_id` bigint unsigned DEFAULT NULL,
  `carrier_id` bigint unsigned DEFAULT NULL,
  `corridor_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loading_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unloading_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `truck_body_type_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_weight` decimal(10,2) DEFAULT NULL,
  `customer_rate` decimal(14,2) DEFAULT NULL,
  `customer_rate_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `carrier_rate` decimal(14,2) NOT NULL,
  `carrier_rate_currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `margin_abs` decimal(14,2) DEFAULT NULL,
  `margin_pct` decimal(8,2) DEFAULT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal_crm',
  `outcome` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `observed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `load_board_rate_observations_load_board_post_id_foreign` (`load_board_post_id`),
  KEY `load_board_rate_observations_load_board_offer_id_foreign` (`load_board_offer_id`),
  KEY `load_board_rate_observations_carrier_id_foreign` (`carrier_id`),
  KEY `load_board_rate_obs_corridor_outcome_idx` (`corridor_key`,`outcome`,`observed_at`),
  KEY `load_board_rate_observations_corridor_key_index` (`corridor_key`),
  KEY `load_board_rate_observations_source_index` (`source`),
  KEY `load_board_rate_observations_outcome_index` (`outcome`),
  CONSTRAINT `load_board_rate_observations_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_rate_observations_load_board_offer_id_foreign` FOREIGN KEY (`load_board_offer_id`) REFERENCES `load_board_offers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `load_board_rate_observations_load_board_post_id_foreign` FOREIGN KEY (`load_board_post_id`) REFERENCES `load_board_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `loading_cargo_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loading_cargo_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loading_planner_project_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Грузовая группа #1',
  `recipient_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#60a5fa',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loading_cargo_groups_loading_planner_project_id_sort_order_index` (`loading_planner_project_id`,`sort_order`),
  CONSTRAINT `loading_cargo_groups_loading_planner_project_id_foreign` FOREIGN KEY (`loading_planner_project_id`) REFERENCES `loading_planner_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `loading_cargo_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loading_cargo_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loading_cargo_group_id` bigint unsigned NOT NULL,
  `client_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'box',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `length_mm` int unsigned NOT NULL DEFAULT '1200',
  `width_mm` int unsigned NOT NULL DEFAULT '800',
  `height_mm` int unsigned NOT NULL DEFAULT '1000',
  `weight_kg` decimal(10,2) NOT NULL DEFAULT '0.00',
  `can_rotate` tinyint(1) NOT NULL DEFAULT '1',
  `stackable` tinyint(1) NOT NULL DEFAULT '0',
  `max_stack` tinyint unsigned NOT NULL DEFAULT '1',
  `can_tilt` tinyint(1) NOT NULL DEFAULT '0',
  `allow_oversize` tinyint(1) NOT NULL DEFAULT '0',
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#93c5fd',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loading_cargo_items_loading_cargo_group_id_sort_order_index` (`loading_cargo_group_id`,`sort_order`),
  KEY `loading_cargo_items_client_key_index` (`client_key`),
  CONSTRAINT `loading_cargo_items_loading_cargo_group_id_foreign` FOREIGN KEY (`loading_cargo_group_id`) REFERENCES `loading_cargo_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `loading_planner_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loading_planner_projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `selected_transport_template_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `calculation` json DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loading_planner_projects_user_id_updated_at_index` (`user_id`,`updated_at`),
  KEY `loading_planner_projects_lead_id_index` (`lead_id`),
  KEY `loading_planner_projects_order_id_index` (`order_id`),
  CONSTRAINT `loading_planner_projects_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loading_planner_projects_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loading_planner_projects_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_blocked_senders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_blocked_senders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(320) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail_blocked_senders_email_unique` (`email`),
  KEY `mail_blocked_senders_created_by_foreign` (`created_by`),
  CONSTRAINT `mail_blocked_senders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mail_thread_id` bigint unsigned NOT NULL,
  `direction` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `internet_message_id` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_emails` json NOT NULL,
  `cc_emails` json DEFAULT NULL,
  `subject` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body_html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachments` json DEFAULT NULL,
  `is_important` tinyint(1) NOT NULL DEFAULT '0',
  `retention_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content_purged_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `lead_offer_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `mailbox_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail_messages_internet_message_id_unique` (`internet_message_id`),
  KEY `mail_messages_mail_thread_id_sent_at_index` (`mail_thread_id`,`sent_at`),
  KEY `mail_messages_is_important_sent_at_index` (`is_important`,`sent_at`),
  KEY `mail_messages_content_purged_at_index` (`content_purged_at`),
  KEY `mail_messages_mailbox_user_id_idx` (`mailbox_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subject` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `lead_offer_id` bigint unsigned DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_outbound_at` timestamp NULL DEFAULT NULL,
  `last_inbound_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `mailbox_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mail_threads_lead_id_last_message_at_index` (`lead_id`,`last_message_at`),
  KEY `mail_threads_order_id_last_message_at_index` (`order_id`,`last_message_at`),
  KEY `mail_threads_last_outbound_at_index` (`last_outbound_at`),
  KEY `mail_threads_mailbox_user_id_idx` (`mailbox_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_bank_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_mask` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `management_bank_accounts_account_number_unique` (`account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_expense_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `flow` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'out',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `include_in_budget` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `management_expense_categories_code_unique` (`code`),
  KEY `mgmt_exp_cat_parent_fk` (`parent_id`),
  CONSTRAINT `mgmt_exp_cat_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_payroll_half_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_payroll_half_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_half_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `accrued_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `management_payroll_half_users_payroll_half_id_user_id_unique` (`payroll_half_id`,`user_id`),
  KEY `management_payroll_half_users_user_id_foreign` (`user_id`),
  CONSTRAINT `management_payroll_half_users_payroll_half_id_foreign` FOREIGN KEY (`payroll_half_id`) REFERENCES `management_payroll_halves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `management_payroll_half_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_payroll_halves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_payroll_halves` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `half` tinyint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `payment_date` date NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `management_payroll_halves_year_month_half_unique` (`year`,`month`,`half`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_reconcile_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_reconcile_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned DEFAULT NULL,
  `keyword` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allocation_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `order_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_schedule_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT '100',
  `times_applied` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `management_reconcile_rules_created_by_foreign` (`created_by`),
  KEY `management_reconcile_rules_category_id_foreign` (`category_id`),
  KEY `management_reconcile_rules_user_id_foreign` (`user_id`),
  KEY `management_reconcile_rules_payment_schedule_id_foreign` (`payment_schedule_id`),
  KEY `management_reconcile_rules_is_active_priority_index` (`is_active`,`priority`),
  CONSTRAINT `management_reconcile_rules_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `management_reconcile_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `management_reconcile_rules_payment_schedule_id_foreign` FOREIGN KEY (`payment_schedule_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `management_reconcile_rules_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_statement_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_statement_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bank_account_id` bigint unsigned NOT NULL,
  `format` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sber_registry_v1',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `imported_by` bigint unsigned NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `lines_count` int unsigned NOT NULL DEFAULT '0',
  `lines_allocated` int unsigned NOT NULL DEFAULT '0',
  `total_in` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_out` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `management_statement_imports_bank_account_id_foreign` (`bank_account_id`),
  KEY `management_statement_imports_imported_by_foreign` (`imported_by`),
  CONSTRAINT `management_statement_imports_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `management_bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `management_statement_imports_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_statement_line_splits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_statement_line_splits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `management_statement_line_id` bigint unsigned NOT NULL,
  `allocation_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_schedule_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mgmt_stmt_line_split_sched_fk` (`payment_schedule_id`),
  KEY `mgmt_stmt_line_split_order_fk` (`order_id`),
  KEY `mgmt_stmt_line_split_cat_fk` (`category_id`),
  KEY `mgmt_stmt_line_split_user_fk` (`user_id`),
  KEY `mgmt_stmt_line_split_line_type_idx` (`management_statement_line_id`,`allocation_type`),
  CONSTRAINT `mgmt_stmt_line_split_cat_fk` FOREIGN KEY (`category_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_split_line_fk` FOREIGN KEY (`management_statement_line_id`) REFERENCES `management_statement_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mgmt_stmt_line_split_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_split_sched_fk` FOREIGN KEY (`payment_schedule_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_split_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_statement_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_statement_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_id` bigint unsigned DEFAULT NULL,
  `bank_account_id` bigint unsigned NOT NULL,
  `line_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_number` int unsigned DEFAULT NULL,
  `operation_date` date NOT NULL,
  `direction` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `exchange_rate` decimal(12,6) DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'import',
  `match_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_confidence` tinyint unsigned NOT NULL DEFAULT '0',
  `match_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggested_order_id` bigint unsigned DEFAULT NULL,
  `suggested_payment_schedule_id` bigint unsigned DEFAULT NULL,
  `suggested_category_id` bigint unsigned DEFAULT NULL,
  `suggested_user_id` bigint unsigned DEFAULT NULL,
  `allocation_category_id` bigint unsigned DEFAULT NULL,
  `allocation_order_id` bigint unsigned DEFAULT NULL,
  `allocation_payment_schedule_id` bigint unsigned DEFAULT NULL,
  `allocation_user_id` bigint unsigned DEFAULT NULL,
  `allocation_amount` decimal(14,2) DEFAULT NULL,
  `allocated_by` bigint unsigned DEFAULT NULL,
  `allocated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `management_statement_lines_bank_account_id_line_hash_unique` (`bank_account_id`,`line_hash`),
  KEY `mgmt_stmt_line_import_fk` (`import_id`),
  KEY `mgmt_stmt_line_sugg_order_fk` (`suggested_order_id`),
  KEY `mgmt_stmt_line_sugg_pay_sched_fk` (`suggested_payment_schedule_id`),
  KEY `mgmt_stmt_line_sugg_cat_fk` (`suggested_category_id`),
  KEY `mgmt_stmt_line_sugg_user_fk` (`suggested_user_id`),
  KEY `mgmt_stmt_line_alloc_cat_fk` (`allocation_category_id`),
  KEY `mgmt_stmt_line_alloc_order_fk` (`allocation_order_id`),
  KEY `mgmt_stmt_line_alloc_pay_sched_fk` (`allocation_payment_schedule_id`),
  KEY `mgmt_stmt_line_alloc_user_fk` (`allocation_user_id`),
  KEY `mgmt_stmt_line_allocated_by_fk` (`allocated_by`),
  KEY `mgmt_stmt_line_created_by_fk` (`created_by`),
  CONSTRAINT `mgmt_stmt_line_alloc_cat_fk` FOREIGN KEY (`allocation_category_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_alloc_order_fk` FOREIGN KEY (`allocation_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_alloc_pay_sched_fk` FOREIGN KEY (`allocation_payment_schedule_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_alloc_user_fk` FOREIGN KEY (`allocation_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_allocated_by_fk` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_bank_acct_fk` FOREIGN KEY (`bank_account_id`) REFERENCES `management_bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mgmt_stmt_line_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_import_fk` FOREIGN KEY (`import_id`) REFERENCES `management_statement_imports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_sugg_cat_fk` FOREIGN KEY (`suggested_category_id`) REFERENCES `management_expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_sugg_order_fk` FOREIGN KEY (`suggested_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_sugg_pay_sched_fk` FOREIGN KEY (`suggested_payment_schedule_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mgmt_stmt_line_sugg_user_fk` FOREIGN KEY (`suggested_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mcp_data_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mcp_data_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bidirectional` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mcp_data_links_pair_unique` (`source_key`,`target_key`),
  KEY `mcp_data_links_source_key_is_active_index` (`source_key`,`is_active`),
  KEY `mcp_data_links_target_key_is_active_index` (`target_key`,`is_active`)
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
DROP TABLE IF EXISTS `one_c_epd_registry_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `one_c_epd_registry_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `publication_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_ref` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_ref_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_ref_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type_label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `epd_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `epd_date` date DEFAULT NULL,
  `ib_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ib_date` datetime DEFAULT NULL,
  `current_step` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_step_done` tinyint(1) NOT NULL DEFAULT '0',
  `participant_role` tinyint unsigned DEFAULT NULL,
  `organization_ref` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipper_ref` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipper_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipper_inn` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignee_ref` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignee_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignee_inn` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_ref` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_inn` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posted` tinyint(1) NOT NULL DEFAULT '0',
  `deletion_mark` tinyint(1) NOT NULL DEFAULT '0',
  `order_id` bigint unsigned DEFAULT NULL,
  `linked_by` bigint unsigned DEFAULT NULL,
  `linked_at` timestamp NULL DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `document_meta` json DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `one_c_epd_registry_pub_doc_unique` (`publication_code`,`document_ref`),
  KEY `one_c_epd_registry_entries_linked_by_foreign` (`linked_by`),
  KEY `one_c_epd_registry_entries_document_type_epd_date_index` (`document_type`,`epd_date`),
  KEY `one_c_epd_registry_entries_order_id_index` (`order_id`),
  KEY `one_c_epd_registry_entries_shipper_inn_index` (`shipper_inn`),
  KEY `one_c_epd_registry_entries_current_step_index` (`current_step`),
  CONSTRAINT `one_c_epd_registry_entries_linked_by_foreign` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `one_c_epd_registry_entries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `amount_risk` decimal(14,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RUB',
  `responsible_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_claims_number_unique` (`number`),
  KEY `order_claims_contractor_id_foreign` (`contractor_id`),
  KEY `order_claims_responsible_id_foreign` (`responsible_id`),
  KEY `order_claims_created_by_foreign` (`created_by`),
  KEY `order_claims_order_id_status_index` (`order_id`,`status`),
  KEY `order_claims_status_due_at_index` (`status`,`due_at`),
  CONSTRAINT `order_claims_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_claims_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_claims_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_claims_responsible_id_foreign` FOREIGN KEY (`responsible_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_document_edo_acknowledgements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_document_edo_acknowledgements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `party` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contractor_id` bigint unsigned NOT NULL DEFAULT '0',
  `received_via_edo` tinyint(1) NOT NULL DEFAULT '0',
  `document_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_document_edo_ack_unique` (`order_id`,`party`,`document_type`,`slot_key`,`contractor_id`),
  KEY `order_document_edo_acknowledgements_confirmed_by_foreign` (`confirmed_by`),
  CONSTRAINT `order_document_edo_acknowledgements_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_document_edo_acknowledgements_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `order_intake_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_intake_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `source_original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_mime_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_storage_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_storage_driver` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_text_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_text_length` int unsigned NOT NULL DEFAULT '0',
  `model` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `extracted_payload` json DEFAULT NULL,
  `wizard_patch` json DEFAULT NULL,
  `warnings` json DEFAULT NULL,
  `matched_contractors` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_intake_drafts_order_id_foreign` (`order_id`),
  KEY `order_intake_drafts_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `order_intake_drafts_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_intake_drafts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_intake_golden_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_intake_golden_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_intake_draft_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source_kind` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `user_instruction` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dialog_learnings` json DEFAULT NULL,
  `proposed_snapshot` json DEFAULT NULL,
  `applied_snapshot` json DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `committed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_intake_golden_records_order_intake_draft_id_unique` (`order_intake_draft_id`),
  KEY `order_intake_golden_records_order_id_foreign` (`order_id`),
  KEY `order_intake_golden_records_user_id_status_created_at_index` (`user_id`,`status`,`created_at`),
  KEY `order_intake_golden_records_status_committed_at_index` (`status`,`committed_at`),
  CONSTRAINT `order_intake_golden_records_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_intake_golden_records_order_intake_draft_id_foreign` FOREIGN KEY (`order_intake_draft_id`) REFERENCES `order_intake_drafts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_intake_golden_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_intake_phrase_learnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_intake_phrase_learnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `field` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_phrase` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `canonical_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `use_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_intake_phrase_learnings_unique` (`user_id`,`field`,`source_phrase`),
  CONSTRAINT `order_intake_phrase_learnings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `order_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `linked_order_id` bigint unsigned NOT NULL,
  `link_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'expedition_chain',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_links_order_id_linked_order_id_unique` (`order_id`,`linked_order_id`),
  KEY `order_links_created_by_foreign` (`created_by`),
  KEY `order_links_linked_order_id_index` (`linked_order_id`),
  CONSTRAINT `order_links_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_links_linked_order_id_foreign` FOREIGN KEY (`linked_order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_links_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_numbering_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_numbering_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cipher` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `own_company_id` bigint unsigned NOT NULL,
  `separator` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '-',
  `prefix_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sequence',
  `prefix_value` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `body_value` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month',
  `suffix_value` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequence_pad` tinyint unsigned NOT NULL DEFAULT '0',
  `sequence_scope` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month',
  `sequence_counters` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_numbering_rules_cipher_unique` (`cipher`),
  UNIQUE KEY `order_numbering_rules_own_company_id_unique` (`own_company_id`),
  CONSTRAINT `order_numbering_rules_own_company_id_foreign` FOREIGN KEY (`own_company_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_one_c_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_one_c_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `document_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_ref` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_date` datetime DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `counterparty_inn` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counterparty_kpp` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_payload` json DEFAULT NULL,
  `response_payload` json DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_one_c_documents_order_id_document_type_unique` (`order_id`,`document_type`),
  KEY `order_one_c_documents_created_by_foreign` (`created_by`),
  KEY `order_one_c_documents_document_type_status_index` (`document_type`,`status`),
  KEY `order_one_c_documents_external_ref_index` (`external_ref`),
  CONSTRAINT `order_one_c_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_one_c_documents_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_portal_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_portal_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned NOT NULL,
  `stage` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_slot` tinyint unsigned NOT NULL DEFAULT '1',
  `purpose` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'carrier_fleet',
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `last_opened_at` timestamp NULL DEFAULT NULL,
  `submitted_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_portal_invites_order_id_foreign` (`order_id`),
  KEY `order_portal_invites_contractor_id_foreign` (`contractor_id`),
  KEY `order_portal_invites_created_by_foreign` (`created_by`),
  CONSTRAINT `order_portal_invites_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_portal_invites_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_portal_invites_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
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
  `order_owner_id` bigint unsigned DEFAULT NULL,
  `dispatcher_id` bigint unsigned DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `loading_date` date DEFAULT NULL,
  `unloading_date` date DEFAULT NULL,
  `customer_rate` decimal(12,2) DEFAULT NULL,
  `customer_payment_form` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_payment_term` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_payment_date` date DEFAULT NULL,
  `special_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_basic_terms` json DEFAULT NULL,
  `carrier_basic_terms` json DEFAULT NULL,
  `svh_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `svh_address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_post_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_post_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_declaration_place` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customs_commodity_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_declared_sum` decimal(15,2) DEFAULT NULL,
  `is_international_transport` tinyint(1) NOT NULL DEFAULT '0',
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
  `carrier_own_company_id` bigint unsigned DEFAULT NULL,
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
  `track_received_date_customer_request` date DEFAULT NULL,
  `track_received_date_customer_closing` date DEFAULT NULL,
  `track_number_carrier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_sent_date_carrier` date DEFAULT NULL,
  `track_received_date_carrier` date DEFAULT NULL,
  `track_received_date_carrier_request` date DEFAULT NULL,
  `track_received_date_carrier_closing` date DEFAULT NULL,
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
  `accounting_handoff_at` timestamp NULL DEFAULT NULL,
  `accounting_handoff_by` bigint unsigned DEFAULT NULL,
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
  KEY `orders_accounting_handoff_by_foreign` (`accounting_handoff_by`),
  KEY `orders_order_owner_id_foreign` (`order_owner_id`),
  KEY `orders_dispatcher_id_foreign` (`dispatcher_id`),
  KEY `orders_carrier_own_company_id_foreign` (`carrier_own_company_id`),
  CONSTRAINT `orders_accounting_handoff_by_foreign` FOREIGN KEY (`accounting_handoff_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_carrier_own_company_id_foreign` FOREIGN KEY (`carrier_own_company_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_dispatcher_id_foreign` FOREIGN KEY (`dispatcher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_driver_id_foreign` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_order_owner_id_foreign` FOREIGN KEY (`order_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_own_company_id_foreign` FOREIGN KEY (`own_company_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_status_updated_by_foreign` FOREIGN KEY (`status_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `own_fleet_cost_norms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `own_fleet_cost_norms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cn_fuel_price_rub_per_liter` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `cn_fuel_consumption_l_per_100km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `cn_driver_rub_per_km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `cn_other_rub_per_km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `ru_fuel_price_rub_per_liter` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `ru_fuel_consumption_l_per_100km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `ru_driver_rub_per_km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `ru_other_rub_per_km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `depreciation_rub_per_km` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `margin_percent` decimal(8,2) NOT NULL DEFAULT '0.00',
  `margin_absolute_rub` decimal(14,2) NOT NULL DEFAULT '0.00',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `own_fleet_cost_norms_updated_by_foreign` (`updated_by`),
  CONSTRAINT `own_fleet_cost_norms_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
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
DROP TABLE IF EXISTS `payment_schedule_payment_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_schedule_payment_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `payment_schedule_id` bigint unsigned DEFAULT NULL,
  `party` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pspe_contractor_date_idx` (`contractor_id`,`payment_date`),
  KEY `pspe_order_party_idx` (`order_id`,`party`),
  KEY `pspe_schedule_idx` (`payment_schedule_id`),
  CONSTRAINT `payment_schedule_payment_events_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_schedule_payment_events_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_schedule_payment_events_payment_schedule_id_foreign` FOREIGN KEY (`payment_schedule_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `party` enum('customer','carrier','contractor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('prepayment','final','installment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installment_sequence` tinyint unsigned DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `invoice_number` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_partial` tinyint(1) NOT NULL DEFAULT '0',
  `planned_date` date DEFAULT NULL,
  `actual_date` date DEFAULT NULL,
  `payment_run_date` date DEFAULT NULL,
  `payment_run_by` bigint unsigned DEFAULT NULL,
  `payment_run_note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  KEY `payment_schedules_payment_run_by_foreign` (`payment_run_by`),
  KEY `payment_schedules_payment_run_date_index` (`payment_run_date`),
  CONSTRAINT `payment_schedules_counterparty_id_foreign` FOREIGN KEY (`counterparty_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_schedules_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_schedules_parent_payment_id_foreign` FOREIGN KEY (`parent_payment_id`) REFERENCES `payment_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_schedules_payment_run_by_foreign` FOREIGN KEY (`payment_run_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `print_form_basic_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `print_form_basic_terms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `party` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contractor_id` bigint unsigned DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `print_form_basic_terms_contractor_id_foreign` (`contractor_id`),
  KEY `print_form_basic_terms_scope_sort_idx` (`party`,`contractor_id`,`sort_order`),
  CONSTRAINT `print_form_basic_terms_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL
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
  `own_company_id` bigint unsigned DEFAULT NULL,
  `transport_scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'any',
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
  KEY `print_form_templates_own_company_id_foreign` (`own_company_id`),
  CONSTRAINT `print_form_templates_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `print_form_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `print_form_templates_own_company_id_foreign` FOREIGN KEY (`own_company_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `print_form_templates_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `load_board_post_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `order_owner_id` bigint unsigned DEFAULT NULL,
  `buyer_id` bigint unsigned DEFAULT NULL,
  `dispatcher_id` bigint unsigned DEFAULT NULL,
  `buying_own_company_id` bigint unsigned DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `procurement_cases_load_board_post_id_foreign` (`load_board_post_id`),
  KEY `procurement_cases_order_owner_id_foreign` (`order_owner_id`),
  KEY `procurement_cases_buyer_id_foreign` (`buyer_id`),
  KEY `procurement_cases_dispatcher_id_foreign` (`dispatcher_id`),
  KEY `procurement_cases_buying_own_company_id_foreign` (`buying_own_company_id`),
  KEY `procurement_cases_order_id_status_index` (`order_id`,`status`),
  KEY `procurement_cases_lead_id_status_index` (`lead_id`,`status`),
  CONSTRAINT `procurement_cases_buyer_id_foreign` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_cases_buying_own_company_id_foreign` FOREIGN KEY (`buying_own_company_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_cases_dispatcher_id_foreign` FOREIGN KEY (`dispatcher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_cases_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_cases_load_board_post_id_foreign` FOREIGN KEY (`load_board_post_id`) REFERENCES `load_board_posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_cases_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_cases_order_owner_id_foreign` FOREIGN KEY (`order_owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_html_template_variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_html_template_variables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lead',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_html_template_variables_path_unique` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_html_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_html_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `html_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `css_inline` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email_assets` json DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `published_at` timestamp NULL DEFAULT NULL,
  `owner_user_id` bigint unsigned DEFAULT NULL,
  `visibility` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'workspace',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proposal_html_templates_slug_unique` (`slug`),
  KEY `proposal_html_templates_owner_user_id_foreign` (`owner_user_id`),
  CONSTRAINT `proposal_html_templates_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_user_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `role_user_role_id_foreign` (`role_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `period_id` bigint unsigned DEFAULT NULL,
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
  KEY `salary_payouts_payout_date_index` (`payout_date`),
  CONSTRAINT `salary_payouts_period_id_foreign` FOREIGN KEY (`period_id`) REFERENCES `salary_periods` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `sales_book_article_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_book_article_feedback` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_book_article_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `rating` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `source` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `turn_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_book_article_feedback_user_id_foreign` (`user_id`),
  KEY `sales_book_article_feedback_sales_book_article_id_rating_index` (`sales_book_article_id`,`rating`),
  KEY `sales_book_article_feedback_created_at_index` (`created_at`),
  KEY `sales_book_article_feedback_turn_id_index` (`turn_id`),
  KEY `sales_book_article_feedback_source_created_at_index` (`source`,`created_at`),
  CONSTRAINT `sales_book_article_feedback_sales_book_article_id_foreign` FOREIGN KEY (`sales_book_article_id`) REFERENCES `sales_book_articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_book_article_feedback_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `status` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `tags` json DEFAULT NULL,
  `cover_image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `content_format` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'markdown',
  `blocks_snapshot` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_book_articles_created_by_foreign` (`created_by`),
  KEY `sales_book_articles_updated_by_foreign` (`updated_by`),
  KEY `sales_book_articles_parent_id_sort_order_index` (`parent_id`,`sort_order`),
  KEY `sales_book_articles_status_index` (`status`),
  KEY `sales_book_articles_content_format_index` (`content_format`),
  CONSTRAINT `sales_book_articles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_book_articles_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `sales_book_articles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_book_articles_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_book_quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_book_quiz_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_book_article_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `score` smallint unsigned NOT NULL,
  `total_questions` smallint unsigned NOT NULL,
  `answers` json NOT NULL,
  `completed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sb_quiz_attempts_article_completed_idx` (`sales_book_article_id`,`completed_at`),
  KEY `sb_quiz_attempts_user_completed_idx` (`user_id`,`completed_at`),
  CONSTRAINT `sales_book_quiz_attempts_sales_book_article_id_foreign` FOREIGN KEY (`sales_book_article_id`) REFERENCES `sales_book_articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_book_quiz_attempts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
DROP TABLE IF EXISTS `sales_script_nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_script_nodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_script_version_id` bigint unsigned NOT NULL,
  `client_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_variant_b` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ab_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `ab_variant_b_weight` tinyint unsigned NOT NULL DEFAULT '50',
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
  PRIMARY KEY (`id`)
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
  `lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `context_tags` json DEFAULT NULL,
  `return_stack` json DEFAULT NULL,
  `is_trainer` tinyint(1) NOT NULL DEFAULT '0',
  `trainer_profile_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_profile_title` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_profile_context` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `training_role_mode` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager_seller',
  `trainer_assistant_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `trainer_dialog_quality` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trainer_score` tinyint unsigned DEFAULT NULL,
  `trainer_ai_role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_reaction_class_id` bigint unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `crm_synced_at` timestamp NULL DEFAULT NULL,
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
  KEY `sales_script_play_sessions_lead_id_index` (`lead_id`),
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
  `sales_script_node_id` bigint unsigned DEFAULT NULL,
  `step_key` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `peer_reaction` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_peer_reaction` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `feedback_tags` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sstm_user` (`user_id`),
  KEY `sstm_sess_id_idx` (`sales_script_play_session_id`,`id`),
  KEY `sstm_node_id_fk` (`sales_script_node_id`),
  KEY `sales_script_trainer_messages_step_key_index` (`step_key`),
  CONSTRAINT `fk_sstm_play_session` FOREIGN KEY (`sales_script_play_session_id`) REFERENCES `sales_script_play_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sstm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sstm_node_id_fk` FOREIGN KEY (`sales_script_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE SET NULL
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
  `target_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'node',
  `target_sales_script_version_id` bigint unsigned DEFAULT NULL,
  `sales_script_reaction_class_id` bigint unsigned DEFAULT NULL,
  `customer_label` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversation_effect` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `momentum_delta` smallint DEFAULT NULL,
  `next_move_preview` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_script_transitions_sales_script_version_id_foreign` (`sales_script_version_id`),
  KEY `sales_script_transitions_from_node_id_foreign` (`from_node_id`),
  KEY `sales_script_transitions_to_node_id_foreign` (`to_node_id`),
  KEY `sales_script_transitions_sales_script_reaction_class_id_foreign` (`sales_script_reaction_class_id`),
  KEY `sales_script_transitions_target_sales_script_version_id_foreign` (`target_sales_script_version_id`),
  KEY `sales_script_transitions_target_type_index` (`target_type`),
  CONSTRAINT `sales_script_transitions_from_node_id_foreign` FOREIGN KEY (`from_node_id`) REFERENCES `sales_script_nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_script_transitions_sales_script_reaction_class_id_foreign` FOREIGN KEY (`sales_script_reaction_class_id`) REFERENCES `sales_script_reaction_classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_script_transitions_sales_script_version_id_foreign` FOREIGN KEY (`sales_script_version_id`) REFERENCES `sales_script_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_script_transitions_target_sales_script_version_id_foreign` FOREIGN KEY (`target_sales_script_version_id`) REFERENCES `sales_script_versions` (`id`) ON DELETE SET NULL,
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
  `company_initiative_id` bigint unsigned DEFAULT NULL,
  `company_initiative_milestone_id` bigint unsigned DEFAULT NULL,
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
  KEY `tasks_contractor_id_index` (`contractor_id`),
  KEY `tasks_company_initiative_id_index` (`company_initiative_id`),
  KEY `tasks_company_initiative_milestone_id_index` (`company_initiative_milestone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transport_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transport_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'truck',
  `allows_oversize` tinyint(1) NOT NULL DEFAULT '0',
  `length_mm` int unsigned NOT NULL,
  `width_mm` int unsigned NOT NULL,
  `height_mm` int unsigned NOT NULL,
  `max_payload_kg` int unsigned NOT NULL DEFAULT '0',
  `axles_count` tinyint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transport_templates_created_by_foreign` (`created_by`),
  KEY `transport_templates_category_is_active_sort_order_index` (`category`,`is_active`,`sort_order`),
  CONSTRAINT `transport_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_mobile_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_mobile_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `device_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` text COLLATE utf8mb4_unicode_ci,
  `failed_pin_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `pin_locked_until` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_mobile_devices_device_key_unique` (`device_key`),
  KEY `user_mobile_devices_user_id_foreign` (`user_id`),
  CONSTRAINT `user_mobile_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_signing_own_company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_signing_own_company` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `contractor_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_signing_own_company_user_id_contractor_id_unique` (`user_id`,`contractor_id`),
  KEY `user_signing_own_company_contractor_id_foreign` (`contractor_id`),
  CONSTRAINT `user_signing_own_company_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_signing_own_company_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` tinyint unsigned DEFAULT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_imap_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mail_sync_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `mail_last_sync_at` timestamp NULL DEFAULT NULL,
  `mail_last_sync_error` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'light',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `hidden_from_lists` tinyint(1) NOT NULL DEFAULT '0',
  `is_external` tinyint(1) NOT NULL DEFAULT '0',
  `contractor_id` bigint unsigned DEFAULT NULL,
  `contractor_contact_id` bigint unsigned DEFAULT NULL,
  `external_party` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_signing_authority` tinyint(1) NOT NULL DEFAULT '0',
  `belongs_to_management` tinyint(1) NOT NULL DEFAULT '0',
  `can_management_accounting` tinyint(1) NOT NULL DEFAULT '0',
  `sees_company_dashboard` tinyint(1) NOT NULL DEFAULT '0',
  `ai_preferences` json DEFAULT NULL,
  `mobile_nav_keys` json DEFAULT NULL,
  `ui_preferences` json DEFAULT NULL,
  `ai_learning_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_contractor_contact_id_unique` (`contractor_contact_id`),
  KEY `users_site_id_index` (`site_id`),
  KEY `users_role_id_index` (`role_id`),
  KEY `users_contractor_id_foreign` (`contractor_id`),
  CONSTRAINT `users_contractor_contact_id_foreign` FOREIGN KEY (`contractor_contact_id`) REFERENCES `contractor_contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_contractor_id_foreign` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vat_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vat_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_05_12_120000_widen_orders_payment_term_columns',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_05_06_120000_add_is_international_transport_to_orders_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_05_12_140000_add_phone_to_users_and_svh_to_orders',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_05_12_160000_add_own_company_bank_account_id_to_orders_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_05_13_120000_add_svh_and_customs_detail_columns_to_orders_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_05_13_210000_add_customs_post_name_to_orders_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_05_12_220000_add_ui_preferences_to_users_table',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_05_14_183202_make_salary_payouts_period_id_nullable',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_05_15_120000_add_file_fields_to_contractor_documents_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_05_16_071217_create_user_signing_own_company_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_05_16_120000_add_default_norms_penalties_to_contractors_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_05_16_120000_add_english_requisites_to_contractors_table',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_05_19_100000_create_business_processes_tables',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_05_19_120000_add_playbook_fields_to_business_process_stages',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_05_19_185349_create_activity_events_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_05_19_185349_create_mail_threads_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_05_19_185349_enhance_lead_offers_for_commercial_intelligence',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_05_19_185350_add_no_reply_nudge_days_to_business_process_stages',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_05_19_185350_create_mail_messages_table',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_05_20_162547_create_loading_cargo_groups_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_05_20_162547_create_loading_cargo_items_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_05_20_162547_create_loading_planner_projects_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_05_20_162547_create_transport_templates_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_05_20_171128_add_client_key_to_loading_cargo_items_table',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_05_21_170742_add_belongs_to_management_to_users_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_05_21_170742_create_budget_scenarios_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_05_21_172456_create_budget_opex_articles_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_05_21_181232_add_percent_of_margin_to_budget_opex_articles_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_05_19_120000_add_carrier_slot_to_leg_contractor_assignments',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_05_25_182115_create_fleet_trips_tables',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_05_26_041813_create_role_user_table',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_05_26_050103_create_order_portal_invites_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_05_26_052501_add_operational_status_fields_to_contractors_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_05_26_190008_create_payment_schedule_payment_events_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_05_28_111612_expand_contractor_and_payment_schedule_party_enums',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_05_29_112456_add_edo_fields_to_contractors_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_05_29_173642_add_cargo_declared_sum_to_orders_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_05_29_185957_add_own_company_and_transport_scope_to_print_form_templates',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_05_31_081254_sync_admin_role_visibility_areas',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_05_31_133146_create_personal_access_tokens_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_05_31_140000_create_ai_tool_audit_logs_table',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_05_31_142623_create_disposition_entries_table',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_05_31_170228_create_order_intake_drafts_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_06_01_060542_create_ai_interaction_events_table',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_06_02_040301_add_close_outcome_fields_to_leads_table',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_06_03_145827_create_sales_book_article_feedback_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_06_03_153052_add_command_bar_context_to_sales_book_article_feedback_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_06_03_155728_add_status_to_sales_book_articles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_06_03_162306_add_tags_to_sales_book_articles_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_06_03_170643_add_cover_image_path_to_sales_book_articles_table',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_06_03_175215_create_sales_book_quiz_attempts_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_06_03_175526_add_indexes_to_sales_book_quiz_attempts_table',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_06_04_040855_add_mail_imap_fields_to_users_table',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2026_06_04_041122_add_mail_sync_columns_to_mail_tables',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_06_04_171948_create_order_numbering_rules_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_06_04_173258_create_order_intake_phrase_learnings_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_06_04_174816_create_order_intake_golden_records_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_06_05_032040_add_mail_sync_domains_to_contractors_table',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_06_05_035813_add_attachments_to_mail_messages_table',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_06_05_090943_add_metadata_to_lead_cargo_items_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_06_06_064005_create_print_form_basic_terms_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2026_06_06_064010_add_basic_terms_overrides_to_orders_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_06_06_071332_create_mcp_data_links_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_06_06_175417_create_contractor_risk_snapshots_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_06_06_175418_create_contractor_risk_assessments_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2026_06_06_180906_add_submission_fields_to_contractor_risk_assessments_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_06_06_192811_create_departments_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_06_06_192812_add_ntfy_topic_to_users_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_06_06_192812_create_department_user_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_06_07_155057_add_contractor_portrait_schema',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_06_07_173537_create_contractor_print_form_change_requests_table',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_06_08_123529_create_kpi_deduction_rules_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_06_08_155321_add_installment_sequence_to_payment_schedules_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_06_08_191914_add_accounting_handoff_to_orders_table',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2026_06_09_115905_widen_financial_terms_client_payment_terms_to_400',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2026_06_10_102215_create_mail_blocked_senders_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2026_06_10_152130_add_customer_label_to_sales_script_transitions_table',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2026_06_10_195759_add_tags_to_sales_script_nodes_table',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2026_06_10_200147_create_sales_script_editor_extensions_tables',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2026_06_10_211015_create_management_accounting_tables',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2026_06_10_213333_fix_management_statement_lines_foreign_key_names',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2026_06_10_220513_add_management_expense_category_id_to_budget_opex_articles_table',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2026_06_10_220838_create_management_reconcile_rules_table',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2026_06_11_092654_seed_management_expense_system_categories',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2026_06_11_211728_add_hierarchy_to_management_expense_categories_table',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2026_06_11_212738_seed_cost_own_fleet_management_category',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2026_06_12_114828_add_include_in_budget_to_management_expense_categories_table',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2026_06_12_162154_create_grid_views_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2026_06_15_210851_add_reversal_columns_to_payment_schedule_payment_events_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2026_06_15_211937_add_finance_payment_reconcile_visibility_to_accountant_roles',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2026_06_16_120615_add_barrel_pack_type_to_ati_dictionary_items',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2026_06_16_130000_sync_pack_type_ati_dictionary_items',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2026_06_16_204547_add_sees_company_dashboard_to_users_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2026_06_16_214720_add_playbook_coaching_fields_to_business_process_stages_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2026_06_16_220348_add_sales_script_id_to_business_process_stages_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2026_06_17_154201_create_import_cost_tn_ved_entries_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2026_06_17_154202_create_import_cost_pp1291_categories_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_06_17_154203_create_import_cost_reference_syncs_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_06_18_114631_add_kodtnved_fields_to_import_cost_tn_ved_entries_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_06_18_141524_add_alta_fields_to_import_cost_tn_ved_entries_table',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_06_18_153931_add_plan_hierarchy_to_budget_scenarios_table',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_06_18_153932_create_budget_plan_snapshots_tables',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_06_18_153932_create_management_statement_line_splits_table',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2026_06_21_222758_create_contractor_insight_drafts_table',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2026_06_21_230000_create_proposal_html_templates_table',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_06_21_240000_add_ab_and_context_to_sales_scripts',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2026_06_23_111841_create_lead_attachments_table',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_06_24_091729_add_payment_forms_to_leads_table',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_06_21_235900_create_commercial_ai_suggestion_logs_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_06_21_235901_add_automated_actions_to_business_process_stages_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_06_24_121919_add_nudge_triggers_to_business_process_stages_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_06_26_170000_widen_mail_subject_columns',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2026_06_29_120034_add_payment_run_fields_to_payment_schedules_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2026_06_30_140418_add_lead_id_to_sales_script_play_sessions_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2026_06_30_210504_add_feedback_context_to_sales_script_trainer_messages_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2026_07_02_111644_create_load_board_posts_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2026_07_02_111645_create_load_board_offers_table',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2026_07_02_152142_grant_load_board_visibility_to_existing_roles',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2026_07_03_092401_add_ati_dictionary_fields_to_load_board_posts',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_07_03_100513_add_acceptance_fields_to_load_board_posts',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_07_03_131346_repair_leads_won_on_lost_terminal_stage',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2026_07_04_154859_create_user_mobile_devices_table',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_07_04_171344_drop_ntfy_topic_from_users_table',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_07_05_180001_add_external_user_fields_to_users_and_contractor_contacts',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2026_07_05_180002_add_counterparty_fields_to_conversations_table',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2026_07_05_180003_create_external_user_invites_table',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2026_07_05_180004_seed_external_counterparty_roles',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2026_07_05_180005_make_users_password_nullable_for_external_invites',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2026_07_05_190001_add_order_context_to_chat_messages',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2026_07_06_131219_add_company_planning_columns_to_tasks_table',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2026_07_06_131219_create_company_planning_tables',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2026_07_07_095038_show_owner_name_in_contractors_grid_default',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2026_07_07_102025_show_track_number_columns_in_orders_grid_default',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2026_07_07_183338_add_sales_book_article_metadata_columns',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (246,'2026_07_07_184813_add_blocks_snapshot_to_sales_book_articles_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (247,'2026_07_08_143701_add_subscript_transitions_to_sales_scripts',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (248,'2026_07_08_181259_create_load_board_rate_observations_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (249,'2026_07_08_202800_add_lead_performers_and_route_point_stage',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (250,'2026_07_08_203503_add_precalculation_to_leads_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (251,'2026_07_08_210000_add_order_owner_and_dispatcher_to_orders_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (252,'2026_07_08_210100_create_procurement_cases_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (253,'2026_07_09_143615_create_order_document_edo_acknowledgements_table',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (254,'2026_07_09_220000_create_budget_sales_targets_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (255,'2026_07_09_230000_add_live_guidance_to_sales_script_transitions_and_sessions',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (256,'2026_07_10_113910_add_lead_and_order_links_to_loading_planner_projects_table',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (257,'2026_07_11_201800_remove_legacy_order_permissions_from_roles',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (258,'2026_07_14_120559_add_governance_and_idempotency_to_messenger_tables',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (259,'2026_07_14_144031_add_replies_and_attachments_to_chat_messages',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (260,'2026_07_14_150008_change_default_work_status_for_new_contractors',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (261,'2026_07_15_121259_add_email_assets_to_proposal_html_templates_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (262,'2026_07_20_160335_create_order_claims_table',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (263,'2026_07_21_141949_create_order_links_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (264,'2026_07_22_110126_add_phones_to_contractor_contacts_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (265,'2026_07_22_150212_seed_client_acquaintance_business_process',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (266,'2026_07_28_110415_add_carrier_own_company_id_to_orders_table',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (267,'2026_07_22_184545_create_lead_rate_quotes_table',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (268,'2026_07_30_194008_create_fleet_containers_table',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (269,'2026_08_03_131025_create_improvement_signals_table',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (270,'2026_08_03_134803_create_improvement_experiment_assignments_table',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (271,'2026_08_03_141015_add_meta_to_improvement_adoptions_table',112);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (272,'2026_08_06_115704_add_track_received_date_request_closing_to_orders_table',113);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (273,'2026_08_07_205314_create_contractor_enrichment_runs_table',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (274,'2026_08_09_160000_create_order_one_c_documents_table',114);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (275,'2026_08_25_105951_add_payment_form_to_payment_schedules_table',115);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (276,'2026_08_27_151720_create_own_fleet_cost_norms_table',116);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (277,'2026_08_31_142047_add_dimension_unit_to_cargos_table',117);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (278,'2026_09_01_120000_add_allow_oversize_to_loading_cargo_items_table',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (279,'2026_09_01_130000_add_allows_oversize_to_transport_templates_table',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (280,'2026_09_04_134754_drop_has_signing_authority_from_roles_table',118);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (281,'2026_09_04_144947_add_hidden_from_lists_to_users_table',119);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (282,'2026_09_07_192020_create_one_c_epd_registry_entries_table',120);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (283,'2026_09_08_151123_add_document_meta_to_one_c_epd_registry_entries_table',121);
