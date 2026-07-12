<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8">
    <div class="mb-4 text-center"><?= rx_icon('lock', 'w-12 h-12', 'mx-auto') ?></div>
    <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">Forgot Password?</h2>
    <p class="text-gray-600 mb-6 text-center text-sm">
        Enter your email and we'll send you a reset link if an account exists.
    </p>

    <form method="post" action="<?= base_url('forgot-password') ?>">
        <?= csrf_field() ?>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
            <input type="email" name="email" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500"
                   placeholder="you@company.com">
        </div>
        <button type="submit"
                class="w-full py-2 px-4 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-md transition-colors">
            Send Reset Link
        </button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="<?= base_url('login') ?>" class="text-blue-700 hover:underline">Back to login</a>
    </p>
</div>
<?= $this->endSection() ?>