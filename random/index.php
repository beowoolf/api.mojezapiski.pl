<?php

// Pobranie parametrów z zapytania
$count = isset($_GET['count']) ? intval($_GET['count']) : 6;
$min = isset($_GET['min']) ? intval($_GET['min']) : 1;
$max = isset($_GET['max']) ? intval($_GET['max']) : 99;

// Sprawdzenie czy min jest mniejsze od max, jeśli nie, zamień wartości
if ($min >= $max) {
    $temp = $max;
    $max = $min;
    $min = $temp;
}

// Obliczenie ilości możliwych unikalnych liczb w danym zakresie
$possibleNumbers = $max - $min + 1;

// Sprawdzenie czy możliwe jest wygenerowanie wystarczającej liczby unikalnych liczb
if ($count > $possibleNumbers) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Nie można wygenerować tylu unikalnych liczb z danego zakresu']);
    exit();
}

// Tablica na wylosowane liczby
$randomNumbers = [];

// Wygenerowanie unikalnych liczb z zakresu
while (count($randomNumbers) < $count) {
    $random = mt_rand($min, $max);
    if (!in_array($random, $randomNumbers)) {
        $randomNumbers[] = $random;
    }
}

// Zwrócenie wylosowanych liczb jako JSON
header('Content-Type: application/json');
echo json_encode($randomNumbers);
