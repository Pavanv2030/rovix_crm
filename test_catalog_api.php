<?php
// Quick test to see what Meta API actually returns for products
require __DIR__ . '/vendor/autoload.php';

$accessToken = 'YOUR_ACCESS_TOKEN_HERE'; // Get from whatsapp_config table
$catalogId = '4430992460513850';

$ch = curl_init("https://graph.facebook.com/v21.0/{$catalogId}/products?fields=id,name,retailer_id&limit=5");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
$response = curl_exec($ch);
curl_close($ch);

echo "Raw Meta API response:\n";
echo $response;
