<?php

function get_user_repos($pat) {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.github.com/user/repos",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_HTTPHEADER => [
        "Authorization: token $pat",
        "User-Agent: insomnia/2023.5.8"
      ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
      return array("success" => false, "error_msg" => "cURL Error #:" . $err);
    } else {
      return array("success" => true, "response" => json_decode($response, true));
    }
}

function get_repo_langs($pat, $full_name) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.github.com/repos/$full_name/languages",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_HTTPHEADER => [
        "Authorization: token $pat",
        "User-Agent: insomnia/2023.5.8"
      ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return array("success" => false, "error_msg" => "cURL Error #:" . $err);
    } else {
        return array("success" => true, "list" => json_decode($response, false));
    }
}

require __DIR__."/pat.php";

$user_repos = get_user_repos($pat);
if ($user_repos["success"] === false)
    echo(json_encode($user_repos));
else {
    $output = array();
    foreach ($user_repos["response"] as $key => $value)
        $output[] = array("name" => $value["name"], "created_at" => $value["created_at"], "langs" => get_repo_langs($pat, $value["full_name"]));
    $all_langs = array();
    foreach ($output as $key => $value)
        foreach ((isset($value["langs"]["list"]) ? $value["langs"]["list"] : array()) as $k => $v)
            if (isset($all_langs[$k]))
                $all_langs[$k] += $v;//my_debug($v);
            else
                $all_langs[$k] = $v;
    arsort($all_langs);
    echo(json_encode(array("success" => true, "list" => $output, "allLangs" => $all_langs)));
}
