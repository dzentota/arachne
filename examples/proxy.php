<?php

$data = array("source" =>  "amazon", "url" => "https://www.amazon.com/gp/offer-listing/B01LXFKSNA/ref=olp_f_usedLikeNew?ie=UTF8&f_freeShipping=true&f_usedLikeNew=true");
$data_string = json_encode($data);

$ch = curl_init('https://apricer:8HLF9bfkKH@realtime.oxylabs.io/v1/queries');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data_string))
);

$result = curl_exec($ch);
$json = json_decode($result, true);
print_r($json['results'][0]['content']);
