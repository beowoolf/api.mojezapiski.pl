<?php

header("Content-Type: application/json; charset=UTF-8");

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
        return "up-to-date"; // Brak zmian
    } else {
        file_put_contents($file1, $html2);
        return "out-to-date"; // Znaleziono zmiany
    }
}

$file1 = 'last.html'; // Plik na dysku
$file2_url = 'https://strefakursow.pl/'; // Adres URL do drugiego pliku HTML

// Zwróć wartość logiczną w formacie JSON
echo json_encode(compareHtmlFiles($file1, $file2_url));
?>
