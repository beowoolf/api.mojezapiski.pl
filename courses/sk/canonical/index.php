<?php

function getCanonicalLink($dom) {
    $pageLinks = $dom->getElementsByTagName("link");
    foreach ($pageLinks as $key => $value)
        if ($value->hasAttribute("rel") == true && $value->getAttribute("rel") == "canonical" && $value->hasAttribute("href") == true)
            return $value->getAttribute("href");
    return "";
}

function createDirIfNotExist($path) {
    // Sprawdzenie, czy katalog już istnieje
    if (!is_dir($path)) {
        // Utworzenie katalogu, jeśli nie istnieje
        if (!mkdir($path, 0777, true)) {
            // Jeśli nie udało się utworzyć katalogu, zwracamy false
            return false;
        }
    }
    
    // Jeśli katalog już istniał lub został utworzony, zwracamy true
    return true;
}

function getPage($input_link) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_URL => $input_link,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => 1,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_COOKIE => "products_displayed=%255B%257B%2522id%2522%253A1570%257D%252C%257B%2522id%2522%253A1349%257D%255D",
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    $effective_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    
    if ($err) {
      error_log("cURL Error #:" . $err);
      return "";
    } else {
      return array("response" => $response, "effective_url" => $effective_url);
    }
}

$request = $_REQUEST;

if (!isset($request["id"]) || !$request["id"] || !ctype_digit(strval($request["id"]))) die(json_encode(array("success" => false, "response" => "Setted int field id is required!")));

header("Content-Type: application/json; charset=UTF-8");
// Wyłączanie raportowania błędów XML
$id = $request["id"];

$responses_dir = __DIR__."/responses";
if (createDirIfNotExist($responses_dir) == false)
    die(json_encode(array('success' => false, 'message' => 'Błąd tworzenia katalogu na cache')));

$response_file_name = "$responses_dir/$id.json";
if (file_exists($response_file_name) == true)
    die(file_get_contents($response_file_name));

$url = "https://strefakursow.pl/product/show/$id";//"https://strefakursow.pl/kursy/rozwoj_osobisty/kurs_asana_od_podstaw_-_zarzadzanie_projektami.html";

$page = getPage($url);
$html = $page["response"];
$effective_url = $page["effective_url"];

if (strlen($html) === 0) die(json_encode(array("success" => false, "message" => "Pusty plik HTML")));

libxml_use_internal_errors(true);

// Tworzenie obiektu DOMDocument
$doc = new DOMDocument();
if ($doc->loadHTML($html)) {
    $response = json_encode(array("success" => true, "response" => getCanonicalLink($doc)), JSON_UNESCAPED_SLASHES);
    file_put_contents($response_file_name, $response);
    echo($response);
} else
    echo(json_encode(array("success" => false, "response" => "Error while HTML loading")));
?>
