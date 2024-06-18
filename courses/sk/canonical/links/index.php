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

function getSkLatestCoursePage() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_URL => "https://strefakursow.pl/najnowsze_kursy.html",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_COOKIE => "cfv=1714310165; guest_cart_id=84007122; guest_cart_key=453e73b625e1cac1e985eafd0ccb6d63; SID=c12d729f0a4b0697fefb49a9427dc5f1",
      CURLOPT_HTTPHEADER => [
        "User-Agent: insomnia/9.0.0"
      ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err)
        return array("success" => false, "message" => "cURL Error #: $err");
    else
        return array("success" => true, "response" => $response);
}

function getContentBetweenParentheses($string) {
    $matches = [];
    // Używamy wyrażenia regularnego do dopasowania treści między nawiasami
    if (preg_match('/\(.*\)/', $string, $matches)) {
        // Zwracamy zawartość między nawiasami
        return $matches[0];
    } else {
        // Jeśli nie udało się znaleźć treści między nawiasami, zwracamy pusty string lub możemy rzucić wyjątek
        return '';
    }
}

function getByClassName($dom, $className) {
    $pageDivs = $dom->getElementsByTagName("div");
    foreach ($pageDivs as $key => $value)
        if ($value->hasAttribute("class") == true) {
            $css_classes = explode(" ", $value->getAttribute("class"));
            if (in_array($className, $css_classes))
                return str_replace("  "," ",str_replace("  "," ",str_replace("  "," ",str_replace("  "," ",trim(str_replace("\r","", str_replace("\n"," ", str_replace("  ", "", $value->nodeValue))))))));
        }
    return "";
}

function getPromoBannerTextFromHtmlCode($html) {
    if (!$html) return "";
    // Ustaw poziom raportowania błędów na cichy
    $previousErrorReporting = error_reporting();
    error_reporting(0);

    // Utwórz parser DOM
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD); // Używamy '@' przed loadHTML, aby ukryć ostrzeżenia

    // Przywróć poprzedni poziom raportowania błędów
    error_reporting($previousErrorReporting);
    
    // Znajdź div-y o określonej klasie i usuń je
    //$xpath = new DOMXPath($dom);
    return getByClassName($dom, "b-promo-bar-main");
}

function getLatestDirectly() {
    $response = getSkLatestCoursePage();
    
    if ($response["success"] == false)
        die(json_encode($response));
    
    $html = $response["response"];
    
    libxml_use_internal_errors(true);
    
    // Tworzenie obiektu DOMDocument
    $doc = new DOMDocument();
    
    if ($doc->loadHTML($html)) {
        $productViewBox = $doc->getElementById("product-view-box");
        if ($productViewBox) {
            $productViewBoxChildNodes = $productViewBox->childNodes;
            if ($productViewBoxChildNodes->length > 0) {
                for ($a=0; $a < $productViewBoxChildNodes->length; $a++) { 
                    $divContainer = $productViewBoxChildNodes->item($a);
                    $productsNodes = $divContainer->childNodes;
                    if ($productsNodes && $productsNodes->length) {
                        $productAttributesArray = array();
                        $countOfProducts = $productsNodes->length;
                        for ($i=0; $i < $countOfProducts; $i++) {
                            $productDiv = $productsNodes->item($i);
                            $productAttributes = $productDiv->attributes;
                            if ($productAttributes !== null) {
                                $countOfProductAttributes = $productAttributes->length;
                                for ($j=0; $j < $countOfProductAttributes; $j++) { 
                                    $attrNode = $productAttributes->item($j);
                                    if ($attrNode->nodeName === "onclick") {
                                        $attrNodeValue = $attrNode->nodeValue;
                                        $str = getContentBetweenParentheses($attrNodeValue);
                                        $valid_json = str_replace("'", '"', "[" . substr($str, 1, strlen($str) - 2) . "]");
                                        $array = json_decode($valid_json, true);
                                        $product = array();
                                        $product["id"] = $array[0];
                                        $product["title"] = $array[1];
                                        $product["categories"] = array();
                                        for ($b=2; $b < 5; $b++)
                                            if($array[$b] != "brak")
                                                $product["categories"][] = $array[$b];
                                        $product["author"] = $array[5];
                                        $product["position"] = $array[6];
                                        $product["price"] = $array[7];
                                        $productChilds = $productDiv->childNodes;
                                        $countOfProductChilds = $productChilds->length;
                                        for ($k=0; $k < $countOfProductChilds; $k++) { 
                                            $productChildNode = $productChilds->item($k);
                                            $productChildAttributes = $productChildNode->attributes;
                                            if ($productChildAttributes !== null) {
                                                $countOfProductChildAttributes = $productChildAttributes->length;
                                                for ($l=0; $l < $countOfProductChildAttributes; $l++) { 
                                                    $productChildAttrNode = $productChildAttributes->item($l);
                                                    if ($productChildAttrNode->nodeName === "href")
                                                        $product["url"] = "https://strefakursow.pl".$productChildAttrNode->nodeValue;
                                                }
                                            }
                                        }
                                        $productAttributesArray[] = $product;
                                    }
                                }
                            }
                        }
                        return (json_encode(array("success" => true, "products" => $productAttributesArray)));
                        break;
                    }
                }
            } else
                return (json_encode(array("success" => false, "message" => "Error while get product-view-box childNodes")));
        } else
            return (json_encode(array("success" => false, "message" => "Error while getElementId operation for id product-view-box")));
    } else
        return (json_encode(array("success" => false, "message" => "Error while HTML loading")));
}

header("Content-Type: application/json; charset=UTF-8");

$latest_response = getLatestDirectly();
$latest_arr = json_decode($latest_response, true);
if ($latest_arr["success"] == false || !$latest_arr["products"])
    die(json_encode(array("success" => false, "message" => "Error while get latest products")));

$latest_id = $latest_arr["products"][0]["id"];

$responses_dir = __DIR__."/../responses";
if (createDirIfNotExist($responses_dir) == false)
    die(json_encode(array('success' => false, 'message' => 'Błąd tworzenia katalogu na cache')));

// Wyłączanie raportowania błędów XML
libxml_use_internal_errors(true);

$responses = array();

for ($id=1; $id <= $latest_id; $id++) {
    $response_file_name = "$responses_dir/$id.json";
    if (file_exists($response_file_name) == true)
        $responses[] = json_decode(file_get_contents($response_file_name), true);
    else {
        $url = "https://strefakursow.pl/product/show/$id";
        $page = getPage($url);
        $html = $page["response"];
        $effective_url = $page["effective_url"];
        if (strlen($html) === 0)
            $responses[] = array("success" => false, "message" => "Pusty plik HTML");
        // Tworzenie obiektu DOMDocument
        $doc = new DOMDocument();
        if ($doc->loadHTML($html)) {
            $response = json_encode(array("success" => true, "response" => getCanonicalLink($doc)), JSON_UNESCAPED_SLASHES);
            file_put_contents($response_file_name, $response);
            $responses[] = json_decode($response, true);
        } else
           $responses[] = array("success" => false, "response" => "Error while HTML loading");
    }
}

$urls = array();
foreach ($responses as $key => $value)
    if (filter_var($value["response"], FILTER_VALIDATE_URL))
        if (strpos($value["response"], "https://strefakursow.pl/kursy/") !== false)
            $urls[] = $value["response"];
echo(json_encode($urls, JSON_UNESCAPED_SLASHES));

?>
