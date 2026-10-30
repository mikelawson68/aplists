<?php
/** apLists CLI. @package apLists */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
final class APLists_CLI
{
    public function install(): void { APLists_Schema::install();WP_CLI::success('apLists schema installed.'); }
    public function counts(): void { global $wpdb;$counts=[];foreach(APLists_Schema::tables() as $key=>$table)$counts[$key]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i",$table));WP_CLI::line((string)wp_json_encode($counts)); }
    public function smoke_lifecycle(): void
    {
        global $wpdb;$run=strtolower(wp_generate_password(8,false,false));$list=APLists_Lifecycle::create_list(['name'=>'Smoke '.$run,'description'=>'Temporary release test','double_opt_in'=>1,'reply_to'=>get_option('admin_email')]);$list_id=(int)($list['list_id']??0);
        $pending=APLists_Lifecycle::upsert_subscriber($list_id,['email'=>'pending-'.$run.'@example.com','first_name'=>'Pending','source'=>'cli_test']);$active=APLists_Lifecycle::upsert_subscriber($list_id,['email'=>'active-'.$run.'@example.com','first_name'=>'Active','source'=>'cli_test','force_subscribed'=>true]);$suppressed=APLists_Lifecycle::suppress_email('active-'.$run.'@example.com','cli_test');
        $counts=[];foreach(APLists_Schema::tables() as $key=>$table)$counts[$key]=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i",$table));WP_CLI::line((string)wp_json_encode(['run_key'=>$run,'list'=>$list,'pending'=>$pending,'active'=>$active,'suppressed'=>$suppressed,'counts'=>$counts,'standalone'=>true,'crm_required'=>false]));
    }
}
if(defined('WP_CLI')&&WP_CLI){WP_CLI::add_command('aplists','APLists_CLI');}
