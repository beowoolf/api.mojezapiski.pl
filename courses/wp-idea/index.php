<?php

function getProductsFromPage($url) {
    // Sprawdź, czy biblioteka SimpleHTMLDOM jest załadowana
    if (!function_exists('file_get_html')) {
        require_once 'simple_html_dom.php'; // Załaduj bibliotekę, jeśli nie jest jeszcze załadowana
    }

    // Pobierz zawartość strony HTML
    $html = file_get_html($url);

    // Zainicjuj tablicę na produkty
    $products = array();

    // Znajdź wszystkie div-y z atrybutem itemtype="http://schema.org/Product"
    foreach ($html->find('div[itemtype="http://schema.org/Product"]') as $productDiv) {
        // Znajdź tagi img, h2 i a wewnątrz div-a produktu
        $productImage = $productDiv->find('img', 0);
        $productName = $productDiv->find('h2', 0)->plaintext;

        // Znajdź pierwszy tag a z klasą thumb wewnątrz div-a produktu
        $link = $productDiv->find('a.thumb', 0);

        // Sprawdź, czy znaleziono obraz, nazwę produktu i link
        if ($productImage && $productName && $link) {
            // Dodaj adres URL obrazu, nazwę produktu i link do tablicy produktów
            $products[] = array(
                'name' => trim($productName),
                'image' => trim($productImage->src),
                'link' => trim($link->href) // Dodaj link produktu
            );
        }
    }

    // Zwolnij pamięć
    $html->clear();

    // Zwróć tablicę produktów w formacie JSON
    return json_encode($products, JSON_UNESCAPED_SLASHES);
}

// Odczytaj dane przesłane jako JSON
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Sprawdź, czy przekazano adres URL
if (isset($data['url'])) {
    // Przekazanie adresu URL do funkcji getProductsFromPage
    $url = $data['url'];
    $productsJSON = getProductsFromPage($url);

    // Ustaw nagłówek Content-Type
    header("Content-Type: application/json; charset=UTF-8");

    // Wyświetl wyniki w formacie JSON
    echo $productsJSON;
} else {
    // Jeśli nie przekazano adresu URL, zwróć błąd
    http_response_code(400);
    echo json_encode(array('error' => 'URL not provided'), JSON_UNESCAPED_SLASHES);
}

?>
