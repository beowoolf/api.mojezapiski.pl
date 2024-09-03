<?php

header("Content-Type: application/json; charset=UTF-8");

// Ustawienia zapytania cURL
$url = 'https://strefakursow.pl/sitemap.xml';
$cacheTable = 'cache_strefakursow.db'; // Nazwa pliku bazy SQLite
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

            echo json_encode($urls, JSON_UNESCAPED_SLASHES);
        } else {
            error_log("Sitemap.xml nie zawiera adresów URL.");
            echo json_encode([]);
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

        echo json_encode($urls, JSON_UNESCAPED_SLASHES);
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

    echo json_encode($urls, JSON_UNESCAPED_SLASHES);
}

// Zamknij połączenie z bazą SQLite
$db->close();

?>
