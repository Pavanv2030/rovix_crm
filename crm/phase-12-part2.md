### Prompt 12.2 — Team Management Views & Accept Invitation

```
Continue building team management for Rovix AI Leads Tool.

Add to app/Controllers/TeamController.php:

    public function accept($token)
    {
        $invitationModel = new TeamInvitationModel();
        $invitation = $invitationModel->where('token', $token)->first();

        if (!$invitation) {
            return redirect()->to('/login')->with('error', 'Invalid invitation');
        }

        if ($invitation['accepted_at']) {
            return redirect()->to('/login')->with('error', 'Invitation already accepted');
        }

        if (strtotime($invitation['expires_at']) < time()) {
            return redirect()->to('/login')->with('error', 'Invitation expired');
        }

        // Show signup form with pre-filled email and account
        return view('team/accept', [
            'invitation' => $invitation
        ]);
    }

    public function processAccept()
    {
        $token = $this->request->getPost('token');
        
        $invitationModel = new TeamInvitationModel();
        $invitation = $invitationModel->where('token', $token)->first();

        if (!$invitation || $invitation['accepted_at'] || strtotime($invitation['expires_at']) < time()) {
            return redirect()->to('/login')->with('error', 'Invalid or expired invitation');
        }

        $rules = [
            'name' => 'required|min_length[2]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Create user
        $userModel = new UserModel();
        $userId = $userModel->insert([
            'account_id' => $invitation['account_id'],
            'email' => $invitation['email'],
            'name' => $this->request->getPost('name'),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => $invitation['role'],
            'is_active' => 1
        ]);

        // Mark invitation as accepted
        $invitationModel->update($invitation['id'], [
            'accepted_at' => date('Y-m-d H:i:s')
        ]);

        // Log activity
        ActivityLogModel::log('team.invitation_accepted', 'user', $userId, [
            'email' => $invitation['email'],
            'role' => $invitation['role']
        ]);

        // Auto-login
        session()->set([
            'user_id' => $userId,
            'account_id' => $invitation['account_id'],
            'email' => $invitation['email'],
            'name' => $this->request->getPost('name'),
            'role' => $invitation['role'],
            'logged_in' => true
        ]);

        return redirect()->to('/dashboard')->with('success', 'Welcome to the team!');
    }

    public function updateRole($userId)
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $role = $this->request->getPost('role');

        if (!in_array($role, ['admin', 'agent', 'viewer'])) {
            return $this->response->setJSON(['error' => 'Invalid role'])->setStatusCode(400);
        }

        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if (!$user) {
            return $this->response->setJSON(['error' => 'User not found'])->setStatusCode(404);
        }

        // Prevent self-demotion
        if ($userId == session('user_id') && $role !== 'admin') {
            return $this->response->setJSON(['error' => 'Cannot change your own role'])->setStatusCode(400);
        }

        $userModel->update($userId, ['role' => $role]);

        ActivityLogModel::log('team.role_changed', 'user', $userId, [
            'old_role' => $user['role'],
            'new_role' => $role
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function toggleActive($userId)
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if (!$user) {
            return $this->response->setJSON(['error' => 'User not found'])->setStatusCode(404);
        }

        // Prevent self-deactivation
        if ($userId == session('user_id')) {
            return $this->response->setJSON(['error' => 'Cannot deactivate yourself'])->setStatusCode(400);
        }

        $newStatus = $user['is_active'] ? 0 : 1;
        $userModel->update($userId, ['is_active' => $newStatus]);

        ActivityLogModel::log('team.status_changed', 'user', $userId, [
            'is_active' => $newStatus
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function remove($userId)
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if (!$user) {
            return $this->response->setJSON(['error' => 'User not found'])->setStatusCode(404);
        }

        // Prevent self-removal
        if ($userId == session('user_id')) {
            return $this->response->setJSON(['error' => 'Cannot remove yourself'])->setStatusCode(400);
        }

        // Check if user has assigned conversations
        $conversationModel = new \App\Models\ConversationModel();
        $assignedCount = $conversationModel
            ->where('assigned_agent_id', $userId)
            ->countAllResults();

        if ($assignedCount > 0) {
            return $this->response->setJSON([
                'error' => "Cannot remove user with {$assignedCount} assigned conversations. Reassign them first."
            ])->setStatusCode(400);
        }

        $userModel->delete($userId);

        ActivityLogModel::log('team.member_removed', 'user', $userId, [
            'email' => $user['email']
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    public function resendInvitation($invitationId)
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $invitationModel = new TeamInvitationModel();
        $invitation = $invitationModel->find($invitationId);

        if (!$invitation || $invitation['accepted_at']) {
            return $this->response->setJSON(['error' => 'Invalid invitation'])->setStatusCode(400);
        }

        // Extend expiry
        $invitationModel->update($invitationId, [
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);

        // Resend email
        $this->sendInvitationEmail(
            $invitation['email'],
            $invitation['token'],
            $invitation['role']
        );

        return $this->response->setJSON(['success' => true]);
    }

    public function cancelInvitation($invitationId)
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $invitationModel = new TeamInvitationModel();
        $invitationModel->delete($invitationId);

        return $this->response->setJSON(['success' => true]);
    }

    public function activityLog()
    {
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $activityModel = new ActivityLogModel();
        
        $page = $this->request->getGet('page') ?? 1;
        $perPage = 50;

        $activities = $activityModel
            ->select('activity_logs.*, users.name as user_name, users.email as user_email')
            ->join('users', 'activity_logs.user_id = users.id')
            ->orderBy('activity_logs.created_at', 'DESC')
            ->paginate($perPage, 'default', $page);

        $pager = $activityModel->pager;

        return view('team/activity_log', [
            'activities' => $activities,
            'pager' => $pager
        ]);
    }

Create app/Views/team/index.php:

<?php $this->extend('layouts/main'); ?>

<?php $this->section('content'); ?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Team Management</h1>
            <p class="text-sm text-gray-600 mt-1">Manage team members and permissions</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="<?= base_url('team/activity-log') ?>" 
               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                View Activity Log
            </a>
            <button onclick="showInviteModal()" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Invite Team Member
            </button>
        </div>
    </div>
</div>

<!-- Active Team Members -->
<div class="bg-white rounded-lg border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Team Members (<?= count($users) ?>)</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conversations</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Seen</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900"><?= esc($user['name']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-600"><?= esc($user['email']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <select onchange="updateRole(<?= $user['id'] ?>, this.value)"
                                class="text-sm rounded px-2 py-1 border border-gray-300"
                                <?= $user['id'] == session('user_id') ? 'disabled' : '' ?>>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="agent" <?= $user['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                            <option value="viewer" <?= $user['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-block px-2 py-1 text-xs rounded-full
                            <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?= $user['conversation_count'] ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= $user['last_seen_at'] ? date('M j, g:i A', strtotime($user['last_seen_at'])) : 'Never' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <?php if ($user['id'] != session('user_id')): ?>
                        <button onclick="toggleActive(<?= $user['id'] ?>)" 
                                class="text-blue-600 hover:text-blue-700 mr-3">
                            <?= $user['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                        <button onclick="removeUser(<?= $user['id'] ?>)" 
                                class="text-red-600 hover:text-red-700">
                            Remove
                        </button>
                        <?php else: ?>
                        <span class="text-gray-400">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending Invitations -->
<?php if (!empty($pendingInvitations)): ?>
<div class="bg-white rounded-lg border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Pending Invitations (<?= count($pendingInvitations) ?>)</h3>
    </div>
    
    <div class="divide-y divide-gray-200">
        <?php foreach ($pendingInvitations as $invite): ?>
        <div class="p-4 flex items-center justify-between">
            <div>
                <p class="font-medium text-gray-900"><?= esc($invite['email']) ?></p>
                <p class="text-sm text-gray-600">
                    Role: <?= ucfirst($invite['role']) ?> • 
                    Expires: <?= date('M j, Y', strtotime($invite['expires_at'])) ?>
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                <button onclick="resendInvitation(<?= $invite['id'] ?>)"
                        class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">
                    Resend
                </button>
                <button onclick="cancelInvitation(<?= $invite['id'] ?>)"
                        class="px-3 py-1 text-sm text-red-600 hover:text-red-700">
                    Cancel
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Invite Modal (Alpine.js) -->
<div x-data="{ open: false }" 
     x-show="open" 
     @show-invite-modal.window="open = true"
     style="display: none;"
     class="fixed inset-0 z-50 overflow-y-auto">
    
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black opacity-30" @click="open = false"></div>
        
        <div class="bg-white rounded-lg shadow-xl z-50 max-w-md w-full p-6">
            <h3 class="text-lg font-semibold mb-4">Invite Team Member</h3>
            
            <form id="invite-form" @submit.prevent="submitInvite">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" 
                           name="email" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="agent">Agent</option>
                        <option value="admin">Admin</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" 
                            @click="open = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showInviteModal() {
    window.dispatchEvent(new CustomEvent('show-invite-modal'));
}

async function submitInvite() {
    const form = document.getElementById('invite-form');
    const formData = new FormData(form);
    
    const response = await fetch('/team/invite', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        alert('Invitation sent!');
        location.reload();
    } else {
        const data = await response.json();
        alert(data.error || 'Failed to send invitation');
    }
}

async function updateRole(userId, role) {
    const response = await fetch(`/team/${userId}/update-role`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `role=${role}`
    });
    
    if (response.ok) {
        alert('Role updated');
    } else {
        alert('Failed to update role');
        location.reload();
    }
}

async function toggleActive(userId) {
    if (!confirm('Are you sure?')) return;
    
    const response = await fetch(`/team/${userId}/toggle-active`, {
        method: 'POST'
    });
    
    if (response.ok) {
        location.reload();
    }
}

async function removeUser(userId) {
    if (!confirm('Are you sure you want to remove this user?')) return;
    
    const response = await fetch(`/team/${userId}/remove`, {
        method: 'POST'
    });
    
    if (response.ok) {
        location.reload();
    } else {
        const data = await response.json();
        alert(data.error || 'Failed to remove user');
    }
}

async function resendInvitation(invitationId) {
    const response = await fetch(`/team/invitations/${invitationId}/resend`, {
        method: 'POST'
    });
    
    if (response.ok) {
        alert('Invitation resent!');
    }
}

async function cancelInvitation(invitationId) {
    if (!confirm('Cancel this invitation?')) return;
    
    const response = await fetch(`/team/invitations/${invitationId}/cancel`, {
        method: 'POST'
    });
    
    if (response.ok) {
        location.reload();
    }
}
</script>

<?php $this->endSection(); ?>
```

Continue with Part 3 (Activity Log + Testing)?
