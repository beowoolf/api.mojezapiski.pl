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

function getSkLatestCourses() {
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
                        return(json_encode(array("success" => true, "products" => $productAttributesArray)));
                    }
                }
            } else
                return(json_encode(array("success" => false, "message" => "Error while get product-view-box childNodes")));
        } else
            return(json_encode(array("success" => false, "message" => "Error while getElementId operation for id product-view-box")));
    } else
        return(json_encode(array("success" => false, "message" => "Error while HTML loading")));
}

function fetchSitemapLink() {
    // Ustawienia zapytania cURL
    $url = 'https://strefakursow.pl/sitemap.xml';
    $directory_sepatator = DIRECTORY_SEPARATOR;
    $current_dir = __DIR__;
    if (strpos($current_dir, "detector{$directory_sepatator}v")!= false) {
        //error_log("$current_dir jest wersjonowane");
        $current_dir = "$current_dir$directory_sepatator..";
    } /*else
        error_log("$current_dir NIE jest wersjonowane");*/
    $cacheTable = "$current_dir$directory_sepatator..{$directory_sepatator}cache_strefakursow.db"; // Nazwa pliku bazy SQLite
    $strefakursowTable = 'strefakursow';
    
    // Inicjalizacja bazy SQLite
    $db = new SQLite3($cacheTable);
    
    // Tworzenie tabeli "strefakursow", jeśli nie istnieje
    $db->exec("CREATE TABLE IF NOT EXISTS $strefakursowTable (url TEXT PRIMARY KEY)");
    
    // Inicjalizacja sesji cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Wykonaj zapytanie cURL
    $response = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Zamknij sesję cURL
    curl_close($ch);
    
    if ($status_code === 200 && $response !== false) {
        // Przetwarzanie pliku XML
        $xml = simplexml_load_string($response);
    
        if ($xml !== false) {
            // Wyciągnij adresy URL z pliku sitemap.xml
            $urls = $xml->url;
    
            if (count($urls) > 0) {
                // Przygotuj listę adresów URL, które pasują do wyrażenia regularnego
                $filteredUrls = [];
                
                foreach ($urls as $url) {
                    $urlString = (string)$url->loc;
    
                    if (preg_match("~\/kursy\/.*\/.*\.html~", $urlString)) {
                        $filteredUrls[] = $urlString;
                    }
                }
    
                if (count($filteredUrls) > 0) {
                    // Pobierz aktualne adresy URL z bazy SQLite
                    $existingUrls = [];
                    $result = $db->query("SELECT url FROM $strefakursowTable");
    
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $existingUrls[] = $row['url'];
                    }
    
                    // Wyklucz istniejące adresy, które nie pasują do wyrażenia regularnego
                    $filteredUrls = array_diff($filteredUrls, $existingUrls);
    
                    if (count($filteredUrls) > 0) {
                        // Dodaj nowe adresy do bazy SQLite
                        $stmt = $db->prepare("INSERT OR REPLACE INTO $strefakursowTable (url) VALUES (:url)");
    
                        foreach ($filteredUrls as $urlString) {
                            $stmt->bindValue(':url', $urlString, SQLITE3_TEXT);
                            $stmt->execute();
                        }
    
                        error_log("Pobrano i zaktualizowano adresy z sitemap.xml w bazie SQLite.");
                    } else {
                        error_log("Sitemap.xml nie zawiera nowych adresów URL pasujących do wyrażenia regularnego.");
                    }
                } else {
                    error_log("Sitemap.xml nie zawiera nowych adresów URL pasujących do wyrażenia regularnego.");
                }
    
                // Zwróć adresy URL zapisane w bazie SQLite, które pasują do wyrażenia regularnego
                $result = $db->query("SELECT url FROM $strefakursowTable");
                $urls = [];
    
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $urlString = $row['url'];
                    
                    if (preg_match("~\/kursy\/.*\/.*\.html~", $urlString)) {
                        $urls[] = $urlString;
                    }
                }
    
                return $urls;
            } else {
                error_log("Sitemap.xml nie zawiera adresów URL.");
                return [];
            }
        } else {
            error_log("Błąd podczas przetwarzania pliku XML.");
    
            // Spróbuj zwrócić zapisane adresy URL z tabeli "strefakursow" bazy SQLite, które pasują do wyrażenia regularnego, w formacie JSON
            $result = $db->query("SELECT url FROM $strefakursowTable");
            $urls = [];
    
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $urlString = $row['url'];
    
                if (preg_match("~\/kursy\/.*\/.*\.html~", $urlString)) {
                    $urls[] = $urlString;
                }
            }
    
            return $urls;
        }
    } else {
        // Błąd podczas pobierania sitemap.xml - logowanie i próba odczytu danych z bazy SQLite
        error_log("Błąd podczas pobierania sitemap.xml.");
    
        // Spróbuj zwrócić zapisane adresy URL z tabeli "strefakursow" bazy SQLite, które pasują do wyrażenia regularnego, w formacie JSON
        $result = $db->query("SELECT url FROM $strefakursowTable");
        $urls = [];
    
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $urlString = $row['url'];
    
            if (preg_match("~\/kursy\/.*\/.*\.html~", $urlString)) {
                $urls[] = $urlString;
            }
        }
    
        return $urls;
    }
    
    // Zamknij połączenie z bazą SQLite
    $db->close();
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

function getPromoBannerTextAndNewCoursesFromHtmlCode($html) {
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
    $promoBarText = getByClassName($dom, "b-promo-bar-main");
    $newCoursesParent2 = $dom->getElementById("js-scroll-box-tag2");
    $newCoursesParent3 = $dom->getElementById("js-scroll-box-tag3");
    return $promoBarText
        .$newCoursesParent2->nodeValue
        .$newCoursesParent3->nodeValue;
}

function compareHtmlFiles($file1, $file2) {
    $html1 = @file_get_contents($file1);
    $html2 = @file_get_contents($file2);
    $res1 = getPromoBannerTextAndNewCoursesFromHtmlCode($html1);
    $res2 = getPromoBannerTextAndNewCoursesFromHtmlCode($html2);
    
    // Porównaj czysty kod HTML
    if ($res1 === $res2) {
        return array(); // Brak zmian
    } else {
        file_put_contents($file1, $html2);
        $linksFromSitemap = fetchSitemapLink(); 
        $latestsCoursesResponse = getSkLatestCourses();
        $latestsCourses = json_decode($latestsCoursesResponse, true);
        if ($latestsCourses["success"] == true)
            foreach ($latestsCourses["products"] as $key => $value)
                if (!in_array($value["url"], $linksFromSitemap))
                    $linksFromSitemap[] = $value["url"];
        return $linksFromSitemap; // Znaleziono zmiany
    }
}

$file1 = 'last.html'; // Plik na dysku
$file2_url = 'https://strefakursow.pl/'; // Adres URL do drugiego pliku HTML

// Zwróć wartość logiczną w formacie JSON
echo json_encode(compareHtmlFiles($file1, $file2_url), JSON_UNESCAPED_SLASHES);
?>
