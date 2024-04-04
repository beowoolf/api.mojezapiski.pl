<?php

header("Content-Type: application/json; charset=UTF-8");

$current_dir = __DIR__;

$lasts_files = array(
    "eduj" => "$current_dir/../eduj/detector/last.json",
    "sk" => "$current_dir/../sk/detector/last.html"
);

$states = array();

foreach ($lasts_files as $key => $value)
    $states[$key] = file_exists($value) ? "exist" : "not exist";

if (isset($_REQUEST["clear"]) && array_key_exists($_REQUEST["clear"], $lasts_files) && $states[$_REQUEST["clear"]] == "exist")
    $states[$_REQUEST["clear"]] = unlink($lasts_files[$_REQUEST["clear"]]) ? "cleared" : "fail";

echo(json_encode($states));

?>
