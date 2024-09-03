<?php

header("Content-Type: application/json; charset=UTF-8");

function getLinksFromSitemapXml() {
    // Ustawienia zapytania cURL
    $url = 'https://eduj.pl/sitemap.xml';
    $current_dir = __DIR__;
    if (strpos($current_dir, "all/v")!= false) {
        //error_log("$current_dir jest wersjonowane");
        $current_dir = "$current_dir/..";
    } /*else
        error_log("$current_dir NIE jest wersjonowane");*/
    $cacheTable = "$current_dir/../../cache.db"; // Nazwa pliku bazy SQLite
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
    
                return json_encode($urls, JSON_UNESCAPED_SLASHES);
            } else {
                error_log("Sitemap.xml nie zawiera adresów URL.");
                return json_encode([]);
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
    
            return json_encode($urls, JSON_UNESCAPED_SLASHES);
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
    
        return json_encode($urls, JSON_UNESCAPED_SLASHES);
    }
    
    // Zamknij połączenie z bazą SQLite
    $db->close();
}

function getDataFromGraphqlEndpoint() {
    $url = 'https://ecommerce.eduj.pl/graphql';
    $headers = array(
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'Referer: https://eduj.pl/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
        'sec-ch-ua: "Chromium";v="122", "Not(A:Brand";v="24", "Microsoft Edge";v="122"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'Cookie: eduj-vp=%255B%2522731%2522%255D; private_content_version=97b340950cd84875e5a934a6e3379e48'
    );
    
    $data = array(
        'operationName' => 'GetProducts',
        'variables' => array(
            'search' => '',
            'pageSize' => 1000,
            'currentPage' => 1
        ),
        'query' => 'query GetProducts($search: String!, $pageSize: Int!, $currentPage: Int!) {
      products(
        search: $search
        filter: {}
        sort: {}
        pageSize: $pageSize
        currentPage: $currentPage
      ) {
        items {
          id
          sku
          name
          description {
            html
            __typename
          }
          short_description {
            html
            __typename
          }
          image_url
          author_name
          videos_duration
          resource_amount
          acquired_skills
          has_subtitles
          has_test_and_questions
          review_average_score_round
          product_type
          last_update
          review_average_score
          bestseller
          news_to_date
          test_question_amount
          api_review_count
          type_id
          percent_discount
          discounted_price
          categories {
            id
            __typename
          }
          price_range {
            minimum_price {
              regular_price {
                value
                currency
                __typename
              }
              final_price {
                value
                currency
                __typename
              }
              discount {
                amount_off
                percent_off
                __typename
              }
              __typename
            }
            maximum_price {
              regular_price {
                value
                currency
                __typename
              }
              final_price {
                value
                currency
                __typename
              }
              discount {
                amount_off
                percent_off
                __typename
              }
              __typename
            }
            __typename
          }
          __typename
        }
        total_count
        page_info {
          current_page
          total_pages
          page_size
          __typename
        }
        __typename
      }
    }'
    );
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        //"cURL Error #:" . $err;
        return array(
            "data" => array(
                "products" => array(
                    "items" => array()
                )
            )
        );
    } else {
        return json_decode($response, true);
    }
}

function getLinksFromGraphqlEndpoint() {
    $new_graphql_arr = array();
    $graphql_arr = getDataFromGraphqlEndpoint();
    foreach ($graphql_arr["data"]["products"]["items"] as $key => $value)
        $new_graphql_arr[] = "https://eduj.pl/produkt/{$value["sku"]}";
    return json_encode($new_graphql_arr, JSON_UNESCAPED_SLASHES);
}

function dump_array_to_file($json_name, $array) {
    file_put_contents("$json_name.json", json_encode($array, JSON_UNESCAPED_SLASHES));
}

$firstLinksResponse = getLinksFromSitemapXml();
$secondLinksResponse = getLinksFromGraphqlEndpoint();

$firstLinks = json_decode($firstLinksResponse, true);
$secondLinks = json_decode($secondLinksResponse, true);

//dump_array_to_file("firstLinks", $firstLinks);
//dump_array_to_file("secondLinks", $secondLinks);

$final_array = array_values(array_unique(array_merge($firstLinks, $secondLinks)));

echo(json_encode($final_array, JSON_UNESCAPED_SLASHES));
