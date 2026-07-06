## PHASE 12: Team Management (Week 10)

### Prompt 12.1 — User Management & Roles

```
Build team management system for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/settings/team/page.tsx
- src/components/team/user-list.tsx
- src/components/team/invite-modal.tsx

IMPORTANT: Multi-user accounts need role-based access control. Roles: admin, agent, viewer.

Role Permissions:
- **Admin**: Full access (settings, billing, user management, all features)
- **Agent**: Can view/reply to conversations, manage contacts, create deals, no settings access
- **Viewer**: Read-only access, can view conversations/contacts/reports, cannot send messages

Create app/Database/Migrations/2024XXXX_add_user_roles.php:

<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserRoles extends Migration
{
    public function up()
    {
        // Add role column to users table
        $this->forge->addColumn('users', [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['admin', 'agent', 'viewer'],
                'default' => 'agent',
                'after' => 'account_id'
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'after' => 'role'
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'is_active'
            ]
        ]);

        // Create team_invitations table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true
            ],
            'account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255
            ],
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['admin', 'agent', 'viewer'],
                'default' => 'agent'
            ],
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'unique' => true
            ],
            'invited_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'expires_at' => [
                'type' => 'DATETIME'
            ],
            'accepted_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('account_id');
        $this->forge->addKey('token');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('invited_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('team_invitations');

        // Create activity_logs table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true
            ],
            'account_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 100
            ],
            'entity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true
            ],
            'entity_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true
            ],
            'metadata' => [
                'type' => 'JSON',
                'null' => true
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('account_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('activity_logs');
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['role', 'is_active', 'last_seen_at']);
        $this->forge->dropTable('team_invitations', true);
        $this->forge->dropTable('activity_logs', true);
    }
}

Create app/Models/TeamInvitationModel.php:

<?php
namespace App\Models;

class TeamInvitationModel extends BaseModel
{
    protected $table = 'team_invitations';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'account_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = false;
}

Create app/Models/ActivityLogModel.php:

<?php
namespace App\Models;

class ActivityLogModel extends BaseModel
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'account_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = false;

    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $metadata = null): void
    {
        $model = new self();
        $model->insert([
            'account_id' => session('account_id'),
            'user_id' => session('user_id'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'ip_address' => service('request')->getIPAddress()
        ]);
    }
}

Create app/Controllers/TeamController.php:

<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Models\TeamInvitationModel;
use App\Models\ActivityLogModel;

class TeamController extends BaseController
{
    public function index()
    {
        // Only admins can access team management
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $userModel = new UserModel();
        $users = $userModel
            ->select('users.*, COUNT(conversations.id) as conversation_count')
            ->join('conversations', 'conversations.assigned_agent_id = users.id', 'left')
            ->groupBy('users.id')
            ->findAll();

        $invitationModel = new TeamInvitationModel();
        $pendingInvitations = $invitationModel
            ->where('accepted_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->findAll();

        return view('team/index', [
            'users' => $users,
            'pendingInvitations' => $pendingInvitations
        ]);
    }

    public function invite()
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $rules = [
            'email' => 'required|valid_email',
            'role' => 'required|in_list[admin,agent,viewer]'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['errors' => $this->validator->getErrors()])->setStatusCode(400);
        }

        $email = $this->request->getPost('email');
        $role = $this->request->getPost('role');

        // Check if user already exists in account
        $userModel = new UserModel();
        $existing = $userModel->where('email', $email)->first();

        if ($existing) {
            return $this->response->setJSON(['error' => 'User already exists in this account'])->setStatusCode(400);
        }

        // Check if invitation already sent
        $invitationModel = new TeamInvitationModel();
        $existingInvite = $invitationModel
            ->where('email', $email)
            ->where('accepted_at IS NULL')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        if ($existingInvite) {
            return $this->response->setJSON(['error' => 'Invitation already sent'])->setStatusCode(400);
        }

        // Generate token
        $token = bin2hex(random_bytes(32));

        // Create invitation
        $invitationModel->insert([
            'account_id' => session('account_id'),
            'email' => $email,
            'role' => $role,
            'token' => $token,
            'invited_by' => session('user_id'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);

        // Send invitation email
        $this->sendInvitationEmail($email, $token, $role);

        // Log activity
        ActivityLogModel::log('team.invite_sent', 'invitation', $invitationModel->getInsertID(), [
            'email' => $email,
            'role' => $role
        ]);

        return $this->response->setJSON(['success' => true]);
    }

    private function sendInvitationEmail(string $email, string $token, string $role): void
    {
        $inviteLink = base_url("team/accept/{$token}");
        $accountName = session('account_name');

        $emailService = \Config\Services::email();
        $emailService->setTo($email);
        $emailService->setSubject("You've been invited to join {$accountName}");
        $emailService->setMessage("
            <h2>Team Invitation</h2>
            <p>You've been invited to join <strong>{$accountName}</strong> as a <strong>{$role}</strong>.</p>
            <p><a href=\"{$inviteLink}\">Accept Invitation</a></p>
            <p>This invitation expires in 7 days.</p>
        ");

        $emailService->send();
    }
}
```

This is Part 1. Continue with Part 2?
