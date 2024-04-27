<?php

header("Content-Type: application/json; charset=UTF-8");

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
                if ($productsNodes) {
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
                    echo(json_encode(array("success" => true, "products" => $productAttributesArray)));
                    break;
                }
            }
        } else
            echo(json_encode(array("success" => false, "message" => "Error while get product-view-box childNodes")));
    } else
        echo(json_encode(array("success" => false, "message" => "Error while getElementId operation for id product-view-box")));
} else
    echo(json_encode(array("success" => false, "message" => "Error while HTML loading")));

?>