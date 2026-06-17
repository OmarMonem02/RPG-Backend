<?php

return [

  'manifest_version' => 1,

  'mysqldump_binary' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

  'mysql_binary' => env('BACKUP_MYSQL_PATH', 'mysql'),

  'sqlite3_binary' => env('BACKUP_SQLITE3_PATH', 'sqlite3'),

  'max_upload_mb' => (int) env('BACKUP_MAX_UPLOAD_MB', 512),

  'force_php_dump' => (bool) env('BACKUP_FORCE_PHP_DUMP', false),

  'excluded_tables' => [
    'migrations',
    'sessions',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
  ],

];
