<?php

if ($_SERVER['REQUEST_METHOD'] !== "POST") die(json_encode(array("success" => false, "errorMsg" => "Only POST as request method is accepted!")));

$data_in = file_get_contents("php://input");
if (!$data_in) die(json_encode(array("success" => false, "errorMsg" => "There is no data to decode!")));
$request = json_decode($data_in, true);

if (!isset($request["url"]) || !$request["url"]) die(json_encode(array("success" => false, "errorMsg" => "Setted field url is required!")));

header("Content-Type: application/json; charset=UTF-8");
// Wyłączanie raportowania błędów XML
$link = $request["url"];//'https://strefakursow.pl/kursy/rozwoj_osobisty/kurs_asana_od_podstaw_-_zarzadzanie_projektami.html';

libxml_use_internal_errors(true);

// Tworzenie obiektu DOMDocument
$doc = new DOMDocument();

function getErrorReason($dom) {
    $pageDivs = $dom->getElementsByTagName("div");
    foreach ($pageDivs as $key => $value)
        if ($value->hasAttribute("class") == true) {
            $css_classes = explode(" ", $value->getAttribute("class"));
            if (in_array("sciezki-kariery__sticky-menu", $css_classes))
                return str_replace("  "," ",trim(str_replace("\n"," ", str_replace("  ", "", $value->nodeValue))));
        }
    return "";
}

function tryFindByXPath($xpath, $xpath_arr) {
    foreach ($xpath_arr as $key => $value) {
        $nodeList = $xpath->query($value);
        if ($nodeList->length > 0)
            return $nodeList->item(0)->nodeValue;
    }
    return "";
}

$tagsXPath = array(
    '//*[@id="c-tag-navigation__content-wrapper"]/div'
);

function tryReturnDOMNode($xpath, $xpath_arr) {
    foreach ($xpath_arr as $key => $value) {
        $nodeList = $xpath->query($value);
        if ($nodeList->length > 0)
            return $nodeList->item(0);
    }
    return false;
}

function tryReturnContentFromMetaByProperty($dom, $propertyValue) {
    $metaTags = $dom->getElementsByTagName('meta');
    foreach ($metaTags as $meta)
        if ($meta->getAttribute('property')  === $propertyValue)
            return $meta->getAttribute('content');
    return "";
}

function tryReturnTags($xpath, $xpath_arr) {
    $tags = array();
    foreach ($xpath_arr as $key => $value) {
        $nodeList = $xpath->query($value);
        if ($nodeList->length > 0) {
            $domNode = $nodeList->item(0);
            $childNodes = $domNode->childNodes;
            $countOfChildNodes = $childNodes->length;
            for ($i=0; $i < $countOfChildNodes; $i++) { 
                $childNode = $childNodes->item($i);
                $attributes = $childNode->attributes;
                if ($attributes !== null) {
                    $countOfAttributes = $attributes->length;
                    for ($j=0; $j < $countOfAttributes; $j++) { 
                        $attrNode = $attributes->item($j);
                        if ($attrNode->nodeName === "href") {
                            if (strpos($attrNode->nodeValue, "/sciezki_kariery/") === false) {
                                $tags[] = $childNode->nodeValue; // nie znalazł
                            } else {
                                // znalazł
                            }
                        }
                    }
                }
                /*foreach ($attributes as $name => $attrNode) {
                    if ($name == "href") {
                        if (strpos($attrNode->nodeValue, "/sciezki_kariery/") === false) {
                            $tags[] = $childNode->nodeValue; // nie znalazł
                        } else {
                            // znalazł
                        }
                    }
                }*/
            }
            return $tags;
        }
    }
    return $tags;
}

