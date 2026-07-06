<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$roleColors = [
    'owner'  => 'bg-purple-100 text-purple-800',
    'admin'  => 'bg-blue-100 text-blue-800',
    'agent'  => 'bg-green-100 text-green-800',
    'viewer' => 'bg-gray-100 text-gray-700',
];
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Team Management</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage team members, roles and invitations</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= base_url('team/activity-log') ?>"
           class="px-3 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
            Activity Log
        </a>
        <button onclick="showInviteModal()"
                class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
            + Invite Member
        </button>
    </div>
</div>

<!-- Flash messages -->
<?php if (session()->getFlashdata('success')): ?>
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<!-- Team Members -->
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-800">Team Members (<?= count($members) ?>)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Member</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Role</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Conversations</th>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">Last Seen</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($members as $m):
                    $isSelf = ($m['user_id'] === session('user_id'));
                    $isOwner = ($m['account_role'] === 'owner');
                    $rc = $roleColors[$m['account_role']] ?? 'bg-gray-100 text-gray-600';
                    $isActive = (bool)($m['is_active'] ?? 1);
                ?>
                <tr class="hover:bg-gray-50 <?= $isActive ? '' : 'opacity-60' ?>" id="member-row-<?= esc($m['id']) ?>">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-700 flex-shrink-0">
                                <?= strtoupper(substr($m['full_name'] ?? '?', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">
                                    <?= esc($m['full_name']) ?>
                                    <?php if ($isSelf): ?><span class="text-xs text-gray-400 ml-1">(you)</span><?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-400"><?= esc($m['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <?php if ($isSelf || $isOwner): ?>
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= $rc ?>">
                            <?= ucfirst($m['account_role']) ?>
                        </span>
                        <?php else: ?>
                        <select onchange="updateRole('<?= esc($m['id']) ?>', this.value)"
                                class="text-xs rounded-lg px-2 py-1 border border-gray-200 focus:outline-none focus:border-blue-400">
                            <option value="admin"  <?= $m['account_role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
                            <option value="agent"  <?= $m['account_role'] === 'agent'  ? 'selected' : '' ?>>Agent</option>
                            <option value="viewer" <?= $m['account_role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 text-gray-700">
                        <?= number_format($m['conversation_count'] ?? 0) ?>
                    </td>
                    <td class="px-5 py-4 text-xs text-gray-500">
                        <?= $m['last_seen_at'] ? date('M j, g:i A', strtotime($m['last_seen_at'])) : 'Never' ?>
                    </td>
                    <td class="px-5 py-4 text-right">
                        <?php if ($isSelf || $isOwner): ?>
                        <span class="text-xs text-gray-300">—</span>
                        <?php else: ?>
                        <button onclick="toggleActive('<?= esc($m['id']) ?>', <?= $isActive ? 1 : 0 ?>)"
                                class="text-xs text-blue-600 hover:text-blue-700 mr-3">
                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                        </button>
                        <button onclick="removeMember('<?= esc($m['id']) ?>', '<?= esc(addslashes($m['full_name'])) ?>')"
                                class="text-xs text-red-500 hover:text-red-600">
                            Remove
                        </button>
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
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Pending Invitations (<?= count($pendingInvitations) ?>)</h2>
    </div>
    <div class="divide-y divide-gray-100">
        <?php foreach ($pendingInvitations as $inv): ?>
        <div class="flex items-center justify-between px-5 py-4" id="invite-row-<?= esc($inv['id']) ?>">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-amber-700"><?= rx_icon('mail', 'w-4 h-4', '!text-amber-700') ?></div>
                <div>
                    <div class="text-sm font-medium text-gray-900"><?= esc($inv['email'] ?? '—') ?></div>
                    <div class="text-xs text-gray-400">
                        <?= ucfirst($inv['role']) ?> · Expires <?= date('M j', strtotime($inv['expires_at'])) ?>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="resendInvitation('<?= esc($inv['id']) ?>')"
                        class="px-3 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700">
                    Resend
                </button>
                <button onclick="cancelInvitation('<?= esc($inv['id']) ?>')"
                        class="px-3 py-1.5 text-xs border border-red-200 rounded-lg hover:bg-red-50 text-red-600">
                    Cancel
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Invite Modal -->
<div id="invite-modal"
     class="fixed inset-0 z-50 flex items-center justify-center px-4"
     style="display: none !important;"
     x-data="{ open: false }"
     x-show="open"
     @show-invite.window="open = true">
    <div class="absolute inset-0 bg-black/30" onclick="hideInviteModal()"></div>
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-10">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Invite Team Member</h3>
        <div id="invite-error" class="hidden mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

        <form id="invite-form" onsubmit="submitInvite(event)">
            <?= csrf_field() ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                <input type="email" name="email" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400"
                       placeholder="colleague@company.com">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-400">
                    <option value="agent">Agent — can view/reply to conversations</option>
                    <option value="admin">Admin — full access except ownership</option>
                    <option value="viewer">Viewer — read-only access</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="hideInviteModal()"
                        class="px-4 py-2 border border-gray-200 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
                    Cancel
                </button>
                <button type="submit" id="invite-btn"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 font-medium">
                    Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const TEAM_BASE = <?= json_encode(base_url('team'), JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

// Modal
function showInviteModal() {
    document.getElementById('invite-modal').style.removeProperty('display');
    document.getElementById('invite-error').classList.add('hidden');
    document.getElementById('invite-form').reset();
}
function hideInviteModal() {
    document.getElementById('invite-modal').style.setProperty('display', 'none', 'important');
}

async function submitInvite(e) {
    e.preventDefault();
    const btn  = document.getElementById('invite-btn');
    const errEl = document.getElementById('invite-error');
    btn.disabled = true;
    btn.textContent = 'Sending…';
    errEl.classList.add('hidden');

    const form = document.getElementById('invite-form');
    const fd   = new FormData(form);

    try {
        const res  = await fetch(TEAM_BASE + '/invite', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            hideInviteModal();
            location.reload();
        } else {
            errEl.textContent = data.error || 'Failed to send invitation.';
            errEl.classList.remove('hidden');
        }
    } catch {
        errEl.textContent = 'Network error. Try again.';
        errEl.classList.remove('hidden');
    }
    btn.disabled = false;
    btn.textContent = 'Send Invitation';
}

async function updateRole(profileId, role) {
    const res = await fetch(`${TEAM_BASE}/${profileId}/update-role`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `role=${role}`,
    });
    const data = await res.json();
    if (!data.success) { alert(data.error || 'Failed to update role'); location.reload(); }
}

async function toggleActive(profileId, currentlyActive) {
    if (!confirm(currentlyActive ? 'Deactivate this member? They will not be able to log in.' : 'Reactivate this member?')) return;
    const res = await fetch(`${TEAM_BASE}/${profileId}/toggle-active`, {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) { location.reload(); }
    else { alert(data.error || 'Failed'); }
}

async function removeMember(profileId, name) {
    if (!confirm(`Remove ${name} from the team? This cannot be undone.`)) return;
    const res = await fetch(`${TEAM_BASE}/${profileId}/remove`, {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('member-row-' + profileId)?.remove();
    } else {
        alert(data.error || 'Failed to remove member');
    }
}

async function resendInvitation(inviteId) {
    const res = await fetch(`${TEAM_BASE}/invitations/${inviteId}/resend`, {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.ok) { alert('Invitation resent! Expiry extended by 7 days.'); }
    else { alert('Failed to resend'); }
}

async function cancelInvitation(inviteId) {
    if (!confirm('Cancel this invitation? The link will stop working.')) return;
    const res = await fetch(`${TEAM_BASE}/invitations/${inviteId}/cancel`, {
        method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.ok) { document.getElementById('invite-row-' + inviteId)?.remove(); }
    else { alert('Failed to cancel'); }
}
</script>

<?= $this->endSection() ?>
