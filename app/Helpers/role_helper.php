<?php

if (!function_exists('role_rank')) {
    function role_rank(string $role): int
    {
        return match($role) {
            'owner'  => 4,
            'admin'  => 3,
            'agent'  => 2,
            'viewer' => 1,
            default  => 0,
        };
    }
}

if (!function_exists('has_min_role')) {
    function has_min_role(string $required): bool
    {
        $profile = current_profile();
        if (!$profile) return false;
        return role_rank($profile['account_role']) >= role_rank($required);
    }
}

if (!function_exists('can_send_messages')) {
    function can_send_messages(): bool { return has_min_role('agent'); }
}

if (!function_exists('can_manage_members')) {
    function can_manage_members(): bool { return has_min_role('admin'); }
}

if (!function_exists('can_edit_settings')) {
    function can_edit_settings(): bool { return has_min_role('admin'); }
}

if (!function_exists('can_view_only')) {
    function can_view_only(): bool
    {
        $profile = current_profile();
        return $profile && $profile['account_role'] === 'viewer';
    }
}

if (!function_exists('can_delete_account')) {
    function can_delete_account(): bool { return has_min_role('owner'); }
}

if (!function_exists('can_transfer_ownership')) {
    function can_transfer_ownership(): bool { return has_min_role('owner'); }
}
