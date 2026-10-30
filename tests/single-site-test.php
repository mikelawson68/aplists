<?php
require dirname(__DIR__). '/artistpro-lists.php';
if(is_multisite())throw new Exception('Expected single-site QA');
global $wpdb;if($wpdb->query('START TRANSACTION')===false)throw new Exception('Transaction unavailable');
try {
 do_action('plugins_loaded');
 $r=APLists_Lifecycle::create_list(['name'=>'Synthetic single-site test','double_opt_in'=>0]);
 if(empty($r['success']))throw new Exception('List creation failed');
 $s=APLists_Lifecycle::upsert_subscriber((int)$r['list_id'],['email'=>'qa-single@example.invalid','source'=>'admin','force_subscribed'=>true]);
 if(empty($s['success']))throw new Exception('Subscriber creation failed');
 echo "PASS single-site list and subscriber workflow\n";
} finally {$wpdb->query('ROLLBACK');wp_cache_flush();}
