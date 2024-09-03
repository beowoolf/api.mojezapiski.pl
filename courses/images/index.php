<?php

header("Content-Type: application/json; charset=UTF-8");

function create_valid_url($url) {
    // Odkoduj znaki procentowe
    $decoded_url = rawurldecode($url);

    // Ponownie zakoduj adres URL, ale bez kodowania ":" oraz "/"
    $valid_url = str_replace(['%3A', '%2F'], [':', '/'], rawurlencode($decoded_url));

    return $valid_url;
}

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
        if (isset($data['url']) && isset($data['id']) && isset($data['key'])) {
            require __DIR__."/key.php";
            if ($data['key'] == $key) {
                // Pobieranie adresu URL obrazka i jego identyfikatora
                $image_url = create_valid_url($data['url']);
                $image_id = $data['id'];
    
                $image_content = @file_get_contents($image_url);
                if ($image_content === false)
                    $image_content = @file_get_contents(preg_replace('/\.(jpg)$/i', '.png', $image_url));
    
                // Sprawdzenie, czy udało się pobrać obrazek
                if ($image_content !== false) {
                    $enviroments = array("prod", "dev", "test");
                    $enviroment = "dev";
                    if (isset($data['env']) && in_array($data['env'], $enviroments))
                        $enviroment = $data['env'];
                    $image_dir = "dev_images";
                    switch ($enviroment) {
                        case 'dev':
                            $image_dir = "dev_courses";
                            break;
                        case 'test':
                            $image_dir = "test_courses";
                            break;
                        case 'prod':
                            $image_dir = "courses";
                            break;
                        default:
                            $image_dir = "dev_courses";
                            break;
                    }
                    // Utworzenie nazwy pliku z nowym rozszerzeniem
                    $new_filename = $image_id . '.webp';
    
                    $images_dir = __DIR__ . "/$image_dir";
    
                    if (createDirIfNotExist($images_dir) == false)
                        die(json_encode(array('success' => false, 'message' => 'Błąd tworzenia katalogu na obrazki')));
    
                    // Ścieżka do zapisu przekonwertowanego obrazka
                    $destination_path = $images_dir . '/' . $new_filename;
    
                    // Zapis przekonwertowanego obrazka do pliku za pomocą Imagick
                    $imagick = new Imagick();
                    $imagick->readImageBlob($image_content);
                    $imagick->setImageFormat('webp');
                    $imagick->writeImage($destination_path);
                    $imagick->clear();
                    $imagick->destroy();
    
                    echo json_encode(array('success' => true, 'message' => 'Obrazek został pomyślnie przekonwertowany i zapisany jako ' . $new_filename));
                } else {
                    echo json_encode(array('success' => false, 'message' => 'Błąd podczas pobierania obrazka: ' . $image_url), JSON_UNESCAPED_SLASHES);
                }
            } else {
                // Zwracanie odpowiedzi, że nie udało się pobrać obrazka
                echo json_encode(array('success' => false, 'message' => "Nieprawidłowy 'key'"), JSON_UNESCAPED_SLASHES);
            }
        } else {
            // Zwracanie odpowiedzi w przypadku braku wymaganych danych
            echo json_encode(array('success' => false, 'message' => 'Brak wymaganych danych (url lub id lub key)'));
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
