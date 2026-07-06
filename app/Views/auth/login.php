<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8">
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">Sign in to Rovix AI</h2>

    <form action="<?= base_url('login') ?>" method="POST" class="space-y-5">
        <?= csrf_field() ?>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" id="email" required autocomplete="email"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" id="password" required autocomplete="current-password"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="text-right">
            <a href="<?= base_url('forgot-password') ?>" class="text-sm text-blue-600 hover:text-blue-500">
                Forgot password?
            </a>
        </div>

        <button type="submit"
                class="w-full py-2 px-4 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Sign In
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Don't have an account?
        <a href="<?= base_url('signup') ?>" class="font-medium text-blue-600 hover:text-blue-500">Sign up for free</a>
    </p>
</div>
<?= $this->endSection() ?>
