<?php

require __DIR__."/crashlytics_db_drv_name.php";
require __DIR__."/crashlytics_db_host_address.php";
require __DIR__."/crashlytics_db_host_port.php";
require __DIR__."/crashlytics_db_name.php";
require __DIR__."/crashlytics_db_user_name.php";
require __DIR__."/crashlytics_db_user_password.php";
require __DIR__."/options_for_crashlytics_db_pdo.php";
require_once __DIR__."/../../functions/create_new_pdo.php";

$pdo_for_crashlytics_db = create_new_pdo(
    $crashlytics_db_drv_name,
    $crashlytics_db_host_address,
    $crashlytics_db_host_port,
    $crashlytics_db_name,
    $crashlytics_db_user_name, $crashlytics_db_user_password,
    $options_for_crashlytics_db_pdo
);

?>
