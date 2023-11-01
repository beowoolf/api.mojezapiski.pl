<?php

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== "POST") die(json_encode("Only POST as request method is accepted!"));

//if ($_SERVER['CONTENT_TYPE'] !== "application/json") die(json_encode("Only application/json Content-Type accepted!"));

$data_in = file_get_contents("php://input");
if (!$data_in) die(json_encode("There is no data to decode!"));
$request = json_decode($data_in, true);

if (!isset($request["sitemap"]) || !$request["sitemap"]) die(json_encode("Setted field sitemap in JSON is required!"));

if (!filter_var($request["sitemap"], FILTER_VALIDATE_URL)) die(json_encode("Valid sitemap in JSON is required!"));

$page_url = explode("/", $request["sitemap"]);
unset($page_url[count($page_url)-1]);
//$page_url = implode("/", $url_array);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $request["sitemap"],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_POSTFIELDS => "",
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    // echo(json_encode("cURL Error #:" . $err));
} else {
    $xml = simplexml_load_string($response);
    if ($xml === false) {
        // $error = "Failed loading XML: ";
        foreach (libxml_get_errors() as $error) {
            // $error .= "\n", $error->message;
        }
        // echo(json_encode($error));
    } else {
        // print_r($xml);
        $preload_resources = array();
        $preload_resources_final_list = array();
        foreach ($xml->url as $key => $value) {
            $page = $value->loc; // substr($value->loc, 0, -1);
            $url_array = explode("/", $page);
            $url_array[0] = $page_url[0];
            $url_array[2] = $page_url[2];
            $page = implode("/", $url_array);
            //error_log("$page\n", 3, __DIR__."/log.txt");

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $page,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_POSTFIELDS => "",
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            if ($err) {
                // echo(json_encode("cURL Error #2:" . $err));
            } else {
                $arr = explode("/", $page);
                $page = ($arr[count($arr) - 1] ? $arr[count($arr) - 1] : $arr[count($arr) - 2]);
                // file_put_contents(__DIR__ . "/down/$page.html", $response);
                $x = preg_replace("/<body.*/ms", "<body></body></html>", $response);
                if ($x) {
                    $dom = new DomDocument();
                    $dom->loadHTML($x);
                    $links = $dom->getElementsByTagName("link");
                    header("Access-Control-Allow-Origin: *");
                    foreach ($links as $key => $value) {
                        if ($value->hasAttribute("as") === true) {
                            if (!in_array($value->getAttribute("as"), array("script"))) {
                                if ($value->hasAttribute("href") === true) {
                                    // echo($value->getAttribute("href")."\n");
                                    $img_link = $value->getAttribute("href");
                                    $preload_resources[] = $img_link;
                                    $preload_resources_final_list[$img_link] = 1;
                                }
                            }
                        }
                    }
                }
            }
        }
        echo(json_encode(array_keys($preload_resources_final_list), JSON_UNESCAPED_SLASHES));
    }
}
