## PHASE 13: Settings Module (Week 10)

### Prompt 13.1 — Account Settings & WhatsApp Configuration

```
Build settings module for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/settings/page.tsx
- src/app/settings/whatsapp/page.tsx
- src/components/settings/whatsapp-config.tsx

IMPORTANT: Settings should be organized in tabs: Account, WhatsApp, Notifications, API Keys, Webhooks.

Create app/Controllers/SettingsController.php:

<?php
namespace App\Controllers;

use App\Models\AccountModel;
use App\Models\UserModel;
use App\Models\WhatsAppConfigModel;
use App\Models\ActivityLogModel;
use App\Libraries\WhatsApp\Encryption;

class SettingsController extends BaseController
{
    public function index()
    {
        // Only admins can access settings
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $accountModel = new AccountModel();
        $account = $accountModel->find(session('account_id'));

        return view('settings/index', [
            'account' => $account,
            'activeTab' => 'account'
        ]);
    }

    public function updateAccount()
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $rules = [
            'name' => 'required|min_length[2]',
            'timezone' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['errors' => $this->validator->getErrors()])->setStatusCode(400);
        }

        $accountModel = new AccountModel();
        $accountModel->update(session('account_id'), [
            'name' => $this->request->getPost('name'),
            'timezone' => $this->request->getPost('timezone')
        ]);

        // Update session
        session()->set('account_name', $this->request->getPost('name'));

        ActivityLogModel::log('settings.account_updated', 'account', session('account_id'));

        return $this->response->setJSON(['success' => true]);
    }

    public function whatsapp()
    {
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->first();

        // Decrypt for display (mask most of token)
        if ($waConfig && $waConfig['access_token']) {
            $encryption = new Encryption();
            $decrypted = $encryption->decrypt($waConfig['access_token']);
            $waConfig['access_token_masked'] = substr($decrypted, 0, 10) . '...' . substr($decrypted, -4);
        }

        $accountModel = new AccountModel();
        $account = $accountModel->find(session('account_id'));

        return view('settings/index', [
            'account' => $account,
            'waConfig' => $waConfig,
            'activeTab' => 'whatsapp'
        ]);
    }

    public function updateWhatsApp()
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $rules = [
            'phone_number_id' => 'required',
            'waba_id' => 'required',
            'access_token' => 'required',
            'webhook_verify_token' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['errors' => $this->validator->getErrors()])->setStatusCode(400);
        }

        $encryption = new Encryption();

        $data = [
            'account_id' => session('account_id'),
            'phone_number_id' => $this->request->getPost('phone_number_id'),
            'waba_id' => $this->request->getPost('waba_id'),
            'access_token' => $encryption->encrypt($this->request->getPost('access_token')),
            'webhook_verify_token' => $this->request->getPost('webhook_verify_token')
        ];

        $waConfigModel = new WhatsAppConfigModel();
        $existing = $waConfigModel->first();

        if ($existing) {
            $waConfigModel->update($existing['id'], $data);
        } else {
            $waConfigModel->insert($data);
        }

        ActivityLogModel::log('settings.whatsapp_updated');

        return $this->response->setJSON(['success' => true]);
    }

    public function testWhatsApp()
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $testPhone = $this->request->getPost('test_phone');

        if (!$testPhone) {
            return $this->response->setJSON(['error' => 'Phone number required'])->setStatusCode(400);
        }

        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->first();

        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not configured'])->setStatusCode(400);
        }

        try {
            $encryption = new Encryption();
            $accessToken = $encryption->decrypt($waConfig['access_token']);

            $metaApi = new \App\Libraries\WhatsApp\MetaApi();
            $result = $metaApi->sendTextMessage(
                $waConfig['phone_number_id'],
                $accessToken,
                $testPhone,
                'Test message from Rovix AI ✓'
            );

            if ($result) {
                ActivityLogModel::log('settings.whatsapp_test_sent', null, null, [
                    'test_phone' => $testPhone
                ]);

                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Test message sent successfully'
                ]);
            } else {
                return $this->response->setJSON(['error' => 'Failed to send test message'])->setStatusCode(500);
            }

        } catch (\Exception $e) {
            log_message('error', 'WhatsApp test failed: ' . $e->getMessage());
            return $this->response->setJSON(['error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    public function notifications()
    {
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $accountModel = new AccountModel();
        $account = $accountModel->find(session('account_id'));

        // Load notification preferences (stored in account metadata JSON)
        $notificationPrefs = json_decode($account['notification_preferences'] ?? '{}', true);

        return view('settings/index', [
            'account' => $account,
            'notificationPrefs' => $notificationPrefs,
            'activeTab' => 'notifications'
        ]);
    }

    public function updateNotifications()
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $prefs = [
            'email_new_message' => $this->request->getPost('email_new_message') ? true : false,
            'email_broadcast_complete' => $this->request->getPost('email_broadcast_complete') ? true : false,
            'email_daily_summary' => $this->request->getPost('email_daily_summary') ? true : false,
            'email_weekly_report' => $this->request->getPost('email_weekly_report') ? true : false
        ];

        $accountModel = new AccountModel();
        $accountModel->update(session('account_id'), [
            'notification_preferences' => json_encode($prefs)
        ]);

        ActivityLogModel::log('settings.notifications_updated');

        return $this->response->setJSON(['success' => true]);
    }

    public function apiKeys()
    {
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $accountModel = new AccountModel();
        $account = $accountModel->find(session('account_id'));

        // Generate API key if not exists
        if (!$account['api_key']) {
            $apiKey = bin2hex(random_bytes(32));
            $accountModel->update(session('account_id'), ['api_key' => $apiKey]);
            $account['api_key'] = $apiKey;
        }

        return view('settings/index', [
            'account' => $account,
            'activeTab' => 'api'
        ]);
    }

    public function regenerateApiKey()
    {
        if (session('role') !== 'admin') {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $apiKey = bin2hex(random_bytes(32));

        $accountModel = new AccountModel();
        $accountModel->update(session('account_id'), ['api_key' => $apiKey]);

        ActivityLogModel::log('settings.api_key_regenerated');

        return $this->response->setJSON([
            'success' => true,
            'api_key' => $apiKey
        ]);
    }

    public function webhooks()
    {
        if (session('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Access denied');
        }

        $accountModel = new AccountModel();
        $account = $accountModel->find(session('account_id'));

        // Load recent webhook logs
        $db = \Config\Database::connect();
        $webhookLogs = $db->table('webhook_logs')
            ->where('account_id', session('account_id'))
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        return view('settings/index', [
            'account' => $account,
            'webhookLogs' => $webhookLogs,
            'activeTab' => 'webhooks'
        ]);
    }
}

Add to app/Database/Migrations/2024XXXX_add_settings_columns.php:

<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSettingsColumns extends Migration
{
    public function up()
    {
        // Add columns to accounts table
        $this->forge->addColumn('accounts', [
            'timezone' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'UTC',
                'after' => 'name'
            ],
            'notification_preferences' => [
                'type' => 'JSON',
                'null' => true,
                'after' => 'timezone'
            ],
            'api_key' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'unique' => true,
                'after' => 'notification_preferences'
            ]
        ]);

        // Create webhook_logs table
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
            'event_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50
            ],
            'payload' => [
                'type' => 'JSON'
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['success', 'failed'],
                'default' => 'success'
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true
            ],
            'processing_time_ms' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('account_id');
        $this->forge->addKey('event_type');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('webhook_logs');
    }

    public function down()
    {
        $this->forge->dropColumn('accounts', ['timezone', 'notification_preferences', 'api_key']);
        $this->forge->dropTable('webhook_logs', true);
    }
}
```

Continue with Part 2 (Settings Views)?
