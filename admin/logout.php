<?php
require dirname(__DIR__) . '/lib/admin_auth.php';

admin_logout();
header('Location: /admin/login0929.php');
exit;
