<?php

if (!function_exists('current_user_id')) {
    function current_user_id(): ?string
    {
        return session('user_id') ?: null;
    }
}

if (!function_exists('current_account_id')) {
    function current_account_id(): ?string
    {
        return session('account_id') ?: null;
    }
}

if (!function_exists('current_profile')) {
    function current_profile(): ?array
    {
        return session('profile') ?: null;
    }
}
