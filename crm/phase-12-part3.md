### Prompt 12.3 — Activity Log View & Testing

```
Complete team management with activity log view and testing checklist.

Create app/Views/team/activity_log.php:

<?php $this->extend('layouts/main'); ?>

<?php $this->section('content'); ?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Activity Log</h1>
            <p class="text-sm text-gray-600 mt-1">Track all team actions and changes</p>
        </div>
        
        <a href="<?= base_url('team') ?>" 
           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
            ← Back to Team
        </a>
    </div>
</div>

<div class="bg-white rounded-lg border border-gray-200">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900"><?= esc($activity['user_name']) ?></div>
                        <div class="text-sm text-gray-500"><?= esc($activity['user_email']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            <?= esc($activity['action']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-600 max-w-md">
                            <?php if ($activity['entity_type']): ?>
                                <?= ucfirst($activity['entity_type']) ?> #<?= $activity['entity_id'] ?>
                            <?php endif; ?>
                            
                            <?php if ($activity['metadata']): ?>
                                <?php $meta = json_decode($activity['metadata'], true); ?>
                                <?php if ($meta): ?>
                                <div class="mt-1 text-xs text-gray-500">
                                    <?php foreach ($meta as $key => $value): ?>
                                        <span class="mr-2"><?= esc($key) ?>: <strong><?= esc($value) ?></strong></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= esc($activity['ip_address']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($pager->getPageCount() > 1): ?>
    <div class="px-6 py-4 border-t border-gray-200">
        <?= $pager->links() ?>
    </div>
    <?php endif; ?>
</div>

<?php $this->endSection(); ?>

Create app/Views/team/accept.php:

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accept Invitation - Rovix AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-semibold text-gray-900 mb-2">Accept Invitation</h2>
            <p class="text-sm text-gray-600 mb-6">
                You've been invited to join as a <strong><?= ucfirst($invitation['role']) ?></strong>
            </p>
            
            <form method="POST" action="<?= base_url('team/accept/process') ?>">
                <input type="hidden" name="token" value="<?= esc($invitation['token']) ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" 
                           value="<?= esc($invitation['email']) ?>" 
                           disabled
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                    <input type="text" 
                           name="name" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" 
                           name="password" 
                           required
                           minlength="8"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" 
                           name="password_confirm" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <button type="submit" 
                        class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Accept & Join Team
                </button>
            </form>
        </div>
    </div>
</body>
</html>

Add routes to app/Config/Routes.php:

GET /team → TeamController::index
POST /team/invite → TeamController::invite
GET /team/accept/{token} → TeamController::accept
POST /team/accept/process → TeamController::processAccept
POST /team/{id}/update-role → TeamController::updateRole
POST /team/{id}/toggle-active → TeamController::toggleActive
POST /team/{id}/remove → TeamController::remove
POST /team/invitations/{id}/resend → TeamController::resendInvitation
POST /team/invitations/{id}/cancel → TeamController::cancelInvitation
GET /team/activity-log → TeamController::activityLog

Update app/Filters/RoleFilter.php (create if not exists):

<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = session('role');
        
        // If arguments passed, check if user has one of the allowed roles
        if ($arguments && !in_array($role, $arguments)) {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}

Update app/Config/Filters.php:

public $aliases = [
    // ... existing filters
    'role' => \App\Filters\RoleFilter::class,
];

Update last_seen_at in app/Filters/AuthFilter.php:

public function before(RequestInterface $request, $arguments = null)
{
    if (!session('logged_in')) {
        return redirect()->to('/login');
    }
    
    // Update last_seen_at
    $userModel = new \App\Models\UserModel();
    $userModel->update(session('user_id'), [
        'last_seen_at' => date('Y-m-d H:i:s')
    ]);
}
```

### Testing Phase 12 (Team Management)

Manual test checklist:

