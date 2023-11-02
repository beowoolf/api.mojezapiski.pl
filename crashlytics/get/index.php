<?php

header("Access-Control-Allow-Origin: *");
// header("Content-Type: application/json; charset=UTF-8");
// header("Access-Control-Allow-Methods: POST");
// header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
                require "$configurations_dir/db/users_for_crashlytics.php";
                if (isset($request["login"]) && isset($request["password"]) && isset($users_for_crashlytics[$request["login"]]) && $request["password"] == $users_for_crashlytics[$request["login"]]) {
                    $stmt = $pdo_for_crashlytics_db->query("SELECT * FROM crashes ORDER BY id;");
                  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($data as $key => $value)
                        $data[$key] = array("id" => $value["id"], "info" => json_decode($value["info"], true));
                    echo(json_encode($data));
                } else
                    echo(json_encode(array("error" => "Forbidden")));
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
