<?php
global $wpdb;
if (!is_multisite() || !class_exists('APLists_Lifecycle')) throw new Exception('Network plugin unavailable');
if ($wpdb->query('START TRANSACTION')===false) throw new Exception('Transaction unavailable');
try {
    $ids=[];
    foreach ([1,2,3] as $blog) {
        switch_to_blog($blog);
        try {
            $r=APLists_Lifecycle::create_list(['name'=>'Synthetic network fixture '.$blog,'double_opt_in'=>0]);
            if(empty($r['success']))throw new Exception('List creation failed on site '.$blog);
            $ids[$blog]=(int)$r['list_id'];
            $s=APLists_Lifecycle::upsert_subscriber($ids[$blog],['email'=>'qa-site-'.$blog.'@example.invalid','source'=>'admin','force_subscribed'=>true]);
            if(empty($s['success']))throw new Exception('Subscriber save failed on site '.$blog);
        } finally { restore_current_blog(); }
    }
    foreach ([1,2,3] as $blog) {
        switch_to_blog($blog);
        try {
            $table=APLists_Schema::tables()['subscribers'];
            $own=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE email=%s',$table,'qa-site-'.$blog.'@example.invalid'));
            $other=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE email=%s',$table,'qa-site-'.($blog===1?2:1).'@example.invalid'));
            if ($own!==1 || $other!==0) throw new Exception('Cross-site isolation failed on '.$blog);
        } finally { restore_current_blog(); }
    }
    echo "PASS isolated lists and subscribers on three network sites\n";
} finally { $wpdb->query('ROLLBACK');wp_cache_flush(); }
