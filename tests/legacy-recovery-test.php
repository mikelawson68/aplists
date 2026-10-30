<?php
global $wpdb;
if (!is_multisite() || !class_exists('APLists_Schema')) throw new Exception('Fixture missing');
switch_to_blog(8);
try {
    $marker = 'aplists_schema_verified_105';
    delete_option($marker);
    $table = $wpdb->prefix . 'aplists_subscriber_lists';
    $previous = $wpdb->suppress_errors(true);
    $filter = static function ($query) use ($table) {
        if (stripos($query, 'SHOW TABLES LIKE') === 0 && strpos($query, 'subscriber') !== false) return "SELECT NULL";
        if (preg_match('/^\\s*CREATE\\s+TABLE\\s+`?[^\\s`]*aplists_subscriber_lists/i', $query)) return 'SELECT * FROM aplists_qa_deliberately_missing_table';
        return $query;
    };
    add_filter('query', $filter);
    try {
        if (APLists_Schema::ensure()) throw new Exception('Failed repair reported success');
        if (get_option($marker, false)) throw new Exception('Failed repair marked verified');
    } finally {
        remove_filter('query', $filter);
        $wpdb->suppress_errors($previous);
    }
    if (!APLists_Schema::ensure() || !get_option($marker, false)) throw new Exception('Retry failed');
    echo "PASS failed legacy repair remains unverified and successful retry marks verified\n";
} finally { restore_current_blog(); }

