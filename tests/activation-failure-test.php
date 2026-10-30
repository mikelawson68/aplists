<?php
require dirname(__DIR__). '/artistpro-lists.php';
global $wpdb;$blocked=false;
$filter=static function($query)use(&$blocked){if(preg_match('/^\s*CREATE\s+TABLE\s+`?[^\s`]*aplists_subscriber_lists`?\s*\(/i',$query)){$blocked=true;return 'SELECT * FROM `aplists_qa_deliberately_missing_table`';}return $query;};
add_filter('query',$filter);add_filter('wp_die_handler',static fn()=>static function(){throw new RuntimeException('activation_failed');});
$prior=$wpdb->suppress_errors(true);$rejected=false;
try {APLists_Schema::activate();}catch(RuntimeException $e){if($e->getMessage()!=='activation_failed')throw $e;$rejected=true;}
finally{remove_filter('query',$filter);$wpdb->suppress_errors($prior);}
if(!$blocked||!$rejected||get_option('aplists_version',false)!==false)throw new Exception('Failed activation was not safely rejected');
echo "PASS activation rejects incomplete database installation\n";
