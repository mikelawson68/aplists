<?php
require dirname(__DIR__).'/artistpro-lists.php';
do_action('plugins_loaded');
global $wpdb;
if($wpdb->query('START TRANSACTION')===false)throw new Exception('Transaction unavailable');
$old=$wpdb->suppress_errors(true);
try {
 $l=APLists_Lifecycle::create_list(['name'=>'Suppression check QA','double_opt_in'=>0]);$id=(int)($l['list_id']??0);if(!$id)throw new Exception('Fixture unavailable');
 $t=APLists_Schema::tables();
 foreach (['suppressions','subscribers'] as $name) {
  $table=$t[$name];
  $block=static function($q)use($table){if(stripos(ltrim($q),'SELECT')===0 && strpos($q,$table)!==false)return 'SELECT * FROM `aplists_intentionally_missing_test_table`';return $q;};
  add_filter('query',$block);try {$r=APLists_Lifecycle::upsert_subscriber($id,['email'=>'lookup-test@example.invalid']);}finally{remove_filter('query',$block);}
  if(!empty($r['success']))throw new Exception('Database lookup failure allowed subscription for '.$name);
  $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE email=%s',$t['subscribers'],'lookup-test@example.invalid'));
  if($count!==0)throw new Exception('Subscriber was saved despite failed lookup');
 }
 $r=APLists_Lifecycle::upsert_subscriber($id,['email'=>'lookup-test@example.invalid']);if(empty($r['success']))throw new Exception('Valid subscription after recovery failed');
 if(!APLists_Lifecycle::suppress_email('lookup-test@example.invalid'))throw new Exception('Suppression setup failed');
 $r=APLists_Lifecycle::upsert_subscriber($id,['email'=>'lookup-test@example.invalid']);if(!empty($r['success']))throw new Exception('Suppressed address was subscribed');
 echo "PASS failed suppression and subscriber lookups block subscription; normal and suppressed cases work\n";
} finally {$wpdb->query('ROLLBACK');$wpdb->suppress_errors($old);wp_cache_flush();}
