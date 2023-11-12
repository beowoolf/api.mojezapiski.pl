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

// Wczytywanie pliku https://strefakursow.pl/kursy/rozwoj_osobisty/kurs_asana_od_podstaw_-_zarzadzanie_projektami.html
if ($doc->loadHTMLFile($link)) {
    // Tworzenie obiektu DOMXPath
    $xpath = new DOMXPath($doc);

    // Wyrażenia XPath
    $priceXPath = '//*[@id="price-container"]/div[1]/div/div[1]';
    $titleXPath = '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[2]/h1';
    $authorProfessionXPath = '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/p';
    $authorNameXPath = '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/text()';
    $authorProfileURLXPath = '/html/body/div[13]/div[2]/div[2]/div[1]/div/div[1]/div[1]/div/a/@href';

    // Wyszukiwanie ceny
    $priceNodeList = $xpath->query($priceXPath);

    // Wyszukiwanie tytułu kursu
    $titleNodeList = $xpath->query($titleXPath);

    // Wyszukiwanie informacji o profesji autora kursu
    $authorProfessionNodeList = $xpath->query($authorProfessionXPath);

    // Wyszukiwanie imienia i nazwiska autora kursu
    $authorNameNodeList = $xpath->query($authorNameXPath);

    // Wyszukiwanie adresu URL do strony z profilem autora
    $authorProfileURLNodeList = $xpath->query($authorProfileURLXPath);

    // Inicjalizacja tablicy na dane
    $data = array();

    // Sprawdzanie, czy znaleziono węzły
    if ($priceNodeList->length > 0 && $titleNodeList->length > 0 && $authorProfessionNodeList->length > 0 && $authorNameNodeList->length > 0 && $authorProfileURLNodeList->length > 0) {
        // Pobieranie zawartości węzłów
        $data['title'] = trim($titleNodeList->item(0)->nodeValue);
        $data['price'] = intval($priceNodeList->item(0)->nodeValue);
        $data['url'] = $link;
        $data['platform'] = array(
            "name" => 'StrefaKursów.pl',
            'logo' => 'https://strefakursow.pl/redesign/assets/images/logo/default-logo-desktop.svg',
            'url' => 'https://strefakursow.pl/',
            'type' => 'sk',
            'version' => '1.0.0',
            'subscriptionMode' => false
        );
        $data['author']['name'] = trim($authorNameNodeList->item(0)->nodeValue);
        $data['author']['profession'] = trim($authorProfessionNodeList->item(0)->nodeValue);
        $data['author']['url'] = trim($authorProfileURLNodeList->item(0)->nodeValue);
    } else {
        $data['error'] = "Nie znaleziono ceny kursu ({$priceNodeList->length}), tytułu ({$titleNodeList->length}), informacji o profesji ({$authorProfessionNodeList->length}), imienia i nazwiska autora ({$authorNameNodeList->length}) lub adresu URL do strony z profilem autora ({$authorProfileURLNodeList->length}).";
    }

    echo json_encode(array("success" => !isset($data['error']), "response" => $data), JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(array("success" => false, "errorMsg" => "Błąd podczas wczytywania zawartości z adresu $link."), JSON_UNESCAPED_SLASHES);
}

// Wyłączanie obsługi błędów XML
libxml_use_internal_errors(false);

?>