```bash
# 1. Navigate to team management
http://localhost:8080/team

# Test: Only admins can access (agents/viewers get "Access denied")

# 2. Invite new team member
- Click "Invite Team Member"
- Email: newuser@test.com
- Role: Agent
- Click "Send Invitation"

# Test:
- Invitation sent
- Email received with invite link
- Invitation appears in "Pending Invitations"

# 3. Accept invitation
- Click link in email
- Fill name and password
- Submit

# Test:
- User account created
- Auto-logged in
- Role assigned correctly
- Invitation marked as accepted

# 4. Change user role
- Admin changes agent to viewer
- Select new role from dropdown

# Test:
- Role updated in database
- Activity logged
- User sees permission changes on next action

# 5. Deactivate user
- Click "Deactivate" on user

# Test:
- User marked inactive
- Cannot login (show error on login attempt)
- Activity logged

# 6. Reactivate user
- Click "Activate" on inactive user

# Test: User can login again

# 7. Remove user
- Click "Remove" on user

# Test:
- Cannot remove if has assigned conversations (error shown)
- Reassign conversations first
- User deleted successfully
- Activity logged

# 8. Resend invitation
- Click "Resend" on pending invitation

# Test:
- Expiry extended by 7 days
- Email resent

# 9. Cancel invitation
- Click "Cancel" on pending invitation

# Test: Invitation deleted, link no longer works

# 10. Self-protection
- Admin tries to change own role
- Admin tries to deactivate self
- Admin tries to remove self

# Test: All blocked with error message

# 11. View activity log
- Click "View Activity Log"

# Test:
- Shows all team actions
- User name, timestamp, action, details
- Pagination works
- Sorted by newest first

# 12. Activity logging
- Perform various actions (invite, role change, etc.)
- Check activity log

# Test: Each action logged with correct metadata

# 13. Permission enforcement
# Login as agent, try to access:
http://localhost:8080/team

# Test: Redirected with "Access denied"

# 14. Role-based features
# Login as viewer:
- Try to send message in inbox

# Test: Send button hidden or disabled

# 15. Expired invitation
- Manually set invitation expires_at to past date
- Try to accept

# Test: "Invitation expired" error

# 16. Duplicate email
- Try to invite email that already exists

# Test: "User already exists" error

# 17. Pending invitation for same email
- Try to invite same email twice

# Test: "Invitation already sent" error

# 18. Email delivery
- Check email sent via CodeIgniter email service

# Test: Invitation email received with correct link

# 19. Tenant isolation
- Login as different account
- Check team list

# Test: Only see own account's team members

# 20. Last seen tracking
- User performs actions
- Check last_seen_at in database

# Test: Updates on each request
```

**Pass Criteria:**
- ✅ Only admins can access team management
- ✅ Invite workflow works (send, receive, accept)
- ✅ Role changes apply correctly
- ✅ Activate/deactivate toggles work
- ✅ Remove user works (blocks if has conversations)
- ✅ Self-protection prevents admin self-harm
- ✅ Resend/cancel invitation works
- ✅ Activity log captures all actions
- ✅ Activity log shows user, timestamp, details
- ✅ Pagination works on activity log
- ✅ Expired invitations rejected
- ✅ Duplicate email prevention works
- ✅ Email delivery works
- ✅ Tenant isolation (no cross-account access)
- ✅ Last seen updates correctly
- ✅ Role-based permissions enforced across app
- ✅ Viewer cannot send messages
- ✅ Agent cannot access settings
- ✅ Invitation token is secure (random 64 chars)
- ✅ Password validation enforced (min 8 chars)

**Common Issues:**
- Email not sending: Check CodeIgniter email config (SMTP settings)
- Invitation link 404: Check route definition, check token in URL
- Role not enforced: Check RoleFilter applied to routes
- Self-modification not blocked: Check userId comparison in controller
- Activity log empty: Check ActivityLogModel::log() calls
- Last seen not updating: Check AuthFilter updates timestamp
- Tenant leak: Check BaseModel account_id scoping on all queries
- Cannot remove user: Check conversation reassignment logic
- Duplicate invitation: Check expires_at > NOW in query
- Role dropdown not updating: Check AJAX response, check page reload

---

**Phase 12 Complete!**

Team management system includes:
- User invitation workflow with email
- Role management (admin, agent, viewer)
- Activate/deactivate users
- Remove users with safety checks
- Activity logging for all team actions
- Permission enforcement with RoleFilter
- Last seen tracking
- Self-protection (cannot harm own account)

