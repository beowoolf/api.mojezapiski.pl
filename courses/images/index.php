<?php

header("Content-Type: application/json; charset=UTF-8");

// Funkcja do pobierania obrazka z określonego URL
function fetch_image($url) {
    // Inicjalizacja sesji cURL
    $ch = curl_init();

    // Ustawienie opcji cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Wykonanie żądania cURL
    $image_content = curl_exec($ch);

    // Zamykanie sesji cURL
    curl_close($ch);

    return $image_content;
}

function createDirIfNotExist($path) {
    // Sprawdzenie, czy katalog już istnieje
    if (!is_dir($path)) {
        // Utworzenie katalogu, jeśli nie istnieje
        if (!mkdir($path, 0777, true)) {
            // Jeśli nie udało się utworzyć katalogu, zwracamy false
            return false;
        }
    }
    
    // Jeśli katalog już istniał lub został utworzony, zwracamy true
    return true;
}

// Sprawdzanie, czy otrzymano dane metodą POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pobieranie danych JSON z ciała żądania
    $json_data = @file_get_contents("php://input");

    // Sprawdzanie, czy otrzymano jakiekolwiek dane
    if (!empty($json_data)) {
        // Dekodowanie danych JSON na tablicę asocjacyjną
        $data = json_decode($json_data, true);

        // Sprawdzanie, czy otrzymano poprawne dane
        if (isset($data['url']) && isset($data['id'])) {
            // Pobieranie adresu URL obrazka i jego identyfikatora
            $image_url = $data['url'];
            $image_id = $data['id'];

            $image_content = @file_get_contents($image_url);
            if ($image_content === false)
                $image_content = @file_get_contents(preg_replace('/\.(jpg)$/i', '.png', $image_url));
            /*
            // Pobieranie rozszerzenia pliku
            $extension = pathinfo($image_url, PATHINFO_EXTENSION);

            // Tablica z możliwymi rozszerzeniami do próby
            $extensions_to_try = ['webp', 'jpg', 'png'];

            // Iteracja przez możliwe rozszerzenia
            foreach ($extensions_to_try as $ext) {
                // Jeśli rozszerzenie jest różne od obecnego, to próbujemy pobrania obrazka
                if ($ext !== $extension) {
                    // Próba zmiany rozszerzenia w adresie URL
                    $url_attempt = preg_replace('/\.(jpg|png)$/i', '.' . $ext, $image_url);

                    // Pobieranie obrazka z próbnego adresu URL
                    $image_content = fetch_image($url_attempt);

                    // Jeśli pobranie obrazka się powiodło, to przerywamy pętlę
                    if ($image_content !== false) {
                        break;
                    }
                }
            }
            */

            // Sprawdzenie, czy udało się pobrać obrazek
            if ($image_content !== false) {
                // Konwersja obrazka do formatu webp
                $webp_image = @imagecreatefromstring($image_content);

                if ($webp_image == false)
                    die(json_encode(array('success' => false, 'message' => 'Błąd podczas tworzenia obrazka')));

                // Utworzenie nazwy pliku z nowym rozszerzeniem
                $new_filename = $image_id . '.webp';

                $images_dir = __DIR__ . '/courses';

                if (createDirIfNotExist($images_dir) == false)
                    die(json_encode(array('success' => false, 'message' => 'Błąd tworzenia katalogu na obrazki')));

                // Ścieżka do zapisu przekonwertowanego obrazka
                $destination_path = $images_dir . '/' . $new_filename;

                // Zapis przekonwertowanego obrazka do pliku
                $is_imagewebp_saved = @imagewebp($webp_image, $destination_path);

                if ($is_imagewebp_saved != true) // Zwracanie odpowiedzi sukcesu
                    echo json_encode(array('success' => false, 'message' => 'Obrazek niew został pomyślnie przekonwertowany i zapisany jako ' . $new_filename));

                // Zwalnianie zasobów
                $is_image_destroyed = @imagedestroy($webp_image);

                if ($is_image_destroyed != true)
                    error_log("Nie udało się niszczenie informacji o obrazku w pamięci");

                if ($is_imagewebp_saved == true) // Zwracanie odpowiedzi sukcesu
                    echo json_encode(array('success' => true, 'message' => 'Obrazek został pomyślnie przekonwertowany i zapisany jako ' . $new_filename));
            } else {
                // Zwracanie odpowiedzi, że nie udało się pobrać obrazka
                echo json_encode(array('success' => false, 'message' => 'Błąd podczas pobierania obrazka: ' . $image_url));
            }
        } else {
            // Zwracanie odpowiedzi w przypadku braku wymaganych danych
            echo json_encode(array('success' => false, 'message' => 'Brak wymaganych danych (url lub id)'));
        }
    } else {
        // Zwracanie odpowiedzi w przypadku pustego ciała żądania
        echo json_encode(array('success' => false, 'message' => 'Puste dane'));
    }
} else {
    // Zwracanie odpowiedzi w przypadku niewłaściwej metody żądania
    echo json_encode(array('success' => false, 'message' => 'Niewłaściwa metoda żądania'));
}
?>
