<?php

function GetKeywordsFromMetaTag($html) {
    // Stworzenie nowego obiektu DOMDocument
    $dom = new DOMDocument();
    // Wyłączenie raportowania błędów związanych z niepoprawnym kodem HTML
    libxml_use_internal_errors(true);
    // Wczytanie kodu HTML
    $dom->loadHTML($html);
    // Włączenie raportowania błędów
    libxml_use_internal_errors(false);

    // Wyodrębnienie tekstu odczytywanego przez czytniki ekranów
    $keywords_to_out = array();
    $xpath = new DOMXPath($dom);

    // Pobierz wartość atrybutu "keywords" z meta danych
    $metaNodes = $xpath->query('//meta[@name="keywords"]');
    foreach ($metaNodes as $metaNode) {
        $keywords_list = $metaNode->getAttribute('content');
        $kewords_to_add = explode(",", $keywords_list);
        for ($i=0; $i < count($kewords_to_add); $i++)
            $keywords_to_out[] = trim($kewords_to_add[$i]);
    }

    // Wyświetlenie tekstu na stronie
    $json = json_encode($keywords_to_out, JSON_UNESCAPED_UNICODE);
    return $json;
}

function ExtractTextFromHtml($html) {
    // Wczytanie pliku HTML
    //$html = file_get_contents('plik.html');

    // Stworzenie nowego obiektu DOMDocument
    $dom = new DOMDocument();
    // Wyłączenie raportowania błędów związanych z niepoprawnym kodem HTML
    libxml_use_internal_errors(true);
    // Wczytanie kodu HTML
    $dom->loadHTML($html);
    // Włączenie raportowania błędów
    libxml_use_internal_errors(false);

    // Wyodrębnienie tekstu odczytywanego przez czytniki ekranów
    $text = '';
    $xpath = new DOMXPath($dom);
    // Wybierz wszystkie teksty wewnątrz elementów
    $textNodes = $xpath->query('//*/text()');
    foreach ($textNodes as $node) {
        // Dodaj tekst do wynikowego ciągu
        //if ($node->nodeType === XML_TEXT_NODE) $text .= $node->nodeValue;
        if ($node->nodeType === XML_TEXT_NODE && strlen(trim($node->nodeValue)) > 0) {
            $text .= /*trim*/($node->nodeValue) . "\n";
        }
    }

    // Pobranie wartości atrybutu "alt" ze znaczników <img>
    $imgNodes = $xpath->query('//img[@alt]');
    foreach ($imgNodes as $imgNode) {
        $altText = $imgNode->getAttribute('alt');
        $text .= $altText . " \n\n";
    }

    // Wyświetlenie tekstu na stronie
    $json = json_encode($text, JSON_UNESCAPED_UNICODE);
    //echo ($json);

    //file_put_contents("out.txt", $json);
    return $json;
}

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== "POST") die(json_encode("Only POST as request method is accepted!"));

//if ($_SERVER['CONTENT_TYPE'] !== "application/json") die(json_encode("Only application/json Content-Type accepted!"));

$data_in = file_get_contents("php://input");
if (!$data_in) die(json_encode("There is no data to decode!"));
$request = json_decode($data_in, true);

if (!isset($request["sitemap"]) || !$request["sitemap"]) die(json_encode("Setted field sitemap in JSON is required!"));

if (!filter_var($request["sitemap"], FILTER_VALIDATE_URL)) die(json_encode("Valid sitemap in JSON is required!"));

