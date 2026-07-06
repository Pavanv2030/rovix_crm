<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Rovix extends BaseConfig
{
    public string $encryptionKey = '';
    public string $dailyReportPhones = '';

    public function __construct()
    {
        parent::__construct();

        $this->encryptionKey      = env('rovix.encryptionKey', '');
        $this->dailyReportPhones  = env('rovix.dailyReportPhones', '');
    }

    public function getDailyReportPhones(): array
    {
        if (empty($this->dailyReportPhones)) {
            return [];
        }
        return array_map('trim', explode(',', $this->dailyReportPhones));
    }
}
