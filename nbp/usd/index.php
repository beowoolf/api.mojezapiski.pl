<?php

// Ustawienia bazy danych SQLite
$db = new SQLite3('rates.db');

// Utworzenie tabeli, jeśli nie istnieje
$db->exec('CREATE TABLE IF NOT EXISTS rates (
    effectiveDate TEXT PRIMARY KEY,
    no TEXT,
    ask REAL
)');

// Sprawdzenie, czy dzisiejsza data już istnieje w bazie danych
$today = date('Y-m-d');
$query = $db->prepare('SELECT COUNT(*) FROM rates WHERE effectiveDate = :today');
$query->bindValue(':today', $today);
$result = $query->execute();
$row = $result->fetchArray();
$alt_rates = [];
if ($row[0] == 0) {
    // Jeśli brak danych dla dzisiejszego dnia, pobierz dane z API NBP
    $url = 'https://api.nbp.pl/api/exchangerates/rates/c/usd/last/255?format=json';
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (isset($data['rates']) && count($data['rates']) > 0) {
        // Rozpoczęcie transakcji
        $db->exec('BEGIN TRANSACTION');
        
        try {
            // Pętla przez dane kursów
            $prevRate = $data['rates'][0];
            $effectiveDate = $prevRate['effectiveDate'];
            $ask = $prevRate['ask'];
            $no = $prevRate['no'];
            $db->exec('INSERT OR REPLACE INTO rates (no, effectiveDate, ask) 
                       VALUES ("' . $no . '", "' . $effectiveDate . '", ' . $ask . ')');
            $alt_rates[] = array("no" => $no, "effectiveDate" => $effectiveDate, "ask" => $ask);
            for ($j=1; $j < count($data['rates']); $j++) {
                $rate = $data['rates'][$j];

                $effectiveDate = $rate['effectiveDate'];
                $ask = $rate['ask'];
                $no = $rate['no'];

                // Sprawdzanie, czy pomiędzy dniami są luki
                $prevDate = new DateTime($prevRate['effectiveDate']);
                $currentDate = new DateTime($effectiveDate);
                $interval = $prevDate->diff($currentDate);

                // Jeśli są brakujące dni, uzupełniamy je tym samym kursem
                if ($interval->days > 1) {
                    $dateIterator = clone $prevDate;
                    for ($i = 1; $i < $interval->days; $i++) {
                        $dateIterator->modify('+1 day');
                        $missingDate = $dateIterator->format('Y-m-d');
                        $db->exec('INSERT OR REPLACE INTO rates (no, effectiveDate, ask) 
                                   VALUES ("' . $prevRate['no'] . '", "' . $missingDate . '", ' . $prevRate['ask'] . ')');
                        $alt_rates[] = array("no" => $prevRate['no'], "effectiveDate" => $missingDate, "ask" => $prevRate['ask']);
                    }
                }

                // Wstawianie/aktualizowanie kursu w bazie danych dla aktualnego dnia
                $db->exec('INSERT OR REPLACE INTO rates (no, effectiveDate, ask) 
                           VALUES ("' . $no . '", "' . $effectiveDate . '", ' . $ask . ')');
                $alt_rates[] = array("no" => $no, "effectiveDate" => $effectiveDate, "ask" => $ask);

                // Zapisz obecny kurs jako poprzedni do sprawdzenia w kolejnej iteracji
                $prevRate = $rate;
            }

            // Zakończenie transakcji
            $db->exec('COMMIT');
        } catch (Exception $e) {
            // W razie błędu wycofujemy transakcję
            $db->exec('ROLLBACK');
            echo 'Błąd: ' . $e->getMessage();
        }
    }
}

// Pobranie wszystkich kursów z bazy danych
$query = $db->query('SELECT effectiveDate, no, ask FROM rates');
$rates = [];
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
    $rates[$row['effectiveDate']] = array("no" => $row['no'], "ask" => $row['ask']);
}

// Zwrócenie wyników jako JSON
header('Content-Type: application/json');
echo json_encode($rates);

?>
