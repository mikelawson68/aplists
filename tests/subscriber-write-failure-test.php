<?php
require dirname(__DIR__) . '/artistpro-lists.php';
do_action('plugins_loaded');
global $wpdb;
if ($wpdb->query('START TRANSACTION') === false) throw new Exception('Transaction unavailable');
$old = $wpdb->suppress_errors(true);
try {
    $list = APLists_Lifecycle::create_list(['name'=>'Failure test','double_opt_in'=>1]);
    $id=(int)($list['list_id']??0);
    if (!$id) throw new Exception('List fixture failed');
    $first=APLists_Lifecycle::upsert_subscriber($id,['email'=>'fixture@example.invalid']);
    if (empty($first['success'])) throw new Exception('Subscriber fixture failed');
    $table=APLists_Schema::tables()['subscribers'];
    $before=$wpdb->get_row($wpdb->prepare('SELECT confirmation_token, status FROM %i WHERE id=%d',$table,$first['subscriber_id']),ARRAY_A);
    $block=static function($q) use ($table) {
        if (preg_match('/^\s*(INSERT|UPDATE)\s+(?:INTO\s+)?`?'.preg_quote($table,'/').'`?/i',$q)) return 'SELECT * FROM `aplists_intentionally_missing_test_table`';
        return $q;
    };
    add_filter('query',$block);
    try {
        $update=APLists_Lifecycle::upsert_subscriber($id,['email'=>'fixture@example.invalid']);
        $insert=APLists_Lifecycle::upsert_subscriber($id,['email'=>'new@example.invalid']);
    } finally { remove_filter('query',$block); }
    $after=$wpdb->get_row($wpdb->prepare('SELECT confirmation_token, status FROM %i WHERE id=%d',$table,$first['subscriber_id']),ARRAY_A);
    if (!empty($update['success']) || !empty($insert['success']) || isset($update['confirmation_token']) || $before !== $after) throw new Exception('Database write failure was reported as success or altered existing subscriber');
    echo "PASS failed subscriber insert and update report failure and preserve existing token\n";
} finally { $wpdb->query('ROLLBACK'); $wpdb->suppress_errors($old); wp_cache_flush(); }
