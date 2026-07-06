## PHASE 2: Authentication System (Week 1-2)

### Prompt 2.1 — Auth Controller + Filters

```
Build the authentication system for Rovix AI Leads Tool.

Reference the original wacrm files:
- src/app/(auth)/login/page.tsx
- src/app/(auth)/signup/page.tsx
- src/middleware.ts
- src/lib/auth/roles.ts

Create app/Controllers/AuthController.php:

1. login() GET: Show login form
   - If already authenticated: redirect to /dashboard
   - Load view: auth/login.php

2. attemptLogin() POST: Process login
   - Validate: email required, password required
   - Query profiles table: WHERE email = ? (case-insensitive)
   - Verify password: password_verify($inputPassword, $profile['password_hash'])
   - On success:
     - Start session with: user_id, account_id, account_role, full_name, email, avatar_url
     - session_regenerate_id(true) — prevent session fixation
     - Redirect to /dashboard
   - On failure:
     - Flash error: "Invalid email or password"
     - Redirect back to /login

3. signup() GET: Show signup form
   - If already authenticated: redirect to /dashboard
   - Load view: auth/signup.php

4. register() POST: Process registration
   - Validate:
     - full_name required, min 2 chars
     - email required, valid format, unique in profiles table
     - password required, min 8 chars
     - password_confirm matches password
   - Create account first:
     - Insert into accounts: name = "{full_name}'s Account"
     - Get account_id
   - Create profile:
     - Insert into profiles: user_id (generate UUID), account_id, full_name, email, password_hash (PASSWORD_BCRYPT), account_role='owner'
     - Update accounts.owner_user_id = user_id
   - Auto-login: set session variables
   - Redirect to /dashboard with success message: "Welcome to Rovix AI!"

5. forgotPassword() GET: Show forgot password form
   - Load view: auth/forgot_password.php
   - (For MVP: Just show "Contact support" message, full reset flow can be added later)

6. logout() POST: Destroy session
   - session_destroy()
   - Redirect to /login with message: "You've been logged out"

Create app/Filters/AuthFilter.php:

Implements: CodeIgniter\Filters\FilterInterface

before() method:
- Check if session('user_id') exists
- Excluded routes (allow without auth):
  - login, attemptLogin
  - signup, register
  - forgot-password
  - api/whatsapp/webhook (GET and POST)
  - join/* (for invitation acceptance)
- If not authenticated and route requires auth: redirect to /login
- If authenticated and trying to access /login or /signup: redirect to /dashboard
- Pass through if valid

Create app/Filters/AccountFilter.php:

Implements: CodeIgniter\Filters\FilterInterface

before() method:
- Run AFTER AuthFilter
- Get user_id from session
- Load profile from database: SELECT * FROM profiles WHERE user_id = ?
- If not found: logout (data inconsistency)
- Refresh session with current data:
  - account_id, account_role, full_name, email, avatar_url
- This ensures BaseModel tenant scoping has fresh account_id
- Pass through

Create app/Filters/RoleFilter.php:

Implements: CodeIgniter\Filters\FilterInterface

Constructor accepts $arguments (minimum required role)

before() method:
- Get account_role from session
- Check role_rank($account_role) >= role_rank($requiredRole)
- If insufficient: return 403 response "Access Denied: Insufficient permissions"
- Pass through if valid

Register all filters in app/Config/Filters.php:

public $aliases = [
    'auth' => \App\Filters\AuthFilter::class,
    'account' => \App\Filters\AccountFilter::class,
    'role' => \App\Filters\RoleFilter::class,
];

public $globals = [
    'before' => [
        'auth',
        'account',
    ],
];

Update app/Config/Routes.php with auth routes:

$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attemptLogin');
$routes->get('signup', 'AuthController::signup');
$routes->post('signup', 'AuthController::register');
$routes->get('forgot-password', 'AuthController::forgotPassword');
$routes->post('logout', 'AuthController::logout');

// Dashboard (protected)
$routes->get('/', 'DashboardController::index', ['filter' => 'auth']);
$routes->get('dashboard', 'DashboardController::index', ['filter' => 'auth']);
```

### Prompt 2.2 — Layout Views + Sidebar

