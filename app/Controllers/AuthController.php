<?php

namespace App\Controllers;

use App\Models\AccountModel;
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

        $profileModel = new ProfileModel();
        ProfileModel::setBypassAccountScope(true);
        $profile = $profileModel->where('LOWER(email)', $email)->first();
        ProfileModel::setBypassAccountScope(false);

        if (!$profile || !password_verify($password, $profile['password_hash'])) {
            session()->setFlashdata('error', 'Invalid email or password.');
            return redirect()->to(base_url('login'));
        }

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
