<?php

function getJobs($page) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.justjoin.it/v2/user-panel/offers?page=$page&sortBy=published&orderBy=DESC&perPage=100&salaryCurrencies=PLN",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_HTTPHEADER => [
        "Version: 2"
      ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return array("success" => false, "errors" => array("cURL Error #$page:" . $err));
    } else {
        $arr_resp = json_decode($response, true);
        return array("success" => true, "object" => $arr_resp);
    }
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $key = 'justjoin.it';
    $file_db_path = __DIR__."/../.cache.db"; // Prepare path to SQLite database in file.
    $db = new PDO("sqlite:$file_db_path"); // Create (connect to) SQLite database in file.
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode to exceptions.
    $db->exec('CREATE TABLE IF NOT EXISTS cache (key TEXT PRIMARY KEY, value TEXT)');
    $stmt = $db->query("SELECT value FROM cache WHERE key = '$key'");
    $rows = $stmt->fetchAll(PDO::FETCH_CLASS);
    if ((isset($_GET['refresh']) && $_GET['refresh'] === '1') || (count($rows) < 1)) {
        $jobs = array();
        $totalPages = 1;
        for ($i = 1; $i <= $totalPages; $i++) {
            $response = getJobs($i);
            if ($response["success"] === true) {
                $totalPages = $response["object"]["meta"]["totalPages"];
                foreach ($response["object"]["data"] as $k => $v) {
                    //$id = $v["id"];
                    $job = json_encode($v, JSON_UNESCAPED_SLASHES);
                    $jobs[] = $job;
                }
            } else {
                echo(json_encode($response, JSON_UNESCAPED_SLASHES));
                break;
            }
        }

        $final_response = '{"success":true,"list":['.implode(",", $jobs).']}';

        $db->beginTransaction();
        $query = "INSERT OR REPLACE INTO cache (key, value) VALUES (:key, :value)";
        $stmt = $db->prepare($query);
        $stmt->execute(array("key" => $key, "value" => $final_response));
        $db->commit();

        echo($final_response);
    } else echo($rows[0]->value);
    $db = null; // Close file db connection
} catch(PDOException $e) {
    echo(json_encode(array("sucess" => false, "errors" => array('PDO driver error: '.mb_convert_encoding($e->getMessage().' (#'.$e->getCode().') in line: '.$e->getLine(), 'UTF-8', 'ISO-8859-2'))), JSON_UNESCAPED_SLASHES)); // Print PDOException message
}
