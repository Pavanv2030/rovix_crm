<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class WhatsApp extends BaseConfig
{
    public string $phoneNumberId = '';
    public string $wabaId = '';
    public string $accessToken = '';
    public string $verifyToken = '';
    public string $metaAppSecret = '';

    public function __construct()
    {
        parent::__construct();

        $this->phoneNumberId = env('whatsapp.phoneNumberId', '');
        $this->wabaId        = env('whatsapp.wabaId', '');
        $this->accessToken   = env('whatsapp.accessToken', '');
        $this->verifyToken   = env('whatsapp.verifyToken', '');
        $this->metaAppSecret = env('whatsapp.metaAppSecret', '');
    }
}
