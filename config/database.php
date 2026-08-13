<?php

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'bell_user';
$password = getenv('DB_PASS') ?: 'bell_pass_2026';
$database = getenv('DB_NAME') ?: 'bell_sekolah';
$port = getenv('DB_PORT') ?: '3306';

define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $password);
define('DB_NAME', $database);
define('DB_PORT', $port);