```
Create the layout views for Rovix AI Leads Tool with Rovix AI branding.

Use the Rovix AI brand colors from https://rovixai.com/ — dark navy sidebar, clean white content area.

Create app/Views/layouts/main.php:

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Rovix AI Leads Tool') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/assets/img/favicon.ico">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?= view('layouts/partials/sidebar') ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <?= view('layouts/partials/header') ?>
            
            <!-- Flash Messages -->
            <?= view('layouts/partials/flash_messages') ?>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
</body>
</html>

Create app/Views/layouts/auth.php:

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Login') ?> - Rovix AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo -->
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900">
                    <span class="text-blue-900">Rovix</span>
                    <span class="text-gray-500">AI</span>
                </h1>
                <p class="mt-2 text-sm text-gray-600">WhatsApp CRM & Lead Automation</p>
            </div>
            
            <!-- Flash Messages -->
            <?= view('layouts/partials/flash_messages') ?>
            
            <!-- Content -->
            <?= $this->renderSection('content') ?>
        </div>
    </div>
</body>
</html>

Create app/Views/layouts/partials/sidebar.php:

<?php
$currentPath = uri_string();
$unreadCount = 0; // TODO: Query from database in real implementation
?>

<aside class="w-64 bg-[#1B2A4A] text-white flex-shrink-0 hidden md:flex flex-col" x-data="{ open: false }">
    <!-- Logo -->
    <div class="p-6 border-b border-blue-900">
        <h1 class="text-2xl font-bold">
            <span class="text-white">Rovix</span>
            <span class="text-gray-400">AI</span>
        </h1>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1">
        <a href="<?= base_url('dashboard') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'dashboard') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">🏠</span>
            <span>Dashboard</span>
        </a>
        
        <a href="<?= base_url('inbox') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'inbox') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">💬</span>
            <span>Inbox</span>
            <?php if ($unreadCount > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
        
        <a href="<?= base_url('contacts') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'contacts') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">👥</span>
            <span>Contacts</span>
        </a>
        
        <a href="<?= base_url('pipelines') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'pipelines') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">📊</span>
            <span>Pipelines</span>
        </a>
        
        <a href="<?= base_url('broadcasts') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'broadcasts') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">📢</span>
            <span>Broadcasts</span>
        </a>
        
        <a href="<?= base_url('automations') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'automations') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">⚡</span>
            <span>Automations</span>
        </a>
        
        <?php if (has_min_role('admin')): ?>
        <a href="<?= base_url('flows') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'flows') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">🔀</span>
            <span>Flows</span>
            <span class="ml-2 bg-green-500 text-white text-xs rounded px-1.5 py-0.5">Beta</span>
        </a>
        <?php endif; ?>
        
        <?php if (has_min_role('admin')): ?>
        <a href="<?= base_url('settings') ?>" 
           class="flex items-center px-4 py-3 rounded-lg <?= str_starts_with($currentPath, 'settings') ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-900' ?>">
            <span class="mr-3">⚙️</span>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>

Create app/Views/layouts/partials/header.php:

<?php
$profile = current_profile();
?>

<header class="bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
        <!-- Mobile menu button + Page Title -->
        <div class="flex items-center">
            <button class="md:hidden mr-4 text-gray-600">
                <span class="text-2xl">☰</span>
            </button>
            <h2 class="text-2xl font-semibold text-gray-800"><?= esc($pageTitle ?? 'Dashboard') ?></h2>
        </div>
        
        <!-- User Menu -->
        <div class="flex items-center space-x-4" x-data="{ open: false }">
            <div class="relative">
                <button @click="open = !open" class="flex items-center space-x-3 focus:outline-none">
                    <?php if (!empty($profile['avatar_url'])): ?>
                        <img src="<?= esc($profile['avatar_url']) ?>" alt="Avatar" class="w-10 h-10 rounded-full">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-blue-900 text-white flex items-center justify-center font-semibold">
                            <?= strtoupper(substr($profile['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="text-sm font-medium text-gray-700"><?= esc($profile['full_name']) ?></span>
                    <span class="text-xs text-gray-500"><?= esc(ucfirst($profile['account_role'])) ?></span>
                </button>
                
                <!-- Dropdown -->
                <div x-show="open" 
                     @click.away="open = false"
                     x-cloak
                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-50">
                    <a href="<?= base_url('settings/profile') ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Profile Settings
                    </a>
                    <?php if (has_min_role('admin')): ?>
                    <a href="<?= base_url('settings') ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Account Settings
                    </a>
                    <?php endif; ?>
                    <hr class="my-2">
                    <form action="<?= base_url('logout') ?>" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

Create app/Views/layouts/partials/flash_messages.php:

<?php
$success = session()->getFlashdata('success');
$error = session()->getFlashdata('error');
$warning = session()->getFlashdata('warning');
?>

<?php if ($success || $error || $warning): ?>
<div x-data="{ show: true }" 
     x-init="setTimeout(() => show = false, 5000)"
     x-show="show"
     x-cloak
     class="fixed top-4 right-4 z-50 max-w-sm">
    
    <?php if ($success): ?>
    <div class="bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center justify-between">
        <span><?= esc($success) ?></span>
        <button @click="show = false" class="ml-4 text-white hover:text-gray-200">✕</button>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center justify-between">
        <span><?= esc($error) ?></span>
        <button @click="show = false" class="ml-4 text-white hover:text-gray-200">✕</button>
    </div>
    <?php endif; ?>
    
    <?php if ($warning): ?>
    <div class="bg-yellow-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center justify-between">
        <span><?= esc($warning) ?></span>
        <button @click="show = false" class="ml-4 text-white hover:text-gray-200">✕</button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

Create app/Views/auth/login.php:

<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8">
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        Sign in to Rovix AI
    </h2>
    
    <form action="<?= base_url('login') ?>" method="POST" class="space-y-6">
        <?= csrf_field() ?>
        
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" 
                   name="email" 
                   id="email" 
                   required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" 
                   name="password" 
                   id="password" 
                   required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div class="flex items-center justify-between">
            <a href="<?= base_url('forgot-password') ?>" class="text-sm text-blue-600 hover:text-blue-500">
                Forgot password?
            </a>
        </div>
        
        <button type="submit" 
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Sign In
        </button>
    </form>
    
    <p class="mt-6 text-center text-sm text-gray-600">
        Don't have an account?
        <a href="<?= base_url('signup') ?>" class="font-medium text-blue-600 hover:text-blue-500">
            Sign up for free
        </a>
    </p>
</div>
<?= $this->endSection() ?>

Create app/Views/auth/signup.php:

<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="bg-white rounded-lg shadow-md p-8">
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-6">
        Create your Rovix AI account
    </h2>
    
    <form action="<?= base_url('signup') ?>" method="POST" class="space-y-6">
        <?= csrf_field() ?>
        
        <div>
            <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" 
                   name="full_name" 
                   id="full_name" 
                   required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" 
                   name="email" 
                   id="email" 
                   required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" 
                   name="password" 
                   id="password" 
                   required 
                   minlength="8"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
        </div>
        
        <div>
            <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" 
                   name="password_confirm" 
                   id="password_confirm" 
                   required 
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        <button type="submit" 
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            Create Account
        </button>
    </form>
    
    <p class="mt-6 text-center text-sm text-gray-600">
        Already have an account?
        <a href="<?= base_url('login') ?>" class="font-medium text-blue-600 hover:text-blue-500">
            Sign in
        </a>
    </p>
</div>
<?= $this->endSection() ?>

Create app/Views/dashboard/index.php:

<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="space-y-6">
    <!-- Welcome Message -->
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-3xl font-bold text-gray-900">Welcome to Rovix AI Leads Tool</h1>
        <p class="mt-2 text-gray-600">Your WhatsApp CRM dashboard</p>
    </div>
    
    <!-- Stats Cards (Placeholder) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Total Contacts</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">0</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Open Conversations</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">0</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Active Deals</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">0</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Deal Value</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">₹0</div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

Create app/Controllers/DashboardController.php:

<?php
namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $data = [
            'pageTitle' => 'Dashboard'
        ];
        
        return view('dashboard/index', $data);
    }
}
```

