<?php

header("Access-Control-Allow-Origin: *");

function createTablesWithStartDataInDB($pdo) {
  $commands = ['CREATE TABLE IF NOT EXISTS car_info (
    attribute_key VARCHAR (51) NOT NULL UNIQUE,
    attribute_val VARCHAR (10) NOT NULL
  );',
  'CREATE TABLE IF NOT EXISTS departure_register (
    counter_before_journey INTEGER NOT NULL UNIQUE,
    counter_after_journey INTEGER NOT NULL UNIQUE,
    driver VARCHAR (5)
  );'];

  // execute the sql commands to create new tables
  foreach ($commands as $command)
      $pdo->exec($command);
  
  $stmt = $pdo->query("SELECT attribute_val FROM car_info WHERE attribute_key = 'current_counter_value'");
  $rows = $stmt->fetchAll(PDO::FETCH_CLASS);
  if (count($rows) == 0)
    $pdo->exec("INSERT INTO car_info (attribute_key, attribute_val) VALUES ('current_counter_value', '0');");
}

try {
	$file_db_path = __DIR__."/.my-car.sqlite3"; // Prepare path to SQLite database in file.
	$file_db = new PDO("sqlite:$file_db_path"); // Create (connect to) SQLite database in file.
	$file_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode to exceptions.
	switch($_SERVER['REQUEST_METHOD']) {
    case "POST":
      $data_in = file_get_contents("php://input");
      $request = json_decode($data_in, true);
      require "admin_password.php";
      if (isset($_SERVER['HTTP_API_KEY_PASSWORD']) && $_SERVER['HTTP_API_KEY_PASSWORD'] == $admin_password) {
        if (isset($request["driver"])) {
          $before = 0;
          $after = 0;
          $distance = 0;
          if (isset($request["before"]) && ctype_digit(strval($request["before"])) && isset($request["after"]) && ctype_digit(strval($request["after"]))) {
            $before = intval($request["before"]);
            $after = intval($request["after"]);
            $distance = $after - $before;
          } else if (isset($request["distance"]) && ctype_digit(strval($request["distance"])) && isset($request["after"]) && ctype_digit(strval($request["after"]))) {
            $distance = intval($request["distance"]);
            $after = intval($request["after"]);
            $before = $after - $distance;
          } else if (isset($request["before"]) && ctype_digit(strval($request["before"])) && isset($request["distance"]) && ctype_digit(strval($request["distance"]))) {
            $before = intval($request["before"]);
            $distance = intval($request["distance"]);
            $after = $before + $distance;
          }
          $file_db->beginTransaction();
          $stmt = $file_db->query("SELECT attribute_val FROM car_info WHERE attribute_key = 'current_counter_value'");
          $rows = $stmt->fetchAll(PDO::FETCH_CLASS);
          $current_counter_value = $rows[0]->attribute_val;
          if ($before == $current_counter_value) {
            if ($after > $current_counter_value) {
              if ($distance > 0) {
                $ins_sql = "INSERT INTO departure_register (counter_before_journey, counter_after_journey, driver) VALUES (:counter_before_journey, :counter_after_journey, :driver)";
                $ins_obj = array(
                  "counter_before_journey" => $before,
                  "counter_after_journey" => $after,
                  "driver" => $request["driver"]
                );
                $ins_stmt = $file_db->prepare($ins_sql);
                $ins_stmt->execute($ins_obj);
                if ($ins_stmt->rowCount()) {
                  $upd_sql = "UPDATE car_info SET attribute_val = :current_counter_value WHERE attribute_key = 'current_counter_value';";
                  $upd_obj = ["current_counter_value" => $after];
                  $upd_stmt = $file_db->prepare($upd_sql);
                  $upd_stmt->execute($upd_obj);
                  if ($upd_stmt->rowCount()) {
                    $file_db->commit();
                    echo(json_encode(array("msg" => "Journey added :)")));
                  }
                  else {
                    echo(json_encode(array("errors" => array("Error on update"))));
                    $file_db->rollback();
                  }
                } else {
                  echo(json_encode(array("errors" => array("Error on insert"))));
                  $file_db->rollback();
                }
              } else {
                $file_db->rollback();
                echo(json_encode(array("errors" => array("Journey distance should be greater than zero."))));
              }
            } else {
              $file_db->rollback();
              echo(json_encode(array("errors" => array("The counter value after the journey should be greater than its last value."))));
            }
          } else {
            $file_db->rollback();
            echo(json_encode(array("errors" => array("Before the journey, the counter value should be the same as its last value."))));
          }
        } else
          echo(json_encode(array("errors" => array("Driver required!"))));
      } else
        echo(json_encode(array("errors" => array("Valid password required in API-Key-Password header!"))));
      break;
    case "GET": // The request is using the GET method
      $car_info_stmt = $file_db->query("SELECT attribute_val FROM car_info WHERE attribute_key = 'current_counter_value'");
      $rows = $car_info_stmt->fetchAll(PDO::FETCH_CLASS);
      $current_counter_value = $rows[0]->attribute_val;
      $counter = array("counter" => $current_counter_value);
      $driver_stats = array();
      if (isset($_GET["driver"])) {
        $sql = "SELECT SUM(counter_after_journey - counter_before_journey) FROM departure_register WHERE driver = ?";
        $driver_stats_stmt = $file_db->prepare($sql);
        $driver_stats_stmt->execute(array($_GET["driver"]));
        $results = $driver_stats_stmt->fetchAll(PDO::FETCH_NUM);
        $driver_stats = array("driver" => (isset($results[0][0]) ? $results[0][0] : 0));
      }
			echo(
				json_encode(
          array_merge($counter, $driver_stats)
				)
			);
			break;
		default:
      echo(
        json_encode(
          array(
            "errors" => array(
              "Unsupported method"
            )
          )
        )
      );
	}
	$file_db = null; // Close file db connection
}
catch(PDOException $e) {
  createTablesWithStartDataInDB($file_db);
	echo(json_encode(array("errors" => array('PDO driver error: '.mb_convert_encoding($e->getMessage().' (#'.$e->getCode().') in line: '.$e->getLine(), 'UTF-8', 'ISO-8859-2'))))); // Print PDOException message
}
?>
