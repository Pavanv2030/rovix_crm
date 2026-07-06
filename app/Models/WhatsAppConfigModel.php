<?php

namespace App\Models;

class WhatsAppConfigModel extends BaseModel
{
    protected $table         = 'whatsapp_config';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['id', 'account_id', 'phone_number_id', 'waba_id', 'access_token', 'business_name', 'status', 'subscription_status', 'webhook_verify_token', 'display_phone_number', 'verified_name', 'quality_rating', 'name_status', 'account_mode', 'number_info_fetched_at', 'business_phone', 'catalog_id', 'catalog_name', 'catalog_synced_at', 'catalog_products', 'flow_public_key', 'flow_private_key'];
}
