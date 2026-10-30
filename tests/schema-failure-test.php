<?php
global $wpdb;
$blog=absint($args[0]??0);if(!$blog || !is_multisite())throw new Exception('Invalid fixture');
$blocked=false;
$filter=static function($query)use(&$blocked){
 if (preg_match('/^\s*CREATE\s+TABLE\s+`?[^\s`]*aplists_subscriber_lists`?\s*\(/i',$query)) {
  $blocked=true;return 'SELECT * FROM `aplists_qa_deliberately_missing_table`';
 }
 return $query;
};
add_filter('query',$filter);$previous=$wpdb->suppress_errors(true);
switch_to_blog($blog);
try {
 $table=$wpdb->prefix.'aplists_subscriber_lists';$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));
 $version=get_option('aplists_version',false);
 if(!$blocked||$exists===$table)throw new Exception('Database failure was not simulated');
 if($version!==false)throw new Exception('Failed installation incorrectly recorded as complete');
 echo "PASS failed installation is not marked complete and can retry\n";
}finally{restore_current_blog();remove_filter('query',$filter);$wpdb->suppress_errors($previous);}
