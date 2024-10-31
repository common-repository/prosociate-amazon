<?php
/**
 * Plugin database schema
 * WARNING:
 * 	dbDelta() doesn't like empty lines in schema string, so don't put them there;
 *  WPDB doesn't like NULL values so better not to have them in the tables;
 */

/**
 * The database character collate.
 * @var string
 * @global string
 * @name $charset_collate
 */
$charset_collate = '';

// Declare these as global in case schema.php is included from a function.
global $wpdb, $plugin_queries;

if ( ! empty($wpdb->charset))
	$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
if ( ! empty($wpdb->collate))
	$charset_collate .= " COLLATE $wpdb->collate";

$table_prefix = $wpdb->prefix.PROSSOCIATE_PREFIX;

// Geo target table is different
$geoTable_prefix = $wpdb->prefix . PMLC_PREFIX;

$plugin_queries = <<<SCHEMA
CREATE TABLE {$table_prefix}campaigns (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL DEFAULT '',
	options TEXT,
	search_results TEXT,
	post_options TEXT,
	campaign_settings TEXT,
	search_parameters TEXT,
	associated_posts TEXT,
	last_run_time INT(10),
	cron_mode VARCHAR(255) NOT NULL DEFAULT '',
	cron_page VARCHAR(255) NOT NULL DEFAULT '',
	cron_running VARCHAR(255) NOT NULL DEFAULT 'no',
	cron_last_run_time INT(10),
	PRIMARY KEY  (id)
) $charset_collate;

CREATE TABLE {$table_prefix}prossubscription (
	id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL DEFAULT '',
	options TEXT,
	search_results TEXT,
	post_options TEXT,
	campaign_settings TEXT,
	search_parameters TEXT,
	associated_posts TEXT,
	last_run_time INT(10),
	cron_mode VARCHAR(255) NOT NULL DEFAULT '',
	cron_page VARCHAR(255) NOT NULL DEFAULT '',
	cron_running VARCHAR(255) NOT NULL DEFAULT 'no',
	cron_last_run_time INT(10),
	PRIMARY KEY  (id)
) $charset_collate;

CREATE TABLE {$geoTable_prefix}geoipcountry (
	begin_ip VARCHAR(15) NOT NULL DEFAULT '0.0.0.0',
	end_ip VARCHAR(15) NOT NULL DEFAULT '0.0.0.0',
	begin_num INT(10) UNSIGNED NOT NULL DEFAULT 0,
	end_num INT(10) UNSIGNED NOT NULL DEFAULT 0,
	country CHAR(2) NOT NULL DEFAULT '',
	name VARCHAR(50) NOT NULL DEFAULT '',
	PRIMARY KEY  (begin_num,end_num),
	KEY end_num (end_num)
) $charset_collate;
SCHEMA;
