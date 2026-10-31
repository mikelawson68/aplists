<?php
require dirname(__DIR__).'/artistpro-lists.php';do_action('plugins_loaded');
wp_set_current_user(1);if(!current_user_can('manage_options'))throw new Exception('QA administrator unavailable');
global $wpdb;if($wpdb->query('START TRANSACTION')===false)throw new Exception('Transaction unavailable');$old=$wpdb->suppress_errors(true);
add_filter('wp_die_handler',static fn()=>static function($message,$title,$args){throw new RuntimeException($message.'|'.($args['response']??0));});
try {
 $list=APLists_Lifecycle::create_list(['name'=>'Export QA','double_opt_in'=>0]);$id=(int)($list['list_id']??0);$subscriber=APLists_Lifecycle::upsert_subscriber($id,['email'=>'export-qa@example.invalid']);if(!$id||empty($subscriber['success']))throw new Exception('Fixture failed');
 for($i=0;$i<500;$i++){if(empty(APLists_Lifecycle::upsert_subscriber($id,['email'=>'export-extra-'.$i.'@example.invalid'])['success']))throw new Exception('Extra fixture failed');}
 $rows=iterator_to_array(APLists_Lifecycle::export_subscribers($id));if(count($rows)!==501||$rows[0]['email']!=='export-qa@example.invalid')throw new Exception('Normal export failed');
 $_GET=['list_id'=>$id,'_wpnonce'=>wp_create_nonce('aplists_export')];$_REQUEST=$_GET;
 foreach(['initial','page'] as $case){
  $blocked=false;$pages=0;$filter=static function($q)use($case,&$blocked,&$pages){if(stripos($q,'SELECT id,email,first_name')===0)$pages++;if(($case==='initial'&&stripos($q,'SELECT MAX(id)')===0)||($case==='page'&&$pages===2&&stripos($q,'SELECT id,email,first_name')===0)){$blocked=true;return 'SELECT * FROM `aplists_intentionally_missing_test_table`';}return $q;};
  add_filter('query',$filter);$result='';ob_start();try{APLists_Lifecycle::instance()->export();}catch(RuntimeException $e){$result=$e->getMessage();}finally{$output=ob_get_clean();remove_filter('query',$filter);}
  if(!$blocked||strpos($result,'Could not prepare subscriber export')===false||!str_ends_with($result,'|500')||$output!=='')throw new Exception('Unsafe export response for '.$case);
 }
 echo "PASS initial and later export failures return an error before CSV output; normal data is readable\n";
}finally{$_GET=[];$_REQUEST=[];$wpdb->query('ROLLBACK');$wpdb->suppress_errors($old);wp_cache_flush();}
