<?php

namespace App\Libraries;

class BroadcastAudience
{
    public static function unsubscribedTagId(string $accountId): ?string
    {
        $tag = \Config\Database::connect()->table('tags')
            ->where('account_id', $accountId)
            ->where('name', 'Unsubscribed')
            ->get()
            ->getRowArray();

        return $tag['id'] ?? null;
    }

    public static function isUnsubscribed(string $contactId, ?string $unsubscribedTagId = null): bool
    {
        if (!$unsubscribedTagId) {
            return false;
        }

        return \Config\Database::connect()->table('contact_tags')
            ->where('contact_id', $contactId)
            ->where('tag_id', $unsubscribedTagId)
            ->countAllResults() > 0;
    }

    /**
     * @param list<string> $tagIds
     * @return list<string> Tag IDs that belong to the account
     */
    public static function filterOwnedTagIds(array $tagIds, string $accountId): array
    {
        if (empty($tagIds)) {
            return [];
        }

        $owned = \Config\Database::connect()->table('tags')
            ->select('id')
            ->where('account_id', $accountId)
            ->whereIn('id', $tagIds)
            ->get()
            ->getResultArray();

        return array_column($owned, 'id');
    }
}