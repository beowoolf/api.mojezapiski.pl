<?php

// Rozpoczęcie pomiaru czasu
$start_time = microtime(true);

if ($_SERVER['REQUEST_METHOD'] !== "POST") die(json_encode("Only POST as request method is accepted!"));

//if ($_SERVER['CONTENT_TYPE'] !== "application/json") die(json_encode("Only application/json Content-Type accepted!"));

$data_in = file_get_contents("php://input");
if (!$data_in) die(json_encode("There is no data to decode!"));
$request = json_decode($data_in, true);

if (!isset($request["url"]) || !$request["url"]) die(json_encode("Setted field url in JSON is required!"));

if (!filter_var($request["url"], FILTER_VALIDATE_URL)) die(json_encode("Valid url in JSON is required!"));

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Ustawienia zapytania cURL
$url = $request["url"];
$timeout = 39; // timeout milliseconds
$cacheTable = 'cache.db'; // Nazwa pliku bazy SQLite

// Inicjalizacja sesji cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);

// Wykonaj zapytanie cURL
$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Zamknij sesję cURL
curl_close($ch);

$db = new SQLite3($cacheTable);
$db->exec('CREATE TABLE IF NOT EXISTS cache (url TEXT PRIMARY KEY, response TEXT)');

$case_numer = 0;
if ($status_code === 200 && $response !== false) {
    // Przygotuj zapytanie SQL
    $query = "INSERT OR REPLACE INTO cache (url, response) VALUES (:url, :response)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':url', $url, SQLITE3_TEXT);
    $stmt->bindParam(':response', $response, SQLITE3_TEXT);

    // Wykonaj zapytanie
    $result = $stmt->execute();

    if ($result) {
        $case_numer = 1;
    } else {
        $case_numer = 2;
    }

    echo "$response";
} else { // W przypadku błędu lub timeoutu, wyciągnij poprzedni wynik z bazy SQLite
    // Przygotuj zapytanie SQL
    $query = "SELECT response FROM cache WHERE url = :url";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':url', $url, SQLITE3_TEXT);

    // Wykonaj zapytanie
    $result = $stmt->execute();

    if ($result) {
        $row = $result->fetchArray();
        if ($row) {
            $case_numer = 3;
            echo $row['response'];
        } else {
            $case_numer = 4;
        }
    } else {
        $case_numer = 5;
    }
}

// Zamknij połączenie z bazą SQLite
$db->close();

// Zakończenie pomiaru czasu
$end_time = microtime(true);
$execution_time = (int)(($end_time - $start_time) * 1000); // Czas w milisekundach

$error_msg = "";

switch ($case_numer) {
    case 1:
        $error_msg = "Zapytanie zakończone sukcesem. Wynik zapisany w bazie SQLite.";
        break;
    case 2:
        $error_msg = "Błąd podczas zapisywania wyniku zapytania w bazie SQLite.";
        break;
    case 3:
        $error_msg = "Wystąpił błąd lub timeout. Wykorzystano poprzedni wynik z bazy SQLite.";
        break;
    case 4:
        $error_msg = "Wystąpił błąd lub timeout, ale nie znaleziono poprzedniego wyniku w bazie SQLite.";
        break;
    case 5:
        $error_msg = "Błąd podczas pobierania poprzedniego wyniku z bazy SQLite.";
        break;
}

if (in_array($case_numer, array(1,2,3,4,5)))
    error_log("PW_ERR ({$execution_time} ms): $error_msg");

?>
