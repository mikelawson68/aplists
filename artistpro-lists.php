<?php
/**
 * Plugin Name: apLists
 * Plugin URI: https://artistprowp.com/aplists/
 * Description: Own your WordPress subscriber lists with consent records, double opt-in, segmentation, suppression, CSV import/export, and one-click unsubscribe.
 * Version: 1.0.3
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: artistproWP
 * Author URI: https://artistprowp.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: artistpro-lists
 * Network: true
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Both editions share database tables and PHP classes; do not load them together.
$aplists_existing_edition = in_array('aplists/aplists.php', (array) get_option('active_plugins', []), true);
if (is_multisite()) {
    $aplists_existing_edition = $aplists_existing_edition || isset(get_site_option('active_sitewide_plugins', [])['aplists/aplists.php']);
}
if ($aplists_existing_edition) {
    register_activation_hook(__FILE__, static function (): void {
        wp_die(esc_html__('Deactivate the existing apLists edition before activating this one. Your subscriber data will be retained.', 'artistpro-lists'));
    });
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-warning"><p>' . esc_html__('The directory edition of apLists is paused because another apLists edition is active.', 'artistpro-lists') . '</p></div>';
    });
    return;
}

define('APLISTS_VERSION', '1.0.3');
define('APLISTS_FILE', __FILE__);
define('APLISTS_PATH', plugin_dir_path(__FILE__));

require_once APLISTS_PATH . 'includes/class-aplists-contract.php';
require_once APLISTS_PATH . 'includes/class-aplists-schema.php';
require_once APLISTS_PATH . 'includes/class-aplists-lifecycle.php';
require_once APLISTS_PATH . 'includes/class-aplists-cli.php';

register_activation_hook(__FILE__, ['APLists_Schema', 'install']);

add_action('plugins_loaded', static function (): void {
    APLists_Lifecycle::instance()->boot();
});
