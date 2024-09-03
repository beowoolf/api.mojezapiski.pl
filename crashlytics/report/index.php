<?php

$core = __DIR__."/../core";
$configurations_dir = "$core/configurations";
$functions_dir = "$core/functions";

try {
    require "$configurations_dir/db/pdo_for_crashlytics_db.php";
    if ($pdo_for_crashlytics_db) {
        switch($_SERVER['REQUEST_METHOD']) {
            case "POST":
                $data_in = file_get_contents("php://input");
                $request = json_decode($data_in, true);
                if (isset($request["info"])) {
                    $info = json_encode($request["info"]);
                    $pdo_for_crashlytics_db->prepare('INSERT INTO crashes (info) VALUES (:info);')->execute(array("info" => $info));
                    echo($info);
                } else if (isset($request["events"]) && is_array($request["events"])) {
                    foreach ($request["events"] as $key => $event)
                        $pdo_for_crashlytics_db->prepare('INSERT INTO crashes (info) VALUES (:info);')->execute(array("info" => json_encode($event)));
                    echo ($data_in);
                } else
                    echo(json_encode(array("error" => "No info to save in db")));
                break;
            case "GET":
            default:
                echo(json_encode(array("error" => "Invalid method")));
        }
    }
} catch (PDOException $e) {
    $error_message = 'Błąd sterownika PDO: '.mb_convert_encoding($e->getMessage().' (#'.$e->getCode().') w linii: '.$e->getLine(), 'UTF-8', 'ISO-8859-2');
    error_log("$error_message\n", 3, __DIR__."/app_errors.log");
    echo(json_encode(array("error" => $error_message)));
    exit();
}

?>
