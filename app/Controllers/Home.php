<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $url = 'https://rovixai.com/assets/img/logo/logo-black.png';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $headers = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $this->response->setJSON([
            'url' => $url,
            'http_code' => $httpCode,
            'headers' => explode("\r\n", $headers)
        ]);
    }
}
