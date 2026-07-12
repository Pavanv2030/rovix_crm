<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation — Rovix AI</title>
    <link rel="stylesheet" href="<?= base_url('css/tailwind.css') ?>?v=<?= @filemtime(FCPATH . 'css/tailwind.css') ?: time() ?>">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-md">

    <!-- Logo / Brand -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-2 text-xl font-bold text-blue-900">
            <?= rx_icon('robot', 'w-6 h-6') ?> Rovix AI
        </div>
        <p class="text-sm text-gray-500 mt-1">WhatsApp CRM</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-8">

        <!-- Flash errors -->
        <?php if (session()->getFlashdata('errors')): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <?php foreach ((array)session()->getFlashdata('errors') as $err): ?>
            <div><?= esc($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">You've been invited</h2>
            <p class="text-sm text-gray-500 mt-1">
                Join as a
                <span class="font-semibold text-gray-700"><?= ucfirst(esc($invitation['role'])) ?></span>.
                Fill in your details to accept.
            </p>
        </div>

        <form method="POST" action="<?= base_url('team/accept/process') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token) ?>">

            <!-- Email (read-only) -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" value="<?= esc($invitation['email'] ?? '') ?>" disabled
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-500">
            </div>

            <!-- Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Your full name</label>
                <input type="text" name="full_name" required
                       value="<?= esc(old('full_name')) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400"
                       placeholder="Jane Smith">
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Create a password</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400"
                       placeholder="Minimum 8 characters">
            </div>

            <!-- Confirm -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                <input type="password" name="password_confirm" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400"
                       placeholder="Repeat password">
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                Accept &amp; Join Team
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-4">
            Already have an account? <a href="<?= base_url('login') ?>" class="text-blue-600 hover:underline">Sign in</a>
        </p>
    </div>

</div>
</body>
</html>
