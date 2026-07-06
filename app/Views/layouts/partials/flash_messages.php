<?php
$success = session()->getFlashdata('success');
$error   = session()->getFlashdata('error');
$warning = session()->getFlashdata('warning');
?>
<?php if ($success || $error || $warning): ?>
<div x-data="{ show: true }"
     x-init="setTimeout(() => show = false, 5000)"
     x-show="show"
     x-cloak
     class="fixed top-4 right-4 z-50 max-w-sm space-y-2">

    <?php if ($success): ?>
    <div class="bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center justify-between">
        <span class="flex items-center gap-2"><?= rx_icon('check-circle', 'w-5 h-5', '!text-white') ?><?= esc($success) ?></span>
        <button @click="show = false" class="ml-4 text-white hover:text-gray-200 font-bold"><?= rx_icon('x', 'w-4 h-4', '!text-white') ?></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center justify-between">
        <span class="flex items-center gap-2"><?= rx_icon('x-circle', 'w-5 h-5', '!text-white') ?><?= esc($error) ?></span>
        <button @click="show = false" class="ml-4 text-white hover:text-gray-200 font-bold"><?= rx_icon('x', 'w-4 h-4', '!text-white') ?></button>
    </div>
    <?php endif; ?>

    <?php if ($warning): ?>
    <div class="bg-yellow-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center justify-between">
        <span class="flex items-center gap-2"><?= rx_icon('warning', 'w-5 h-5', '!text-white') ?><?= esc($warning) ?></span>
        <button @click="show = false" class="ml-4 text-white hover:text-gray-200 font-bold"><?= rx_icon('x', 'w-4 h-4', '!text-white') ?></button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