$page_url = explode("/", $request["sitemap"]);
unset($page_url[count($page_url) - 1]);
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
        $all_text = "";
        $all_keywords = array();
        $preload_resources = array();
        $preload_resources_final_list = array();
        foreach ($xml->url as $key => $value) {
            $page = $value->loc; // substr($value->loc, 0, -1);
            $url_array = explode("/", $page);
            $url_array[0] = $page_url[0];
            $url_array[2] = $page_url[2];
            $page = implode("/", $url_array);
            //error_log("$page\n", 3, __DIR__ . "/log.txt");

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
                $full_page_url = $page;
                $arr = explode("/", $page);
                $page = ($arr[count($arr) - 1] ? $arr[count($arr) - 1] : $arr[count($arr) - 2]);
                // file_put_contents(__DIR__ . "/down/$page.html", $response);
                $keywords_json = GetKeywordsFromMetaTag($response);
                $keywords_text = json_decode($keywords_json);

                $json = ExtractTextFromHtml($response);
                $text = json_decode($json);
                $all_text = $all_text . $text . "\n\n";
                $obj_for_keyword_api = array("text" => $text);
                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_PORT => "20334",
                    CURLOPT_URL => "http://keywords-api.mojezapiski.pl:20334/",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($obj_for_keyword_api, JSON_UNESCAPED_UNICODE),
                    //CURLOPT_COOKIE => "PHPSESSID=2vd4819ifqp79kr9e64rgdckoq",
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json"
                    ],
                ]);

                //if ($full_page_url === "http://localhost:3000/kadry-i-place/obsluga-deklaracji-pfron/") file_put_contents("out.txt", $text);

                $response = curl_exec($curl);
                $err = curl_error($curl);

                curl_close($curl);

                if ($err) {
                    //echo "cURL Error #:" . $err;
                } else {
                    //echo $response;
                    $obj_from_keyword_api = json_decode($response);
                    if (!$arr[count($arr) - 1])
                        unset($arr[count($arr) - 1]);
                    for ($i = 0; $i < 3; $i++)
                        unset($arr[$i]);
                    $page_key = implode("|", $arr);
                    $keywords = array();
                    foreach ($obj_from_keyword_api as $k => $v) {
                        $keyword = strtolower($v->word);
                        if ((!ctype_digit($keyword))&&(strlen($keyword)>1)&&(!in_array($keyword, $keywords))) {
                            $keywords[] = $keyword;
                            $all_keywords[] = $keyword;
                        }
                    }
                    $obj_to_out = array(
                        "CurrentKeywords" => $keywords_text,
                        "MappedResponse" => $keywords,
                        "OrginResponse" => $obj_from_keyword_api
                    );
                    unset($obj_to_out["OrginResponse"]);
                    $preload_resources_final_list[$page_key] = $obj_to_out;
                }
                //if ($full_page_url === "http://localhost:3000/sprzet-i-serwis/") break;
            }
        }

        $all_text_for_keyword_api = array("text" => $all_text);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_PORT => "20334",
            CURLOPT_URL => "http://keywords-api.mojezapiski.pl:20334/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($all_text_for_keyword_api, JSON_UNESCAPED_UNICODE),
            //CURLOPT_COOKIE => "PHPSESSID=2vd4819ifqp79kr9e64rgdckoq",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
        ]);

        //if ($full_page_url === "http://localhost:3000/kadry-i-place/obsluga-deklaracji-pfron/") file_put_contents("out.txt", $text);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $all_keywords_array = array();
        if ($err) {
            //echo "cURL Error #:" . $err;
        } else {
            //echo $response;
            $new_obj_from_keyword_api = json_decode($response);
            $mapped_keywords = array();
            foreach ($new_obj_from_keyword_api as $k => $v) {
                $keyword = strtolower($v->word);
                if ((!ctype_digit($keyword))&&(strlen($keyword)>1)) {
                    $mapped_keywords[] = $keyword;
                }
            }
            $obj_to_out = array(
                "MappedResponse" => $mapped_keywords,
                "OrginResponse" => $new_obj_from_keyword_api
            );
            //unset($obj_to_out["OrginResponse"]);
            $all_keywords_array = $obj_to_out;
        }

        $pages_to_update = array();
        foreach ($preload_resources_final_list as $key => $value) {
            $current_keywords = $value["CurrentKeywords"];
            $typed_keywords = $value["MappedResponse"];
            $count_of_typed_keywords = count($typed_keywords);
            if ((count($current_keywords) !== $count_of_typed_keywords)&&($count_of_typed_keywords > 0))
                $pages_to_update[$key] = $typed_keywords;
            else
                for ($i=0; $i < count($typed_keywords); $i++)
                    if ($current_keywords[$i] != $typed_keywords[$i])
                        $pages_to_update[$key] = $typed_keywords;
        }
        $all_keywords_u = array();
        foreach ($all_keywords as $k => $v)
            $all_keywords_u[$v] = true;
        header("Access-Control-Allow-Origin: *");
        echo (json_encode(array("pagesToKeywordUpdate" => $pages_to_update, "topOfAllKeywords" => $all_keywords_array, "allKeywords" => array_keys($all_keywords_u), "allPagesInfoAboutKeywords" => $preload_resources_final_list)));
    }
}
