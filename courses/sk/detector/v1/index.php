<?php

header("Content-Type: application/json; charset=UTF-8");

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
        return fetchSitemapLink(); // Znaleziono zmiany
    }
}

$file1 = 'last.html'; // Plik na dysku
$file2_url = 'https://strefakursow.pl/'; // Adres URL do drugiego pliku HTML

// Zwróć wartość logiczną w formacie JSON
echo json_encode(compareHtmlFiles($file1, $file2_url), JSON_UNESCAPED_SLASHES);
?>
