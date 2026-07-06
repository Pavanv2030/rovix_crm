<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8 text-center">
    <div class="mb-4"><?= rx_icon('lock', 'w-12 h-12', 'mx-auto') ?></div>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Forgot Password?</h2>
    <p class="text-gray-600 mb-6">
        Password reset is not available in this version.<br>
        Please contact your account administrator or support.
    </p>
    <a href="<?= base_url('login') ?>"
       class="inline-block py-2 px-6 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-md transition-colors">
        Back to Login
    </a>
</div>
<?= $this->endSection() ?>
