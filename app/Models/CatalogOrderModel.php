<?php

namespace App\Models;

class CatalogOrderModel extends BaseModel
{
    protected $table         = 'catalog_orders';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'contact_id', 'conversation_id',
        'catalog_id', 'order_items', 'total_price', 'currency',
        'customer_note', 'status', 'wa_order_id',
        'reminder_sent_at', 'payment_method',
        'created_at', 'updated_at',
    ];
}