### Testing Phase 2

Manual test checklist:

```bash
# 1. Start server
php spark serve

# 2. Visit login page
http://localhost:8080/login

# Test: Login form displays correctly

# 3. Try invalid login
- Enter: wrong@email.com / wrongpass
- Submit

# Test: Error message shows "Invalid email or password"

# 4. Visit signup page
http://localhost:8080/signup

# 5. Create account
- Full Name: Test User
- Email: test@example.com
- Password: testpass123
- Confirm: testpass123
- Submit

# Test: Account created, auto-logged in, redirected to dashboard

# 6. Verify session
- Check dashboard loads
- Check sidebar shows navigation
- Check header shows user name + avatar/initials

# 7. Test navigation
- Click each sidebar link
- Verify active state highlights correctly

# 8. Test logout
- Click user dropdown in header
- Click "Logout"

# Test: Redirected to login, session destroyed

# 9. Test protected routes
- Logout
- Try to visit: http://localhost:8080/dashboard

# Test: Redirected to login

# 10. Test authenticated redirect
- Login
- Try to visit: http://localhost:8080/login

# Test: Redirected to dashboard

# 11. Verify tenant isolation
- Create second account (different email)
- Login as second user
- Verify: separate session, separate account_id

# 12. Test role filter (after creating multi-user accounts in Phase 12)
- Create user with role='viewer'
- Login as viewer
- Try to access: /settings

# Test: 403 Access Denied
```

**Pass Criteria:**
- ✅ Login works with valid credentials
- ✅ Signup creates account + profile, auto-login works
- ✅ Session persists across page loads
- ✅ Logout destroys session completely
- ✅ Protected routes redirect to login
- ✅ Authenticated users can't access login/signup pages
- ✅ AccountFilter refreshes session data from database
- ✅ Sidebar navigation shows correct active states
- ✅ Flash messages display and auto-dismiss after 5s
- ✅ User dropdown works (Alpine.js)
- ✅ Mobile menu button visible on small screens
- ✅ Role-based sidebar items hidden correctly (Flows for admin+, Settings for admin+)

**Common Issues:**
- Session not persisting: Check session driver in .env, verify ci_sessions table exists
- Flash messages not showing: Check Alpine.js loaded, check session()->getFlashdata() syntax
- Redirect loop: Check filter exclusions in AuthFilter, verify routes registered correctly
- Password verify fails: Check password_hash() uses PASSWORD_BCRYPT, check stored hash length (255 chars)
- Account filter crashes: Check profiles.user_id matches session user_id, check FK relationships

---
