<?php
#запрос для получения страницы
function get($url) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($curl);
    curl_close($curl);
    return $result;
}
#url для страницы товара
function getProductUrl($domain, $productId) {
    return "https://$domain/item/$productId.html";
}
#url для страницы отзывов о товаре
function getFeedbackUrl($productId, $ownerMemberId) {
    return "https://feedback.aliexpress.com/display/productEvaluation.htm?v=2&page=1&currentPage=1&productId=$productId&ownerMemberId=$ownerMemberId&translate=N";
}

#получение информации о товаре
function getProductInfo($domain, $productId) {
    #получаем страницу товара
    $response = get(getProductUrl($domain, $productId));

    #парсим все данные о товаре
    $parseResult = array();
    preg_match('/data: ({.+})/', $response, $parseResult);

    $productInfo = json_decode($parseResult[1]);
    print_r($productInfo);
    $ownerMemberId = $productInfo->commonModule->sellerAdminSeq;
    #получаем страницу отзывов
    $response = get(getFeedbackUrl($productId, $ownerMemberId));

    #парсим все отзывы о товаре
    $parseResult = array();
    preg_match_all('/<dt class="buyer-feedback">(.*?)<span>(.*?)<\/span>(.*?)<\/dt>/s', $response, $parseResult);

    $feedbacks = $parseResult[2];

    #получаем страницу характеристик
    $response = get($productInfo->descriptionModule->descriptionUrl);

    #парсим все характеристики товара
    $parseResult = array();
    #получаем все ссылки на изображения
    preg_match_all('/src="(.*?)"/s', $response, $parseResult);

    $descriptionsLinks = $parseResult[1];
    $descriptionsText = trim(preg_replace('/window.adminAccountId=\d+;/', '', preg_replace('!\s+!', ' ', (str_replace("&nbsp;", "", strip_tags($response))))));

    $result = (object) [
        "name" => $productInfo->titleModule->subject,
        "feedbackCount" => $productInfo->titleModule->feedbackRating->totalValidNum,
        "rating" => $productInfo->titleModule->feedbackRating->averageStar,
        "feedbacks" => [],
        "features" => [],
        "descriptions" => (object) [
            "links" => $descriptionsLinks,
            "text" => $descriptionsText,
        ],
        "countries" => [],
    ];

    #добавляем страны доставки
    foreach ($productInfo->skuModule->productSKUPropertyList as &$productSKUProperty) {
        if ($productSKUProperty->skuPropertyName == "Доставка из" || $productSKUProperty->skuPropertyName == "Ships From") {
            foreach ($productSKUProperty->skuPropertyValues as &$skuPropertyValue){
                array_push($result->countries, $skuPropertyValue->propertyValueDisplayName);
            }
        }
    }
    #добавляем отзывы
    foreach ($feedbacks as &$feedback) {
        array_push($result->feedbacks, $feedback);
    }
    #добавляем характеристики
    foreach ($productInfo->specsModule->props as &$feature) {
        array_push($result->features, $feature);
    }

    return $result;
}

$domains = $object = (object) [
    "RUS" => "aliexpress.ru",
    "USA" => "www.aliexpress.com",
];
$idList = [32815050332, 32815050352, 32815050366, 32815050393, 32815050407, 32815050413,  32815050445, 32815050448, 32815050582];

foreach ($idList as &$productId) {
    $result = getProductInfo($domains->RUS, $productId);
    print_r($result);
    #json_encode($result);
}
?>