function getProduct($dom, $link) {
    $product_arr = array();
    $og_image = tryReturnContentFromMetaByProperty($dom, 'og:image');
    $priceContainer = $dom->getElementById("price-container");
    if ($priceContainer) {
        $priceContainerDivs = $priceContainer->getElementsByTagName("div");
        foreach ($priceContainerDivs as $key => $value) {
            if ($value->hasAttribute("class") == true) {
                $css_classes = explode(" ", $value->getAttribute("class"));
                if (in_array("desktop", $css_classes)) {
                    if (in_array("new", $css_classes)) {
                        $product_arr["new_price"] = str_replace("zł", "", $value->nodeValue);
                        /*echo("<pre>");
                        var_dump($value->nodeValue);
                        echo("</pre>");*/
                    } else if (in_array("old", $css_classes)) {
                        $product_arr["old_price"] = str_replace("zł", "", $value->nodeValue);
                        /*echo("<pre>");
                        var_dump($value->nodeValue);
                        echo("</pre>");*/
                    }
                }
            }
        }
        $productContainer = $priceContainer->parentNode->parentNode;
    
        $h1_tags_list= $productContainer->getElementsByTagName("h1");
        //$product_arr["a"] = $a_tags_list->count();
        if ($h1_tags_list->count() > 0)
            $product_arr["title"] = $h1_tags_list->item(0)->nodeValue;
        /*echo("<pre>");
        var_dump($product_arr);
        echo("</pre>");*/
        if (isset($product_arr["old_price"])) {
            $product_arr["price"] = $product_arr["old_price"];
            $product_arr["discountedPrice"] = $product_arr["new_price"];
            unset($product_arr["old_price"]);
        } else
            $product_arr["price"] = $product_arr["new_price"];
        unset($product_arr["new_price"]);
        $product_arr["url"] = $link;
        $product_arr['thumbnail'] = "https://strefafilmy.s3.amazonaws.com/product_picture/shop/box/".basename($og_image, ".png").'.jpg';
        $tagsContainer = $dom->getElementById("c-tag-navigation__content-wrapper");
        $aTagsLinks = $tagsContainer->getElementsByTagName("a");
        $categoryNames = array();
        foreach ($aTagsLinks as $key => $value)
            if (strpos($value->hasAttribute("href"), "/sciezki_kariery/") === false)
                $categoryNames[] = $value->nodeValue;
        $product_arr['categoryNames'] = $categoryNames;
        $product_arr["platform"] = array(
            "name" => 'StrefaKursów.pl',
            'logo' => 'https://strefakursow.pl/redesign/assets/images/logo/default-logo-desktop.svg',
            'url' => 'https://strefakursow.pl/',
            'type' => 'sk',
            'version' => '1.0.0',
            'subscriptionMode' => false
        );
    
        $a_tags_list = $productContainer->getElementsByTagName("a");
        foreach ($a_tags_list as $key => $value)
            if ($value->hasAttribute("href") && strpos($value->getAttribute("href"), "https://strefakursow.pl/product/from_author/") !== FALSE) {
                $product_arr["author"]["url"] = $value->getAttribute("href");
                /*echo("<pre>");
                var_dump($value->childNodes);
                echo("</pre>");*/
                foreach ($value->childNodes as $k => $v)
                    if ($v->nodeName == "p")
                        $product_arr["author"]["profession"] = $v->nodeValue;
                $product_arr["author"]["name"] = trim(str_replace($product_arr["author"]["profession"], "", $value->textContent));
            }
        if (!isset($product_arr["author"])) $product_arr["author"] = array("url" => $link, "profession" => "pisarz", "name" => "Gall Anonim");
        else if ($product_arr["author"]["name"] == "Krzysztof Micielski") $product_arr["author"]["profession"] = "Grafik";
    }
    return $product_arr;
}

