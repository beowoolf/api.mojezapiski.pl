<?php

header("Content-Type: application/json; charset=UTF-8");

function prepareCurlHandle($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    //curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Obsługuje przekierowania
    curl_setopt($ch, CURLOPT_MAXREDIRS, -1); // Obsługuje nieskończoną ilość przekierowań
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.93 Safari/537.36"); // Ustaw user-agent

    return $ch;
}

function getLinksFromResponse($response) {
    return json_decode($response, true);
}

$firstURL = "https://api.mojezapiski.pl/courses/eduj/";
$secondURL = "https://api.mojezapiski.pl/courses/eduj/links/";

// Inicjalizacja sesji cURL Multi
$mh = curl_multi_init();

$firstCh = prepareCurlHandle($firstURL);
$secondCh = prepareCurlHandle($secondURL);

// Rozpoczęcie asynchronicznych żądań
$handles = [$firstCh, $secondCh];

foreach ($handles as $ch)
    curl_multi_add_handle($mh, $ch);

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

$firstContent = curl_multi_getcontent($firstCh);
$secondContent = curl_multi_getcontent($secondCh);
//file_put_contents("firstContent.txt", $firstContent);
//file_put_contents("secondContent.txt", $secondContent);
$firstLinks = getLinksFromResponse($firstContent);
$secondLinks = getLinksFromResponse($secondContent);

// Zamknij sesję cURL Multi
foreach ($handles as $ch)
    curl_multi_remove_handle($mh, $ch);
curl_multi_close($mh);

function dump_array_to_file($json_name, $array) {
    file_put_contents("$json_name.json", json_encode($array, JSON_UNESCAPED_SLASHES));
}

//dump_array_to_file("firstLinks", $firstLinks);
//dump_array_to_file("secondLinks", $secondLinks);

$final_array = array_values(array_unique(array_merge($firstLinks, $secondLinks)));

echo(json_encode($final_array, JSON_UNESCAPED_SLASHES));
