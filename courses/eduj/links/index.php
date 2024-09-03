<?php

header("Content-Type: application/json; charset=UTF-8");

function getFromEndpoint() {
    $url = 'https://ecommerce.eduj.pl/graphql';
    $headers = array(
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'Referer: https://eduj.pl/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0',
        'sec-ch-ua: "Chromium";v="122", "Not(A:Brand";v="24", "Microsoft Edge";v="122"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'Cookie: eduj-vp=%255B%2522731%2522%255D; private_content_version=97b340950cd84875e5a934a6e3379e48'
    );
    
    $data = array(
        'operationName' => 'GetProducts',
        'variables' => array(
            'search' => '',
            'pageSize' => 1000,
            'currentPage' => 1
        ),
        'query' => 'query GetProducts($search: String!, $pageSize: Int!, $currentPage: Int!) {
      products(
        search: $search
        filter: {}
        sort: {}
        pageSize: $pageSize
        currentPage: $currentPage
      ) {
        items {
          id
          sku
          name
          description {
            html
            __typename
          }
          short_description {
            html
            __typename
          }
          image_url
          author_name
          videos_duration
          resource_amount
          acquired_skills
          has_subtitles
          has_test_and_questions
          review_average_score_round
          product_type
          last_update
          review_average_score
          bestseller
          news_to_date
          test_question_amount
          api_review_count
          type_id
          percent_discount
          discounted_price
          categories {
            id
            __typename
          }
          price_range {
            minimum_price {
              regular_price {
                value
                currency
                __typename
              }
              final_price {
                value
                currency
                __typename
              }
              discount {
                amount_off
                percent_off
                __typename
              }
              __typename
            }
            maximum_price {
              regular_price {
                value
                currency
                __typename
              }
              final_price {
                value
                currency
                __typename
              }
              discount {
                amount_off
                percent_off
                __typename
              }
              __typename
            }
            __typename
          }
          __typename
        }
        total_count
        page_info {
          current_page
          total_pages
          page_size
          __typename
        }
        __typename
      }
    }'
    );
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        //"cURL Error #:" . $err;
        return array(
            "data" => array(
                "products" => array(
                    "items" => array()
                )
            )
        );
    } else {
        return json_decode($response, true);
    }
}

$new_graphql_arr = array();
$graphql_arr = getFromEndpoint();
foreach ($graphql_arr["data"]["products"]["items"] as $key => $value)
    $new_graphql_arr[] = "https://eduj.pl/produkt/{$value["sku"]}";
echo(json_encode($new_graphql_arr, JSON_UNESCAPED_SLASHES));
