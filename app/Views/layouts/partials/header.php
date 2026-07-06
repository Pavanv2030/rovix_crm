<?php
helper('auth');
$profile = current_profile();
?>
<header class="rx-topbar flex-shrink-0">
    <div class="flex items-center justify-between w-full">
        <div class="flex items-center">
            <h2><?= esc($pageTitle ?? 'Dashboard') ?></h2>
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center space-x-3 focus:outline-none">
                <?php if (!empty($profile['avatar_url'])): ?>
                    <img src="<?= esc($profile['avatar_url']) ?>" alt="Avatar" class="rx-avatar object-cover">
                <?php else: ?>
                    <div class="rx-avatar">
                        <?= strtoupper(substr($profile['full_name'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="text-left hidden sm:block">
                    <div class="text-sm font-medium text-gray-700"><?= esc($profile['full_name'] ?? '') ?></div>
                    <div class="text-xs text-gray-500"><?= esc(ucfirst($profile['account_role'] ?? '')) ?></div>
                </div>
                <span class="text-gray-400 text-xs">▼</span>
            </button>

            <div x-show="open"
                 @click.away="open = false"
                 x-cloak
                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-100">
                <a href="<?= base_url('settings/profile') ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Profile Settings
                </a>
                <?php if (has_min_role('admin')): ?>
                <a href="<?= base_url('settings') ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Account Settings
                </a>
                <?php endif; ?>
                <hr class="my-1 border-gray-100">
                <form action="<?= base_url('logout') ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
