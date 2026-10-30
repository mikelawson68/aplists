<?php
/** apLists schema. @package apLists */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class APLists_Schema
{
    /** @return array<string,string> */
    public static function tables(): array
    {
        global $wpdb;
        $p = $wpdb->prefix . 'aplists_';
        return [
            'lists' => $p . 'subscriber_lists',
            'segments' => $p . 'list_segments',
            'criteria' => $p . 'segment_criteria',
            'subscribers' => $p . 'subscribers',
            'fields' => $p . 'subscriber_fields',
            'imports' => $p . 'import_jobs',
            'exports' => $p . 'export_jobs',
            'suppressions' => $p . 'suppression_records',
            'bounces' => $p . 'bounce_records',
            'complaints' => $p . 'complaint_records',
            'hygiene_rules' => $p . 'hygiene_rules',
            'hygiene_jobs' => $p . 'hygiene_jobs',
        ];
    }

    public static function activate(): void
    {
        if (!self::install()) {
            wp_die(esc_html__('apLists could not create its database tables. Check database permissions and try activating again.', 'artistpro-lists'), '', ['response' => 500]);
        }
    }

    public static function install(): bool
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $t = self::tables();
        $sql = [];

        $sql[] = "CREATE TABLE {$t['lists']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
name varchar(190) NOT NULL,
description text NULL,
double_opt_in tinyint unsigned NOT NULL DEFAULT 1,
from_name varchar(190) NOT NULL DEFAULT '',
reply_to varchar(190) NOT NULL DEFAULT '',
subscriber_count bigint unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY name (name)
) $c;";

        $sql[] = "CREATE TABLE {$t['segments']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
list_id bigint unsigned NOT NULL,
name varchar(190) NOT NULL,
description text NULL,
subscriber_count bigint unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY list_id (list_id)
) $c;";

        $sql[] = "CREATE TABLE {$t['criteria']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
segment_id bigint unsigned NOT NULL,
field varchar(190) NOT NULL,
operator varchar(50) NOT NULL,
value text NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY segment_id (segment_id)
) $c;";

        $sql[] = "CREATE TABLE {$t['subscribers']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
email varchar(190) NOT NULL,
first_name varchar(190) NULL,
last_name varchar(190) NULL,
status varchar(32) NOT NULL DEFAULT 'pending',
list_id bigint unsigned NOT NULL,
source varchar(100) NOT NULL DEFAULT '',
consent_at datetime NULL,
consent_ip_hash varchar(64) NOT NULL DEFAULT '',
confirmed_at datetime NULL,
unsubscribed_at datetime NULL,
confirmation_token varchar(64) NOT NULL DEFAULT '',
unsubscribe_token varchar(64) NOT NULL DEFAULT '',
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY email_list (email,list_id),
KEY list_id (list_id),
KEY status (status),
KEY confirmation_token (confirmation_token),
KEY unsubscribe_token (unsubscribe_token)
) $c;";

        $sql[] = "CREATE TABLE {$t['fields']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
subscriber_id bigint unsigned NOT NULL,
field_key varchar(190) NOT NULL,
field_value text NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY subscriber_field (subscriber_id,field_key),
KEY field_key (field_key)
) $c;";

        $sql[] = "CREATE TABLE {$t['imports']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
list_id bigint unsigned NOT NULL,
source_type varchar(50) NOT NULL,
source_details text NULL,
status varchar(32) NOT NULL DEFAULT 'pending',
processed_count int unsigned NOT NULL DEFAULT 0,
error_count int unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
completed_at datetime NULL,
PRIMARY KEY  (id),
KEY list_id (list_id),
KEY status (status)
) $c;";

        $sql[] = "CREATE TABLE {$t['exports']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
list_id bigint unsigned NOT NULL,
segment_id bigint unsigned NULL,
format varchar(20) NOT NULL DEFAULT 'csv',
status varchar(32) NOT NULL DEFAULT 'completed',
record_count int unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
completed_at datetime NULL,
PRIMARY KEY  (id),
KEY list_id (list_id)
) $c;";

        $sql[] = "CREATE TABLE {$t['suppressions']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
email varchar(190) NOT NULL,
reason varchar(100) NOT NULL,
source varchar(100) NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY email (email)
) $c;";

        $sql[] = "CREATE TABLE {$t['bounces']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
email varchar(190) NOT NULL,
bounce_type varchar(50) NOT NULL,
bounce_reason text NULL,
provider varchar(100) NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY email (email)
) $c;";

        $sql[] = "CREATE TABLE {$t['complaints']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
email varchar(190) NOT NULL,
complaint_type varchar(50) NOT NULL,
provider varchar(100) NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY email (email)
) $c;";

        $sql[] = "CREATE TABLE {$t['hygiene_rules']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
name varchar(190) NOT NULL,
rule_type varchar(64) NOT NULL,
settings_json text NULL,
status varchar(32) NOT NULL DEFAULT 'active',
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY status (status)
) $c;";

        $sql[] = "CREATE TABLE {$t['hygiene_jobs']} (
id bigint unsigned NOT NULL AUTO_INCREMENT,
rule_id bigint unsigned NOT NULL,
status varchar(32) NOT NULL DEFAULT 'queued',
processed_count int unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
completed_at datetime NULL,
PRIMARY KEY  (id),
KEY status (status)
) $c;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
        // Do not mark an incomplete installation as ready. The network
        // initializer can retry on a later request after the host is repaired.
        foreach ($t as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($found !== $table) return false;
        }
        update_option('aplists_version', APLISTS_VERSION, false);
        add_option('aplists_installed', time());
        return true;
    }
}
