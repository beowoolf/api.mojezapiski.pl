<?php

header('Content-Type: application/json');

// Wyłączanie raportowania błędów XML
libxml_use_internal_errors(true);

// URL do strony do parsowania
$url = 'https://nofluffjobs.com/pl';

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
        $xpathExpression = '//*[@id="serverApp-state"]/text()';

        // Wyszukiwanie tekstu za pomocą XPath
        $textNodeList = $xpath->query($xpathExpression);

        // Sprawdzanie, czy znaleziono węzły
        if ($textNodeList->length > 0) {
            $text = $textNodeList->item(0)->nodeValue;
            $text = str_replace("&q;", '"', $text);
            $arr = json_decode($text, true);
            $new_arr = array(
                "joboffersAutocompleteConfig" => $arr["/joboffers/autocomplete/config?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"],
                //"posting" => $arr["/posting?limited=6&a;salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"],
                "joboffersOfTheDay" => $arr["/joboffers/oftheday?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"],
                //"offersCategorized" => $arr["OFFERS_CATEGORIZED"],
                "offersOfTheDay" => $arr["OFFERS_OF_THE_DAY"]
            );
            unset($arr["USER_COUNTRY"]);//X
            unset($arr["assets/environments/prod.json?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//X
            unset($arr["translations_pl-PL"]);//X
            unset($arr["/feature?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//X
            unset($arr["STORE_KEY"]);//X
            unset($arr["/joboffers/count?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//X
            unset($arr["assigned on server"]);//X
            unset($arr["assets/mock/country-codes-list.json?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//X
            unset($arr["/joboffers/autocomplete/config?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//FILTR
            unset($arr["/posting?limited=6&a;salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//OK
            unset($arr["/joboffers/oftheday?salaryCurrency=PLN&a;salaryPeriod=month&a;region=pl"]);//OK
            unset($arr["OFFERS_CATEGORIZED"]);//OK
            unset($arr["OFFERS_OF_THE_DAY"]);//OK
            echo json_encode(array("success" => true, "response" => $new_arr), JSON_UNESCAPED_SLASHES);
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
