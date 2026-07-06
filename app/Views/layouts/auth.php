<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Login') ?> - Rovix AI</title>
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= @filemtime(FCPATH . 'css/tailwind.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900">
                    <span class="text-blue-900">Rovix</span><span class="text-gray-500">AI</span>
                </h1>
                <p class="mt-2 text-sm text-gray-600">WhatsApp CRM &amp; Lead Automation</p>
            </div>

            <?= view('layouts/partials/flash_messages') ?>

            <?= $this->renderSection('content') ?>
        </div>
    </div>
</body>
</html>
