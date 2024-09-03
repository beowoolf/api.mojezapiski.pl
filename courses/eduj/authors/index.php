<?php

header("Content-Type: application/json; charset=UTF-8");

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://api.eduj.pl/api/v2/public/author",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_POSTFIELDS => "",
  CURLOPT_COOKIE => "eduj-vp=%255B%2522264%2522%252C%252239%2522%252C%2522252%2522%255D",
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
    $array = json_decode($response, true);
    $out_arr = array();
    foreach ($array as $key => $value)
        $out_arr[] = array(
            "id" => $value["id"],
            "name" => $value["name"],
            "profession" => $value["profession"],
            "url" => "https://eduj.pl/autorzy/{$value["id"]}"
        );
    echo(json_encode(array("success" => true, "list" => $out_arr), JSON_UNESCAPED_SLASHES));
}
