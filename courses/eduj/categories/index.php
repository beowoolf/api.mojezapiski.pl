<?php

header("Content-Type: application/json; charset=UTF-8");

function getCategoryWithSubCategories($category) {
    $category_to_return = array(
      "id" => $category["id"],
      "slug" => $category["slug"],
      "parent_id" => $category["parent_id"],
      "name" => $category["name"],
      "is_active" => $category["is_active"],
      "image" => $category["image"],
      "position" => $category["position"],
      "level" => $category["level"],
      "product_count" => $category["product_count"]
    );
    $category_list_to_return = array($category_to_return);
    $count_of_childrens = count($category["children_data"]);
    if ($count_of_childrens > 0)
      for ($i=0; $i < $count_of_childrens; $i++)
        $category_list_to_return = array_merge($category_list_to_return, getCategoryWithSubCategories($category["children_data"][$i]));
    return $category_list_to_return;
}

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://ecommerce.eduj.pl/rest/V1/categories",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_POSTFIELDS => "",
  CURLOPT_COOKIE => "eduj-vp=%255B%2522264%2522%252C%252239%2522%252C%2522252%2522%255D",
  CURLOPT_HTTPHEADER => [
    "User-Agent: insomnia/2023.5.8"
  ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo json_encode(array("success" => false, "errorMsg" => "cURL Error #:" . $err));
} else {
    $main_category = json_decode($response, true);
    $flat_category_list = getCategoryWithSubCategories($main_category);
    $only_names_and_ids = array();
    foreach ($flat_category_list as $key => $value)
      $only_names_and_ids[] = array("name" => $value["name"], "id" => $value["id"]);
    echo json_encode(array("success" => true, "list" => $only_names_and_ids), JSON_UNESCAPED_SLASHES);
}
