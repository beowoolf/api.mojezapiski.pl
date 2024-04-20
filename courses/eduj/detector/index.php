<?php

header("Content-Type: application/json; charset=UTF-8");

function getFromGraphqlEndpoint() {
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
            'pageSize' => 10000,
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
          author_id
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
        return json_encode(array(
            "data" => array(
                "products" => array(
                    "items" => array()
                )
            )
        ), JSON_UNESCAPED_SLASHES);
    } else {
        return $response;
    }
}

function mapJsonObject($json) {
    if (!$json) return json_encode(array(
        "data" => array(
            "products" => array(
                "items" => array()
            )
        )
    ), JSON_UNESCAPED_SLASHES);
    $array = json_decode($json, true);
    $items = array();
    foreach ($array["data"]["products"]["items"] as $value) {
        $items[] = array(
            "id" => $value["id"],
            "name" => $value["name"],
            "price_range" => $value["price_range"],
            "sku" => $value["sku"],
            "discounted_price" => $value["discounted_price"],
            "author_id" => $value["author_id"],
            "author_name" => $value["author_name"],
            "image_url" => $value["image_url"],
            "categories" => $value["categories"],
            "description" => $value["description"]["html"]
        );
    }
    $out = array(
        "data" => array(
            "products" => array(
                "total_count" => $array["data"]["products"]["total_count"],
                "page_info" => array(
                    "current_page" => $array["data"]["products"]["page_info"]["current_page"],
                    "total_pages" => $array["data"]["products"]["page_info"]["total_pages"],
                    "page_size" => $array["data"]["products"]["page_info"]["page_size"]
                ),
                "items" => $items
            )
        )
    );
    return json_encode($out, JSON_UNESCAPED_SLASHES);
}

function mapCategories($old_arr) {
    $new_arr = array();
    foreach ($old_arr as $key => $value)
        $new_arr[] = $value["id"];
    return $new_arr;
}

function mapCourseItems($response) {
    $arr = json_decode($response, true);
    $courses = array();
    foreach ($arr["data"]["products"]["items"] as $value) {
        $courses[] = array(
            "title" => trim($value["name"]),
            "price" => $value["price_range"]["maximum_price"]["regular_price"]["value"],
            "url" => "https://eduj.pl/produkt/{$value["sku"]}",
            /*"platform" => array(
                "name" => "Eduj",
                "logo" => "https://eduj.pl/assets/images/logo-eduj.svg",
                "url" => "https://eduj.pl/",
                "type" => "eduj",
                "version" => "1.0.0",
                "subscriptionMode" => false
            ),*/
            "discountedPrice" => $value["price_range"]["minimum_price"]["final_price"]["value"],
            "thumbnail" => str_replace("/thumbnail.jpeg","/thumbnail_848x480.jpeg", $value["image_url"]),
            "authorId" => $value["author_id"],
            "categoryIds" => mapCategories($value["categories"]),
            "description" => strip_tags($value["description"])
        );
    }
    foreach ($courses as $key => $value)
        if ($value["price"] == $value["discountedPrice"])
            unset($courses[$key]["discountedPrice"]);
    
    return $courses;
}

function getLinksFromSitemap() {
    // Ustawienia zapytania cURL
    $url = 'https://eduj.pl/sitemap.xml';
    $cacheTable = __DIR__.'/../cache.db'; // Nazwa pliku bazy SQLite
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
    
                return $urls;
            } else {
                error_log("Sitemap.xml nie zawiera adresów URL.");
                return [];
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
    
            return $urls;
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
    
        return $urls;
    }
    
    // Zamknij połączenie z bazą SQLite
    $db->close();
}

function extractLinksFromResponse($mapped_courses_list) {
    $links_list = array();
    foreach ($mapped_courses_list as $value)
        $links_list[] = $value["url"];
    return $links_list;
}

function dump_array_to_file($json_name, $array) {
    file_put_contents("$json_name.json", json_encode($array, JSON_UNESCAPED_SLASHES));
}

function getMissedLinks($mapped_courses_list) {
    $links_from_sitemap = getLinksFromSitemap();
    //dump_array_to_file("links_from_sitemap", $links_from_sitemap);
    $links_from_graphql = extractLinksFromResponse($mapped_courses_list);
    //dump_array_to_file("links_from_graphql", $links_from_graphql);
    $links_merged_list = array_merge($links_from_sitemap, $links_from_graphql);
    //dump_array_to_file("links_merged_list", $links_merged_list);
    $links_unique_list = array_unique($links_merged_list);
    //dump_array_to_file("links_unique_list", $links_unique_list);
    $all_unique_links = array_values($links_unique_list);
    //dump_array_to_file("all_unique_links", $all_unique_links);
    return array_values(array_diff($all_unique_links, $links_from_graphql));
}

function compareJsonFiles($json_file, $reverse) {
    $json1 = @file_get_contents($json_file);
    $json2 = getFromGraphqlEndpoint();
    $res1 = mapJsonObject($json1);
    $res2 = mapJsonObject($json2);
    
    // Porównaj czysty kod HTML
    if ($res1 === $res2) {
        return array(
            "courses" => array(),
            "links" => array()
        ); // Brak zmian
    } else {
        file_put_contents($json_file, $json2);
        $courses = mapCourseItems($res2);
        $courses_to_out = $reverse ? array_reverse($courses) : $courses;
        return array(
            "courses" => $courses_to_out,
            "links" => getMissedLinks($courses)
        ); // Znaleziono zmiany
    }
}

$reverse = (isset($_REQUEST["reverse"]) && $_REQUEST["reverse"] == 1);

$file_with_last_response = 'last.json'; // Plik na dysku

echo json_encode(compareJsonFiles($file_with_last_response, $reverse), JSON_UNESCAPED_SLASHES);

?>
