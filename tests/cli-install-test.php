<?php
require dirname(__DIR__) . '/artistpro-lists.php';
if (getenv('APLISTS_TEST_FAILURE') === '1') {
    add_filter('query', static function ($query) {
        if (stripos($query, 'SHOW TABLES LIKE') === 0 && strpos($query, 'aplists') !== false) return 'SELECT NULL';
        return $query;
    });
}
(new APLists_CLI())->install();
