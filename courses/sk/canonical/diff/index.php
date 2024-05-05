<?php

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

$links_from_sitemap = getPage("https://api.mojezapiski.pl/courses/sk/");
if (!$links_from_sitemap || strpos($links_from_sitemap["response"], "[") === false)
    die(json_encode(array("success" => false, "message" => "Error while get links from sitemap")));

$all_canonical_response = getPage("http://localhost/api/courses/sk/canonical/all/");
if (!$all_canonical_response || strpos($all_canonical_response["response"], "[") === false)
    die(json_encode(array("success" => false, "message" => "Error while get canonical links from canonical/all")));

$diff = array();
$sitemap_arr = json_decode($links_from_sitemap["response"], true);
$canonical_arr = json_decode($all_canonical_response["response"], true);
foreach ($canonical_arr as $key => $value)
    if (strpos($value["response"], "https://strefakursow.pl/kursy/") === 0 && !in_array($value["response"], $sitemap_arr))
        $diff[] = $value["response"];

echo(json_encode($diff, JSON_UNESCAPED_SLASHES));

?>
