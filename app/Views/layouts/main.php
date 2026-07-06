<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Rovix AI Leads Tool') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= @filemtime(FCPATH . 'css/tailwind.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>window.__BASE = '<?= base_url() ?>';</script>
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="stylesheet" href="<?= base_url('css/rovix-ui.css') ?>?v=<?= @filemtime(FCPATH . 'css/rovix-ui.css') ?: time() ?>">
</head>
<body class="bg-gray-50">
    <div id="rx-nav-progress"></div>
    <div class="flex h-screen overflow-hidden" id="app-shell">
        <?= view('layouts/partials/sidebar') ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <?= view('layouts/partials/header') ?>
            <?= view('layouts/partials/flash_messages') ?>

            <main class="flex-1 overflow-y-auto p-6">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
    <script src="<?= base_url('js/app-nav.js') ?>"></script>
</body>
</html>
