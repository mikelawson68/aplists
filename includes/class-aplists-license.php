<?php
/** apLists license service. @package apLists */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
final class APLists_License
{
    public static function init(): void { self::client()->hooks(); add_action('admin_menu', [__CLASS__, 'menu'], 20); }
    public static function client(): ArtistProWP_License_Client
    {
        static $client = null;
        if (!$client) { $client = new ArtistProWP_License_Client([
            'product_key'=>'aplists','product_name'=>'apLists','plugin_file'=>plugin_basename(APLISTS_FILE),'version'=>APLISTS_VERSION,'option_prefix'=>'aplists','purchase_url'=>'https://artistprowp.com/aplists/','account_url'=>'https://artistprowp.com/my-account/','support_url'=>'https://artistprowp.com/support/','admin_page'=>'aplists-license','admin_screen_contains'=>'aplists-license','nonce_action'=>'aplists_license','ajax_prefix'=>'aplists_license',
        ]); }
        return $client;
    }
    public static function menu(): void { add_submenu_page('aplists','apLists License','License','manage_options','aplists-license',[__CLASS__,'render']); }
    public static function render(): void { self::client()->render_panel(); }
}
