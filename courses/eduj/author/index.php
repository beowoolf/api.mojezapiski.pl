<?php

header("Content-Type: application/json; charset=UTF-8");

/*if ($_SERVER['REQUEST_METHOD'] !== "POST") die(json_encode(array("success" => false, "errorMsg" => "Only POST as request method is accepted!")));

$data_in = file_get_contents("php://input");
if (!$data_in) die(json_encode(array("success" => false, "errorMsg" => "There is no data to decode!")));
$request = json_decode($data_in, true);*/
$request = $_REQUEST;

if (!isset($request["id"]) || !$request["id"]) die(json_encode(array("success" => false, "errorMsg" => "Setted field id is required!")));

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.eduj.pl/api/v2/public/author/{$request["id"]}",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_POSTFIELDS => "",
  CURLOPT_COOKIE => "PHPSESSID=85gdli12hla7h5t4vckvh94t83; eduj-vp=%255B%2522264%2522%255D",
  CURLOPT_HTTPHEADER => [
    "User-Agent: insomnia/2023.5.8"
  ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo json_encode(array("success" => false, "errorMsg" => "cURL Error #:" . $err));
} else {
    $res_arr = json_decode($response, true);
    $out_arr = array(
        "id" => $res_arr["id"],
        "name" => $res_arr["name"],
        "profession" => $res_arr["profession"],
        "url" => "https://eduj.pl/autorzy/{$res_arr["id"]}"
    );
    echo(json_encode(array("success" => true, "object" => $out_arr), JSON_UNESCAPED_SLASHES));
}
