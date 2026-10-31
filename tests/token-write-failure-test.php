<?php
require dirname(__DIR__).'/artistpro-lists.php';
do_action('plugins_loaded');
global $wpdb;
if ($wpdb->query('START TRANSACTION') === false) throw new Exception('Transaction unavailable');
$old=$wpdb->suppress_errors(true);
add_filter('wp_die_handler',static fn()=>static function($message,$title,$args){throw new RuntimeException($message.'|'.($args['response']??0));});
try {
 $l=APLists_Lifecycle::create_list(['name'=>'Token failure QA','double_opt_in'=>1]);$id=(int)($l['list_id']??0);
 $s=APLists_Lifecycle::upsert_subscriber($id,['email'=>'token-qa@example.invalid']);if(empty($s['success']))throw new Exception('Fixture creation failed');
 $tables=APLists_Schema::tables();
 foreach (['confirm','unsubscribe'] as $case) {
  $table=$case==='confirm'?$tables['subscribers']:$tables['suppressions'];
  $block=static function($q)use($table){if(preg_match('/^\s*(UPDATE|REPLACE)\s+(?:INTO\s+)?`?'.preg_quote($table,'/').'`?/i',$q))return 'SELECT * FROM `aplists_intentionally_missing_test_table`';return $q;};
  $_GET=$case==='confirm'?['aplists_confirm'=>$s['confirmation_token']]:['aplists_unsubscribe'=>$s['unsubscribe_token']];
  add_filter('query',$block);$got='';
  try { APLists_Lifecycle::instance()->token_routes(); } catch (RuntimeException $e) {$got=$e->getMessage();} finally {remove_filter('query',$block);}
  if (strpos($got,'could not')===false || !str_ends_with($got,'|500')) throw new Exception('Misleading result for '.$case.': '.$got);
 }
 $_GET=['aplists_confirm'=>$s['confirmation_token']];$got='';try {APLists_Lifecycle::instance()->token_routes();} catch(RuntimeException $e) {$got=$e->getMessage();}if(strpos($got,'Subscription confirmed')===false||!str_ends_with($got,'|200'))throw new Exception('Normal confirmation failed');
 $table=$tables['subscribers'];$block=static function($q)use($table){if(preg_match('/^\s*UPDATE\s+`?'.preg_quote($table,'/').'`?/i',$q))return 'SELECT * FROM `aplists_intentionally_missing_test_table`';return $q;};
 add_filter('query',$block);try {$ok=APLists_Lifecycle::suppress_email('token-qa@example.invalid');}finally{remove_filter('query',$block);}
 if($ok || !(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE email=%s',$tables['suppressions'],'token-qa@example.invalid')))throw new Exception('Partial suppression was reported as successful or lost its protective record');
 $_GET=['aplists_unsubscribe'=>$s['unsubscribe_token']];$got='';try {APLists_Lifecycle::instance()->token_routes();} catch(RuntimeException $e) {$got=$e->getMessage();}if(strpos($got,'You have been unsubscribed')===false||!str_ends_with($got,'|200'))throw new Exception('Normal unsubscribe failed');
 echo "PASS confirmation and unsubscribe report database failures and succeed after recovery\n";
} finally { $_GET=[]; $wpdb->query('ROLLBACK');$wpdb->suppress_errors($old);wp_cache_flush(); }
