<?php

namespace App\Controllers;

use App\Models\ProfileModel;
use App\Models\AccountInvitationModel;
use App\Models\ActivityLogModel;
use App\Models\ConversationModel;

class TeamController extends BaseController
{
    public function index()
    {
        if (!can_manage_members()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied. Admin or Owner required.');
        }

        ProfileModel::setBypassAccountScope(false);
        $profileModel = new ProfileModel();
        $members = $profileModel
            ->select('profiles.*, COUNT(c.id) AS conversation_count')
            ->join('conversations c', 'c.assigned_agent_id = profiles.user_id', 'left')
            ->groupBy('profiles.id')
            ->orderBy('profiles.created_at', 'ASC')
            ->findAll();

        AccountInvitationModel::setBypassAccountScope(false);
        $inviteModel = new AccountInvitationModel();
        $pending = $inviteModel
            ->where('accepted_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->findAll();

        return view('team/index', [
            'pageTitle'          => 'Team Management',
            'members'            => $members,
            'pendingInvitations' => $pending,
        ]);
    }

    public function invite()
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $rules = [
            'email' => 'required|valid_email',
            'role'  => 'required|in_list[admin,agent,viewer]',
        ];
        if (!$this->validate($rules)) {
            return $this->response->setJSON(['errors' => $this->validator->getErrors()])->setStatusCode(400);
        }

        $email = strtolower(trim($this->request->getPost('email')));
        $role  = $this->request->getPost('role');

        // Check if email already has a profile in this account
        $profileModel = new ProfileModel();
        if ($profileModel->where('email', $email)->first()) {
            return $this->response->setJSON(['error' => 'A team member with this email already exists.'])->setStatusCode(400);
        }

        // Check for existing pending invite
        $inviteModel = new AccountInvitationModel();
        $existing = $inviteModel
            ->where('email', $email)
            ->where('accepted_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
        if ($existing) {
            return $this->response->setJSON(['error' => 'An invitation has already been sent to this email.'])->setStatusCode(400);
        }

        $token   = bin2hex(random_bytes(32));
        $inviteId = generate_uuid();

        $inviteModel->insert([
            'id'                 => $inviteId,
            'account_id'         => session('account_id'),
            'email'              => $email,
            'role'               => $role,
            'token_hash'         => $this->hashInviteToken($token),
            'invited_by_user_id' => session('user_id'),
            'expires_at'         => date('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        $emailSent = $this->sendInviteEmail($email, $token, $role);

        ActivityLogModel::record('team.invite_sent', 'invitation', $inviteId, [
            'email' => $email, 'role' => $role,
        ]);

        if ($emailSent) {
            session()->setFlashdata('success', "Invitation sent to {$email}.");
        } else {
            session()->setFlashdata('error', "Invitation created for {$email}, but the email could not be sent. You can copy the invitation link below to share it manually.");
        }

        return $this->response->setJSON([
            'success'     => true,
            'invite_link' => base_url('team/accept/' . $token),
        ]);
    }

    public function accept(string $token)
    {
        $invite = $this->findInvitationByToken($token);

        if (!$invite)                                               return redirect()->to(base_url('login'))->with('error', 'Invalid invitation link.');
        if ($invite['accepted_at'])                                 return redirect()->to(base_url('login'))->with('error', 'This invitation has already been accepted.');
        if (strtotime($invite['expires_at']) < time())             return redirect()->to(base_url('login'))->with('error', 'This invitation has expired.');

        return view('team/accept', ['invitation' => $invite, 'token' => $token]);
    }

    public function processAccept()
    {
        $token  = $this->request->getPost('token');
        $invite = $this->findInvitationByToken($token);

        if (!$invite || $invite['accepted_at'] || strtotime($invite['expires_at']) < time()) {
            return redirect()->to(base_url('login'))->with('error', 'Invalid or expired invitation.');
        }

        $rules = [
            'full_name'        => 'required|min_length[2]',
            'password'         => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = generate_uuid();

        ProfileModel::setBypassAccountScope(true);
        $profileModel = new ProfileModel();
        $profileId = generate_uuid();
        $profileModel->insert([
            'id'            => $profileId,
            'user_id'       => $userId,
            'account_id'    => $invite['account_id'],
            'full_name'     => trim($this->request->getPost('full_name')),
            'email'         => $invite['email'],
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'account_role'  => $invite['role'],
            'is_active'     => 1,
        ]);
        $profile = $profileModel->find($profileId);
        ProfileModel::setBypassAccountScope(false);

        AccountInvitationModel::setBypassAccountScope(true);
        $inviteModel->update($invite['id'], [
            'accepted_at'         => date('Y-m-d H:i:s'),
            'accepted_by_user_id' => $userId,
        ]);
        AccountInvitationModel::setBypassAccountScope(false);

        // Log before setting session (no session yet)
        $logModel = new ActivityLogModel();
        $logModel->insert([
            'id'          => generate_uuid(),
            'account_id'  => $invite['account_id'],
            'user_id'     => $userId,
            'action'      => 'team.invitation_accepted',
            'entity_type' => 'profile',
            'entity_id'   => $profileId,
            'metadata'    => json_encode(['email' => $invite['email'], 'role' => $invite['role']]),
            'ip_address'  => service('request')->getIPAddress(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        session()->set([
            'user_id'      => $profile['user_id'],
            'account_id'   => $profile['account_id'],
            'account_role' => $profile['account_role'],
            'full_name'    => $profile['full_name'],
            'email'        => $profile['email'],
            'avatar_url'   => null,
            'profile'      => $profile,
        ]);

        return redirect()->to(base_url('dashboard'))->with('success', 'Welcome to the team!');
    }

    public function updateRole(string $profileId)
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $role = $this->request->getPost('role');
        if (!in_array($role, ['admin', 'agent', 'viewer'])) {
            return $this->response->setJSON(['error' => 'Invalid role'])->setStatusCode(400);
        }

        $profileModel = new ProfileModel();
        $profile = $profileModel->find($profileId);
        if (!$profile) {
            return $this->response->setJSON(['error' => 'Member not found'])->setStatusCode(404);
        }

        if ($profile['user_id'] === session('user_id')) {
            return $this->response->setJSON(['error' => 'You cannot change your own role.'])->setStatusCode(400);
        }

        // Owner role cannot be assigned via this UI
        if ($profile['account_role'] === 'owner') {
            return $this->response->setJSON(['error' => 'Cannot change the account owner\'s role.'])->setStatusCode(400);
        }

        $profileModel->update($profileId, ['account_role' => $role]);

        ActivityLogModel::record('team.role_changed', 'profile', $profileId, [
            'old_role' => $profile['account_role'], 'new_role' => $role,
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function toggleActive(string $profileId)
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $profileModel = new ProfileModel();
        $profile = $profileModel->find($profileId);
        if (!$profile) {
            return $this->response->setJSON(['error' => 'Member not found'])->setStatusCode(404);
        }

        if ($profile['user_id'] === session('user_id')) {
            return $this->response->setJSON(['error' => 'You cannot deactivate yourself.'])->setStatusCode(400);
        }

        $newStatus = ($profile['is_active'] ?? 1) ? 0 : 1;
        $profileModel->update($profileId, ['is_active' => $newStatus]);

        ActivityLogModel::record('team.status_changed', 'profile', $profileId, [
            'is_active' => $newStatus,
        ]);

        return $this->response->setJSON(['success' => true, 'is_active' => $newStatus]);
    }

    public function remove(string $profileId)
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $profileModel = new ProfileModel();
        $profile = $profileModel->find($profileId);
        if (!$profile) {
            return $this->response->setJSON(['error' => 'Member not found'])->setStatusCode(404);
        }

        if ($profile['user_id'] === session('user_id')) {
            return $this->response->setJSON(['error' => 'You cannot remove yourself.'])->setStatusCode(400);
        }

        if ($profile['account_role'] === 'owner') {
            return $this->response->setJSON(['error' => 'Cannot remove the account owner.'])->setStatusCode(400);
        }

        $assigned = (new ConversationModel())
            ->where('assigned_agent_id', $profile['user_id'])
            ->countAllResults();
        if ($assigned > 0) {
            return $this->response->setJSON([
                'error' => "Cannot remove — {$assigned} conversation(s) are still assigned to this member. Reassign them first.",
            ])->setStatusCode(400);
        }

        ActivityLogModel::record('team.member_removed', 'profile', $profileId, [
            'email' => $profile['email'],
        ]);
        $profileModel->delete($profileId);

        return $this->response->setJSON(['success' => true]);
    }

    public function resendInvitation(string $inviteId)
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $inviteModel = new AccountInvitationModel();
        $invite = $inviteModel->find($inviteId);
        if (!$invite || $invite['accepted_at']) {
            return $this->response->setJSON(['error' => 'Invalid invitation'])->setStatusCode(400);
        }

        $newToken  = bin2hex(random_bytes(32));
        $newExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        $inviteModel->update($inviteId, [
            'expires_at' => $newExpiry,
            'token_hash' => $this->hashInviteToken($newToken),
        ]);
        $emailSent = $this->sendInviteEmail($invite['email'], $newToken, $invite['role']);

        if ($emailSent) {
            session()->setFlashdata('success', "Invitation resent to {$invite['email']}.");
        } else {
            session()->setFlashdata('error', "Failed to send email to {$invite['email']}. You can copy the invitation link below to share it manually.");
        }

        return $this->response->setJSON(['success' => true]);
    }

    public function inviteLink(string $inviteId)
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $inviteModel = new AccountInvitationModel();
        $invite = $inviteModel->find($inviteId);
        if (!$invite || $invite['accepted_at']) {
            return $this->response->setJSON(['error' => 'Invalid invitation'])->setStatusCode(400);
        }

        $newToken = bin2hex(random_bytes(32));
        $inviteModel->update($inviteId, [
            'token_hash' => $this->hashInviteToken($newToken),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'link'    => base_url('team/accept/' . $newToken),
        ]);
    }

    public function cancelInvitation(string $inviteId)
    {
        if (!can_manage_members()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        (new AccountInvitationModel())->delete($inviteId);
        return $this->response->setJSON(['success' => true]);
    }

    public function activityLog()
    {
        if (!can_manage_members()) {
            return redirect()->to(base_url('dashboard'))->with('error', 'Access denied.');
        }

        $page    = (int)($this->request->getGet('page') ?? 1);
        $perPage = 50;

        ActivityLogModel::setBypassAccountScope(false);
        $logModel = new ActivityLogModel();
        $activities = $logModel
            ->select('activity_logs.*, profiles.full_name AS user_name, profiles.email AS user_email')
            ->join('profiles', 'profiles.user_id = activity_logs.user_id', 'left')
            ->orderBy('activity_logs.created_at', 'DESC')
            ->paginate($perPage, 'default', $page);

        return view('team/activity_log', [
            'pageTitle'  => 'Activity Log',
            'activities' => $activities,
            'pager'      => $logModel->pager,
        ]);
    }

    private function hashInviteToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function findInvitationByToken(string $token): ?array
    {
        AccountInvitationModel::setBypassAccountScope(true);
        $inviteModel = new AccountInvitationModel();
        $invite = $inviteModel->where('token_hash', $this->hashInviteToken($token))->first();

        // Legacy invites stored plaintext before hashing migration
        if (!$invite && strlen($token) === 64 && ctype_xdigit($token)) {
            $invite = $inviteModel->where('token_hash', $token)->first();
        }
        AccountInvitationModel::setBypassAccountScope(false);

        return $invite ?: null;
    }

    private function sendInviteEmail(string $email, string $token, string $role): bool
    {
        $link        = base_url('team/accept/' . $token);
        // full_name is user-controlled (set via profile edit) — must be
        // escaped before landing in HTML sent to a third party's inbox.
        $accountName = esc(session('full_name')) . "'s Team";
        $roleLabel   = esc(ucfirst($role));

        try {
            $config = config('Email');
            $fromEmail = !empty($config->fromEmail) ? $config->fromEmail : 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'rovix-crm.com');
            $fromName  = !empty($config->fromName) ? $config->fromName : 'Rovix CRM';

            $mail = \Config\Services::email();
            $mail->setFrom($fromEmail, $fromName);
            $mail->setTo($email);
            $mail->setMailType('html');
            $mail->setSubject("You've been invited to join {$accountName} on Rovix AI");
            $mail->setMessage("
                <h2>Team Invitation</h2>
                <p>You've been invited to join <strong>{$accountName}</strong> as a <strong>{$roleLabel}</strong>.</p>
                <p><a href=\"{$link}\" style=\"background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;\">Accept Invitation</a></p>
                <p>Or copy this link: {$link}</p>
                <p>This invitation expires in 7 days.</p>
            ");

            if (!$mail->send()) {
                log_message('error', 'Team invite email failed to send: ' . $mail->printDebugger(['headers', 'subject', 'body']));
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Team invite email failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
}
