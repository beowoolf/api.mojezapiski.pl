<?php

// Ustawienia zapytania cURL
$url = 'https://eduj.pl/sitemap.xml';
$cacheTable = 'cache.db'; // Nazwa pliku bazy SQLite
$edujTable = 'eduj';

// Inicjalizacja bazy SQLite
$db = new SQLite3($cacheTable);

// Tworzenie tabeli "eduj", jeśli nie istnieje
$db->exec("CREATE TABLE IF NOT EXISTS $edujTable (url TEXT PRIMARY KEY)");

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
            // Przygotuj listę adresów URL, które zawierają ciąg "/produkt/"
            $filteredUrls = [];
            
            foreach ($urls as $url) {
                $urlString = (string)$url->loc;
                
                if (strpos($urlString, '/produkt/') !== false) {
                    $filteredUrls[] = $urlString;
                }
            }

            if (count($filteredUrls) > 0) {
                // Odczytaj aktualne adresy URL z bazy SQLite
                $existingUrls = [];
                $result = $db->query("SELECT url FROM $edujTable");

                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $existingUrls[] = $row['url'];
                }

                // Wyklucz istniejące adresy, które nie zawierają "/produkt/"
                $filteredUrls = array_diff($filteredUrls, $existingUrls);

                if (count($filteredUrls) > 0) {
                    // Dodaj nowe adresy do bazy SQLite
                    $stmt = $db->prepare("INSERT OR REPLACE INTO $edujTable (url) VALUES (:url)");

                    foreach ($filteredUrls as $urlString) {
                        $stmt->bindValue(':url', $urlString, SQLITE3_TEXT);
                        $stmt->execute();
                    }

                    error_log("Pobrano i zaktualizowano adresy z sitemap.xml w bazie SQLite.");
                } else {
                    error_log("1 Sitemap.xml nie zawiera nowych adresów URL zawierających '/produkt/'.");
                }
            } else {
                error_log("2 Sitemap.xml nie zawiera nowych adresów URL zawierających '/produkt/'.");
            }

            // Zwróć adresy URL zapisane w bazie SQLite, które zawierają "/produkt/"
            $result = $db->query("SELECT url FROM $edujTable");
            $urls = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $urlString = $row['url'];
                
                if (strpos($urlString, '/produkt/') !== false) {
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

        // Spróbuj zwrócić zapisane adresy URL z tabeli "eduj" bazy SQLite, które zawierają "/produkt/" w formacie JSON
        $result = $db->query("SELECT url FROM $edujTable");
        $urls = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $urlString = $row['url'];

            if (strpos($urlString, '/produkt/') !== false) {
                $urls[] = $urlString;
            }
        }

        echo json_encode($urls, JSON_UNESCAPED_SLASHES);
    }
} else {
    // Błąd podczas pobierania sitemap.xml - logowanie i próba odczytu danych z bazy SQLite
    error_log("Błąd podczas pobierania sitemap.xml.");

    // Spróbuj zwrócić zapisane adresy URL z tabeli "eduj" bazy SQLite, które zawierają "/produkt/" w formacie JSON
    $result = $db->query("SELECT url FROM $edujTable");
    $urls = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $urlString = $row['url'];

        if (strpos($urlString, '/produkt/') !== false) {
            $urls[] = $urlString;
        }
    }

    echo json_encode($urls, JSON_UNESCAPED_SLASHES);
}

// Zamknij połączenie z bazą SQLite
$db->close();
?>
