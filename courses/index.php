<?php

header('Content-Type: application/json');

echo(
    json_encode(
        array(
            "describe" => "coursesWebscappersAndGettersEndpoints",
            "endpoints" => array(
                "eduj", "sk"
            )
        )
    )
);

