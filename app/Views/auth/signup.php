<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8">
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">Create your Rovix AI account</h2>

    <form action="<?= base_url('signup') ?>" method="POST" class="space-y-5">
        <?= csrf_field() ?>

        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input type="text" name="full_name" id="full_name" required autocomplete="name"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
            <input type="email" name="email" id="email" required autocomplete="email"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
        </div>

        <div>
            <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirm" id="password_confirm" required autocomplete="new-password"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <button type="submit"
                class="w-full py-2 px-4 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Create Account
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Already have an account?
        <a href="<?= base_url('login') ?>" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
    </p>
</div>
<?= $this->endSection() ?>
