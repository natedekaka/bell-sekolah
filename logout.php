<?php
require_once __DIR__ . '/core/init.php';

require_login();
do_logout();
redirect('login.php');