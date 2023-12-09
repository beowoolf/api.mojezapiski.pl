<?php

header('Content-Type: application/json');

// Wyłączanie raportowania błędów XML
libxml_use_internal_errors(true);

// URL do strony do parsowania
$url = 'https://justjoin.it';

// Inicjalizacja cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Wykonanie zapytania cURL
$response = curl_exec($ch);

// Sprawdzenie, czy zapytanie się powiodło
if ($response === false) {
    echo json_encode(array("success" => false, "errorMsg" => "Błąd podczas pobierania zawartości strony."));
} else {
    // Tworzenie obiektu DOMDocument
    $doc = new DOMDocument();

    // Wczytywanie kodu HTML
    if ($doc->loadHTML($response)) {
        // Tworzenie obiektu DOMXPath
        $xpath = new DOMXPath($doc);

        // Wyrażenie XPath
        $xpathExpression = '//*[@id="__NEXT_DATA__"]/text()';

        // Wyszukiwanie tekstu za pomocą XPath
        $textNodeList = $xpath->query($xpathExpression);

        // Sprawdzanie, czy znaleziono węzły
        if ($textNodeList->length > 0) {
            $text = $textNodeList->item(0)->nodeValue;
            //$text = str_replace("&q;", '"', $text);
            $arr = json_decode($text, true);
            //unset($arr["props"]);//?
            unset($arr["page"]);//nextRouter
            unset($arr["query"]);//onlySlugs
            unset($arr["buildId"]);//onlyBuildId
            unset($arr["runtimeConfig"]);//X
            unset($arr["isFallback"]);//onlyLogicValue
            unset($arr["dynamicIds"]);//unknowIntegers
            unset($arr["gssp"]);//onlyLogicValue
            unset($arr["appGip"]);//onlyLogicValue
            unset($arr["scriptLoader"]);//empty
            echo json_encode(array("success" => true, "response" => $arr), JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(array("success" => false, "errorMsg" => "Nie znaleziono tekstu odpowiadającego wyrażeniu XPath."));
        }
    } else {
        echo json_encode(array("success" => false, "errorMsg" => "Błąd podczas wczytywania kodu HTML."));
    }

    // Zamykanie połączenia cURL
    curl_close($ch);
}

// Wyłączanie obsługi błędów XML
libxml_use_internal_errors(false);
?>