function getPage($link) {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => $link,
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

$page = getPage($link);
$html = $page["response"];

//file_put_contents("course.html", $html);

if (strlen($html) === 0) die(json_encode(array("success" => false, "object" => array("errorReason" => "Pusty plik HTML"))));

// Wczytywanie pliku https://strefakursow.pl/kursy/rozwoj_osobisty/kurs_asana_od_podstaw_-_zarzadzanie_projektami.html
if ($doc->loadHTML($html)) {
    // Tworzenie obiektu DOMXPath
    $xpath = new DOMXPath($doc);

    // Wyrażenia XPath
    $currentPriceXPath = array(
        '//*[@id="price-container"]/div[1]/div/div[1]',
        '//*[@id="price-container"]/div[1]/div/div[2]/text()'
    );
    //$parsedXPath = '//*[@id="price-container"]/div[1]/div/div[2]/text()';
    //$priceXPath = '//*[@id="price-container"]/div[1]/div/div[1]';
    //$onlyPriceXPath = '//*[@id="price-container"]/div[1]/div/div[2]/text()';
    //$newPriceXPath = '//*[@id="price-container"]/div[1]/div/div[2]/text()';
    $oldPriceXPath = array(
        '//*[@id="price-container"]/div[1]/div/div[3]'
    );
    $titleXPath = array(
        '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[2]/h1',
        '/html/body/div[12]/div[2]/div[2]/div[1]/div/div[2]/h1'
    );
    $authorProfessionXPath = array(
        '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/p',
        '/html/body/div[12]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/p'
    );
    $authorNameXPath = array(
        '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/text()',
        '/html/body/div[12]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/text()'
    );
    $authorProfileURLXPath = array(
        '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/@href',
        '/html/body/div[12]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/@href'
    );

    // Wyszukiwanie ceny
    $currentPrice = tryFindByXPath($xpath, $currentPriceXPath);
    $currentPrice_len = strlen($currentPrice);
    $is_currentPrice = $currentPrice_len > 0;

    // Wyszukiwanie starej ceny
    $oldPrice = tryFindByXPath($xpath, $oldPriceXPath);
    $oldPrice_len = strlen($oldPrice);
    $is_oldPrice = $oldPrice_len > 0;

    // Wyszukiwanie tytułu kursu
    $title = tryFindByXPath($xpath, $titleXPath);
    $title_len = strlen($title);
    $is_title = $title_len > 0;

    // Wyszukiwanie informacji o profesji autora kursu
    $authorProfession = tryFindByXPath($xpath, $authorProfessionXPath);
    $authorProfession_len = strlen($authorProfession);
    $is_authorProfession = $authorProfession_len > 0;

    // Wyszukiwanie imienia i nazwiska autora kursu
    $authorName = tryFindByXPath($xpath, $authorNameXPath);
    $authorName_len = strlen($authorName);
    $is_authorName = $authorName_len > 0;

    // Wyszukiwanie adresu URL do strony z profilem autora
    $authorProfileURL = tryFindByXPath($xpath, $authorProfileURLXPath);
    $authorProfileURL_len = strlen($authorProfileURL);
    $is_authorProfileURL = $authorProfileURL_len > 0;

    // Inicjalizacja tablicy na dane
    $data = array();

    // Sprawdzanie, czy znaleziono węzły
    if ($is_currentPrice && $is_title && $is_authorProfession && $is_authorName && $is_authorProfileURL) {
        $og_image = tryReturnContentFromMetaByProperty($doc, 'og:image');
        /*
            if (isOldPrice) {
                responseMap.put("price", new BigDecimal(oldPriceElements.first().text().replace("zł", "")));
                responseMap.put("discountedPrice", new BigDecimal(newPriceElements.first().text().replace("zł", "")));
            } else
                responseMap.put("price", new BigDecimal(newPriceElements.first().text().replace("zł", "")));
        */
        // Pobieranie zawartości węzłów
        $data['title'] = trim($title);
        if ($is_oldPrice) {
            $data['price'] = intval(str_replace("zł", "", $oldPrice));
            $data['discountedPrice'] = intval(str_replace("zł", "", $currentPrice));
        } else
            $data['price'] = intval(str_replace("zł", "", $currentPrice));
        $data['url'] = $link;
        $data['thumbnail'] = "https://strefafilmy.s3.amazonaws.com/product_picture/shop/box/".basename($og_image, ".png").'.jpg';
        $data['categoryNames'] = tryReturnTags($xpath, $tagsXPath);
        $data['platform'] = array(
            "name" => 'StrefaKursów.pl',
            'logo' => 'https://strefakursow.pl/redesign/assets/images/logo/default-logo-desktop.svg',
            'url' => 'https://strefakursow.pl/',
            'type' => 'sk',
            'version' => '1.0.0',
            'subscriptionMode' => false
        );
        $data['author']['name'] = trim($authorName);
        $data['author']['profession'] = trim($authorProfession);
        $data['author']['url'] = trim($authorProfileURL);
    } else {
        $product = getProduct($doc, $link);
        if (count(array_keys($product)) == 0) {
          $data['error'] = "Nie znaleziono ceny kursu ({$currentPrice_len}), tytułu ({$title_len}), informacji o profesji ({$authorProfession_len}), imienia i nazwiska autora ({$authorName_len}) lub adresu URL do strony z profilem autora ({$authorProfileURL_len}).";
          /*$xpaths = array(
              '/html/body/div[13]/div[2]/div[2]/div[4]/div',
              '/html/body/div[12]/div[2]/div[2]/div[4]/div',// /html/body/div[13]/div[2]/div[2]/div[4]/div
              '/html/body/div[13]/div[2]/div[2]/div[3]/div',// /html/body/div[13]/div[2]/div[2]/div[4]/div
              '/html/body/div[12]/div[2]/div[2]/div[3]/div'
          );
          $data['errorReason'] = str_replace("  "," ",trim(str_replace("\n"," ", str_replace("  ", "", tryFindByXPath($xpath, $xpaths)))));*/
          $data['errorReason'] = getErrorReason($doc);
          if ($data['errorReason'] === "" && $link != $page["effective_url"]) die(json_encode(array("success" => false, "object" => array("errorReason" => "Przekierowanie do strony: " . $page["effective_url"]))));
        } else $data = $product;
    }

    echo json_encode(array("success" => !isset($data['error']), "object" => $data), JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(array("success" => false, "errorMsg" => "Błąd podczas wczytywania zawartości z adresu $link."), JSON_UNESCAPED_SLASHES);
}

// Wyłączanie obsługi błędów XML
libxml_use_internal_errors(false);

?>
