<?php

namespace App\Libraries\WhatsApp;

/**
 * WhatsApp's 24-hour customer service window — freeform session messages
 * (text, catalog, product, product_list — anything that isn't an approved
 * Template) are only deliverable within 24h of the customer's last inbound
 * message. Outside that window Meta rejects the send outright. This was a
 * genuine gap: catalog/product sends had zero check for this before firing.
 */
class SessionWindow
{
    private const WINDOW_SECONDS = 24 * 60 * 60;

    public static function isOpen(?string $lastCustomerMessageAt): bool
    {
        if (!$lastCustomerMessageAt) {
            return false;
        }
        $lastMessageTime = strtotime($lastCustomerMessageAt);
        if (!$lastMessageTime) {
            return false;
        }
        return (time() - $lastMessageTime) < self::WINDOW_SECONDS;
    }
}
