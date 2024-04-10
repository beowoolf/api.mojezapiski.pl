<?php

function convertStringArrayToIntArray($array) {
    $new_array = array();
    foreach ($array as $k => $v)
        $new_array[] = intval($v);
    return $new_array;
}

header("Content-Type: application/json; charset=UTF-8");

/*if ($_SERVER['REQUEST_METHOD'] !== "POST") die(json_encode(array("success" => false, "errorMsg" => "Only POST as request method is accepted!")));

$data_in = file_get_contents("php://input");
if (!$data_in) die(json_encode(array("success" => false, "errorMsg" => "There is no data to decode!")));
$request = json_decode($data_in, true);*/
$request = $_REQUEST;

if (!isset($request["sku"]) || !$request["sku"]) die(json_encode(array("success" => false, "errorMsg" => "Setted field sku is required!")));

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://ecommerce.eduj.pl/rest/V1/products/{$request["sku"]}",
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
    if (isset($res_arr["message"]) && $res_arr["message"] == "Produkt nie istnieje. Zweryfikuj produkt i spróbuj ponownie.")
        die(json_encode(array("success" => false, "object" => array("errorReason" => "usunięto"))));
    $out_arr = array(
        "title" => trim($res_arr["name"]),
        "price" => $res_arr["price"],
        //"url" => "https://eduj.pl/produkt/{$request["sku"]}",
        //"sku" => "https://eduj.pl/produkt/{$res_arr["sku"]}",
        "url" => "https://eduj.pl/produkt/{$res_arr["sku"]}",
        "platform" => array(
            "name" => "Eduj",
            "logo" => "https://eduj.pl/assets/images/logo-eduj.svg",
            "url" => "https://eduj.pl/",
            "type" => "eduj",
            "version" => "1.0.0",
            "subscriptionMode" => false
        )
    );
    if (isset($res_arr["extension_attributes"]["discounted_price"])) $out_arr["discountedPrice"] = $res_arr["extension_attributes"]["discounted_price"];
    foreach ($res_arr["custom_attributes"] as $key => $value)
        if ($value["attribute_code"] === "author") {
            $out_arr["authorId"] = intval($value["value"]);
            //break;
        } else if ($value["attribute_code"] === "image_url") $out_arr["thumbnail"] = str_replace("/thumbnail.jpeg","/thumbnail_848x480.jpeg", $value["value"]);
        else if ($value["attribute_code"] === "category_ids") $out_arr["categoryIds"] = convertStringArrayToIntArray($value["value"]);
        else if ($value["attribute_code"] === "description") $out_arr["description"] = strip_tags($value["value"]);
    echo(json_encode(array("success" => true, "object" => $out_arr), JSON_UNESCAPED_SLASHES));
}
