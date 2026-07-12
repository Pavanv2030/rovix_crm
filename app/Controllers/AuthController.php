<?php

namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\PasswordResetModel;
use App\Models\ProfileModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }
        return view('auth/login', ['pageTitle' => 'Sign In']);
    }

    public function attemptLogin()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        if (!$this->validate($rules)) {
            session()->setFlashdata('error', 'Please enter a valid email and password.');
            return redirect()->to(base_url('login'));
        }

        $email    = strtolower(trim($this->request->getPost('email')));
        $password = $this->request->getPost('password');

        // Check if account is locked due to failed attempts
        $cache = \Config\Services::cache();
        $lockKey = 'login_lock_' . md5($email);
        $attemptsKey = 'login_attempts_' . md5($email);

        if ($cache->get($lockKey)) {
            session()->setFlashdata('error', 'Account temporarily locked. Try again in 15 minutes.');
            return redirect()->to(base_url('login'));
        }

        $profileModel = new ProfileModel();
        ProfileModel::setBypassAccountScope(true);
        $profile = $profileModel->where('LOWER(email)', $email)->first();
        ProfileModel::setBypassAccountScope(false);

        // Always verify against a hash, even if user doesn't exist (prevents timing attack)
        $hashToVerify = $profile['password_hash'] ?? password_hash('dummy_password_for_timing', PASSWORD_BCRYPT);
        $valid = password_verify($password, $hashToVerify);

        if (!$profile || !$valid) {
            // Increment failed attempts
            $attempts = (int)$cache->get($attemptsKey) + 1;
            $cache->save($attemptsKey, $attempts, 900); // 15 minutes

            if ($attempts >= 5) {
                $cache->save($lockKey, true, 900); // Lock for 15 minutes
                log_message('warning', "Account locked due to failed login attempts: {$email}");
                session()->setFlashdata('error', 'Too many failed attempts. Account locked for 15 minutes.');
            } else {
                session()->setFlashdata('error', 'Invalid email or password.');
            }
            return redirect()->to(base_url('login'));
        }

        // Successful login - clear failed attempts
        $cache->delete($attemptsKey);
        $cache->delete($lockKey);

        $this->setSession($profile);
        return redirect()->to(base_url('dashboard'));
    }

    public function signup()
    {
        if (session('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }
        return view('auth/signup', ['pageTitle' => 'Create Account']);
    }

    public function register()
    {
        $rules = [
            'full_name'        => 'required|min_length[2]',
            'email'            => 'required|valid_email',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (!$this->validate($rules)) {
            session()->setFlashdata('error', implode(' ', array_map(fn($e) => implode(' ', $e), $this->validator->getErrors())));
            return redirect()->to(base_url('signup'));
        }

        $email = strtolower(trim($this->request->getPost('email')));

        ProfileModel::setBypassAccountScope(true);
        $existing = (new ProfileModel())->where('LOWER(email)', $email)->first();
        ProfileModel::setBypassAccountScope(false);

        if ($existing) {
            session()->setFlashdata('error', 'An account with this email already exists.');
            return redirect()->to(base_url('signup'));
        }

        $fullName = trim($this->request->getPost('full_name'));
        $password = $this->request->getPost('password');

        AccountModel::setBypassAccountScope(true);
        $accountModel = new AccountModel();
        $accountId    = generate_uuid();
        $accountModel->insert([
            'id'   => $accountId,
            'name' => $fullName . "'s Account",
        ]);
        AccountModel::setBypassAccountScope(false);

        $userId = generate_uuid();

        ProfileModel::setBypassAccountScope(true);
        $profileModel = new ProfileModel();
        $profileModel->insert([
            'id'            => generate_uuid(),
            'user_id'       => $userId,
            'account_id'    => $accountId,
            'full_name'     => $fullName,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'account_role'  => 'owner',
        ]);

        $profile = $profileModel->where('user_id', $userId)->first();
        ProfileModel::setBypassAccountScope(false);

        AccountModel::setBypassAccountScope(true);
        (new AccountModel())->update($accountId, ['owner_user_id' => $userId]);
        AccountModel::setBypassAccountScope(false);

        $this->setSession($profile);
        session()->setFlashdata('success', 'Welcome to Rovix AI!');
        return redirect()->to(base_url('dashboard'));
    }

    public function forgotPassword()
    {
        return view('auth/forgot_password', ['pageTitle' => 'Forgot Password']);
    }

    public function sendPasswordReset()
    {
        $email = strtolower(trim($this->request->getPost('email') ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session()->setFlashdata('error', 'Please enter a valid email address.');
            return redirect()->to(base_url('forgot-password'));
        }

        ProfileModel::setBypassAccountScope(true);
        $profile = (new ProfileModel())->where('LOWER(email)', $email)->first();
        ProfileModel::setBypassAccountScope(false);

        if ($profile) {
            $token   = bin2hex(random_bytes(32));
            $resetId = generate_uuid();

            (new PasswordResetModel())->insert([
                'id'         => $resetId,
                'profile_id' => $profile['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->sendPasswordResetEmail($email, $token);
        }

        session()->setFlashdata('success', 'If an account exists for that email, a reset link has been sent.');
        return redirect()->to(base_url('login'));
    }

    public function resetPasswordForm(string $token)
    {
        if (!$this->findValidPasswordReset($token)) {
            return redirect()->to(base_url('login'))->with('error', 'Invalid or expired reset link.');
        }

        return view('auth/reset_password', [
            'pageTitle' => 'Reset Password',
            'token'     => $token,
        ]);
    }

    public function resetPassword(string $token)
    {
        $reset = $this->findValidPasswordReset($token);
        if (!$reset) {
            return redirect()->to(base_url('login'))->with('error', 'Invalid or expired reset link.');
        }

        $rules = [
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        ProfileModel::setBypassAccountScope(true);
        $profileModel = new ProfileModel();
        $profileModel->update($reset['profile_id'], [
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
        ]);
        ProfileModel::setBypassAccountScope(false);

        (new PasswordResetModel())->update($reset['id'], ['used_at' => date('Y-m-d H:i:s')]);

        session()->setFlashdata('success', 'Password updated. You can sign in now.');
        return redirect()->to(base_url('login'));
    }

    public function logout()
    {
        // session()->destroy() calls session_destroy() immediately, which
        // removes the session's persistent storage — any setFlashdata()
        // call after that has nothing left to write into, so the message
        // silently never shows. Clear the auth keys and regenerate the
        // session id (invalidates the old authenticated session) instead,
        // so the flash message set on this same request actually survives
        // into the login page's next request.
        session()->remove(['user_id', 'account_id', 'account_role', 'full_name', 'email', 'avatar_url', 'profile']);
        session_regenerate_id(true);
        session()->setFlashdata('success', "You've been logged out.");
        return redirect()->to(base_url('login'));
    }

    private function findValidPasswordReset(string $token): ?array
    {
        $reset = (new PasswordResetModel())
            ->where('token_hash', hash('sha256', $token))
            ->where('used_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        return $reset ?: null;
    }

    private function sendPasswordResetEmail(string $email, string $token): void
    {
        $link = base_url('reset-password/' . $token);

        try {
            $emailService = \Config\Services::email();
            $emailService->setTo($email);
            $emailService->setSubject('Reset your Rovix CRM password');
            $emailService->setMessage(
                '<p>Click the link below to reset your password. This link expires in 1 hour.</p>'
                . '<p><a href="' . esc($link) . '">' . esc($link) . '</a></p>'
            );
            $emailService->send();
        } catch (\Throwable $e) {
            log_message('error', 'Password reset email failed: ' . $e->getMessage());
        }
    }

    private function setSession(array $profile): void
    {
        session()->set([
            'user_id'      => $profile['user_id'],
            'account_id'   => $profile['account_id'],
            'account_role' => $profile['account_role'],
            'full_name'    => $profile['full_name'],
            'email'        => $profile['email'],
            'avatar_url'   => $profile['avatar_url'] ?? null,
            'profile'      => $profile,
        ]);
        session_regenerate_id(true);
    }
}
