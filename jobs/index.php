<?php

header('Content-Type: application/json');

echo(
    json_encode(
        array(
            "describe" => "jobsExtractorsEndpoints",
            "endpoints" => array(
                "jjit", "nofluffjobs"
            )
        )
    )
);

