<?php
/**
 * Plugin Name: apLists
 * Plugin URI: https://artistprowp.com/aplists/
 * Update URI: https://artistprowp.com/aplists/
 * Description: Own your WordPress subscriber lists with consent records, double opt-in, segmentation, suppression, CSV import/export, and one-click unsubscribe.
 * Version: 1.0.4
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: artistproWP
 * Author URI: https://artistprowp.com/
 * Text Domain: aplists
 * Network: true
  * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('APLISTS_VERSION', '1.0.4');
define('APLISTS_FILE', __FILE__);
define('APLISTS_PATH', plugin_dir_path(__FILE__));

require_once APLISTS_PATH . 'includes/class-aplists-contract.php';
require_once APLISTS_PATH . 'includes/class-aplists-schema.php';
require_once APLISTS_PATH . 'includes/class-aplists-lifecycle.php';
require_once APLISTS_PATH . 'includes/class-aplists-license.php';
require_once APLISTS_PATH . 'includes/class-aplists-cli.php';
require_once APLISTS_PATH . 'includes/class-artistprowp-license-client.php';


// Keep a cached WordPress.org update from replacing the retail edition.
// This guard is deliberately local to this plugin, independent of shared clients.
function aplists_retail_update_guard($transient) {
    if (!is_object($transient) || !isset($transient->response) || !is_array($transient->response)) { return $transient; }
    $plugin = plugin_basename(__FILE__);
    $entry = $transient->response[$plugin] ?? null;
    if (!is_object($entry)) { return $transient; }
    $host = strtolower((string) wp_parse_url((string) ($entry->package ?? ''), PHP_URL_HOST));
    if ($host === 'wordpress.org' || str_ends_with($host, '.wordpress.org')) { unset($transient->response[$plugin]); }
    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'aplists_retail_update_guard', PHP_INT_MAX);
add_filter('site_transient_update_plugins', 'aplists_retail_update_guard', PHP_INT_MAX);

register_activation_hook(__FILE__, ['APLists_Schema', 'install']);

add_action('plugins_loaded', static function (): void {
    APLists_Lifecycle::instance()->boot();
    APLists_License::init();
});
