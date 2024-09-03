<?php

// Odbierz dane JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData);

if ($data && isset($data->url)) {
    // Pobierz adres obrazka
    $imageUrl = $data->url;

    // Sprawdź, czy obrazek istnieje
    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        // Odczytaj obrazek
        $imageContent = file_get_contents($imageUrl);
        $basename = basename($imageUrl);

        if ($imageContent !== false) {
            // Utwórz obiekt obrazka z zawartości
            $image = imagecreatefromstring($imageContent);

            if ($image !== false) {
                // Przygotuj nagłówki dla formatu WEBP
                header('Content-Type: image/webp');
                header('Content-Disposition: inline; filename="' . $basename . '"');

                // Konwertuj obrazek na format WEBP i wyślij go jako odpowiedź
                imagewebp($image);
                imagedestroy($image);
            } else {
                header("HTTP/1.0 500 Internal Server Error");
                echo "Błąd podczas przetwarzania obrazka.";
            }
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "Nie można pobrać obrazka.";
        }
    } else {
        header("HTTP/1.0 400 Bad Request");
        echo "Nieprawidłowy URL obrazka.";
    }
} else {
    header("HTTP/1.0 400 Bad Request");
    echo "Nieprawidłowe dane JSON.";
}

?>
