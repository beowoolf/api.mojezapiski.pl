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

function tryFindByXPath($xpath, $xpath_arr) {
    foreach ($xpath_arr as $key => $value) {
        $nodeList = $xpath->query($value);
        if ($nodeList->length > 0)
            return $nodeList->item(0)->nodeValue;
    }
    return "";
}

// Wczytywanie pliku https://strefakursow.pl/kursy/rozwoj_osobisty/kurs_asana_od_podstaw_-_zarzadzanie_projektami.html
if ($doc->loadHTMLFile($link)) {
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
        $data['error'] = "Nie znaleziono ceny kursu ({$currentPrice_len}), tytułu ({$title_len}), informacji o profesji ({$authorProfession_len}), imienia i nazwiska autora ({$authorName_len}) lub adresu URL do strony z profilem autora ({$authorProfileURL_len}).";
    }

    echo json_encode(array("success" => !isset($data['error']), "response" => $data), JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(array("success" => false, "errorMsg" => "Błąd podczas wczytywania zawartości z adresu $link."), JSON_UNESCAPED_SLASHES);
}

// Wyłączanie obsługi błędów XML
libxml_use_internal_errors(false);

?>
