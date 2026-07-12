<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">Set New Password</h2>
    <p class="text-gray-600 mb-6 text-center text-sm">Choose a new password for your account.</p>

    <?php if (session('errors')): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
        <?= esc(implode(' ', array_map(fn($e) => is_array($e) ? implode(' ', $e) : $e, session('errors')))) ?>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('reset-password/' . esc($token)) ?>">
        <?= csrf_field() ?>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
            <input type="password" name="password" required minlength="8"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500">
        </div>
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
            <input type="password" name="password_confirm" required minlength="8"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500">
        </div>
        <button type="submit"
                class="w-full py-2 px-4 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-md transition-colors">
            Update Password
        </button>
    </form>
</div>
<?= $this->endSection() ?>