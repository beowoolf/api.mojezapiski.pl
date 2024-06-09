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

function getAllCanonicalLinks() {
    $url = "https://api.mojezapiski.pl/courses/sk/latest/";
    $latest = getPage($url);
    $latest_response = $latest["response"];
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
    //return json_encode($urls, JSON_UNESCAPED_SLASHES);
    return $urls;
}

function fetchSitemapLinks() {
    // Ustawienia zapytania cURL
    $url = 'https://strefakursow.pl/sitemap.xml';
    $directory_sepatator = DIRECTORY_SEPARATOR;
    $current_dir = __DIR__;
    if (strpos($current_dir, "all{$directory_sepatator}v")!= false) {
        //error_log("$current_dir jest wersjonowane");
        $current_dir = "$current_dir$directory_sepatator..";
    } /*else
        error_log("$current_dir NIE jest wersjonowane");*/
    $cacheTable = "$current_dir$directory_sepatator..$directory_sepatator..{$directory_sepatator}cache_strefakursow.db"; // Nazwa pliku bazy SQLite
    $strefakursowTable = 'strefakursow';
    
    // Inicjalizacja bazy SQLite
    $db = new SQLite3($cacheTable);
    
    // Tworzenie tabeli "strefakursow", jeśli nie istnieje
    $db->exec("CREATE TABLE IF NOT EXISTS $strefakursowTable (url TEXT PRIMARY KEY)");
    
    // Inicjalizacja sesji cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
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

header("Content-Type: application/json; charset=UTF-8");

$allLinksFromCanonical = getAllCanonicalLinks();
$linksFromSitemap = fetchSitemapLinks();
echo(json_encode(array_values(array_unique(array_merge($allLinksFromCanonical, $linksFromSitemap))), JSON_UNESCAPED_SLASHES));

?>
