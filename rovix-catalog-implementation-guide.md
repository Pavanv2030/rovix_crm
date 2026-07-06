# Rovix CRM — WhatsApp Catalog Implementation Guide
**Stack:** CodeIgniter 4 · PHP · MySQL · Meta Graph API v21.0  
**Reference:** WhatChimp Catalog docs + Meta Commerce API

---

## 1. Project Architecture (What We Have)

```
rovix-crm/
├── app/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── SendController.php        ← WhatsApp send API
│   │   │   ├── WebhookController.php     ← Inbound webhook processor
│   │   │   └── BroadcastsController.php
│   │   ├── TemplatesController.php       ← Template CRUD
│   │   ├── SettingsController.php        ← Account/WA settings
│   │   └── InboxController.php           ← Conversation inbox
│   ├── Libraries/
│   │   ├── WhatsApp/
│   │   │   ├── MetaApi.php               ← Meta Graph API wrapper
│   │   │   └── Encryption.php
│   │   ├── FlowEngine.php                ← Chatbot flow runner
│   │   └── FlowNodeSchemas.php           ← Flow node type registry
│   ├── Models/
│   │   ├── WhatsAppConfigModel.php       ← WA credentials per account
│   │   ├── MessageModel.php
│   │   ├── MessageTemplateModel.php
│   │   └── ConversationModel.php
│   ├── Config/
│   │   └── Routes.php                    ← All app routes
│   └── Database/Migrations/              ← 31 migrations (up to 000031)
```

**Tech facts:**
- Meta API base: `https://graph.facebook.com/v21.0/`
- Auth: Bearer access token (encrypted in DB)
- Multi-tenant: every row scoped by `account_id`
- Job queue: async processing via `ProcessQueue.php`
- Flow engine: keyword-triggered chatbot nodes
- BaseModel: auto-generates UUID on insert, auto-injects `account_id` from session (bypass needed in webhook context)

---

## 2. Bugs Found

### BUG 1 — ReportsController `cv.phone_number` (CRITICAL, already fixed)
**File:** `app/Controllers/ReportsController.php`  
**Error log:** `2026-07-01 11:40:40 — Unknown column 'cv.phone_number' in 'field list'`

Original query referenced `cv.phone_number` (conversations alias) — column doesn't exist there. `phone` belongs to `contacts`.

**Status:** Already fixed in current codebase. But confirm:
```php
// WRONG (old):
->select('..., co.name as contact_name, cv.phone_number')

// CORRECT (current):
->select('..., co.name as contact_name, co.phone as phone_number')
->join('conversations cv', 'cv.id = m.conversation_id')
->join('contacts co', 'co.id = cv.contact_id', 'left')
```

---

### BUG 2 — InboxController template status case mismatch (MEDIUM)
**File:** `app/Controllers/InboxController.php`, line 110

```php
// WRONG — DB stores lowercase 'approved', not 'APPROVED'
$templates = (new MessageTemplateModel())->where('status', 'APPROVED')->findAll();
```

MySQL ignores case with default collation but breaks on strict configs.

**Fix:**
```php
$templates = (new MessageTemplateModel())->where('status', 'approved')->findAll();
```

---

### BUG 3 — `guessMediaType()` always returns 'document' (MEDIUM)
**File:** `app/Controllers/Api/WebhookController.php`

```php
private function guessMediaType(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    // downloadMedia() saves ALL files as 'media_XXXXX.bin'
    // $ext always = 'bin' → always returns 'document'
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) return 'image';
    if (in_array($ext, ['mp4','mov','avi']))                return 'video';
    if (in_array($ext, ['mp3','ogg','wav','aac']))          return 'audio';
    return 'document'; // ← always hits this
}
```

**Fix — pass mime hint from webhook payload:**
```php
// In processInboundMessage() switch:
case 'image':
    [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta(
        $message['image']['id'], $waConfig, $accountId, 'image/jpeg'
    );
    break;
case 'video':
    [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta(
        $message['video']['id'], $waConfig, $accountId, 'video/mp4'
    );
    break;
case 'audio':
    [$mediaUrl, $mediaMimeType, $mediaFilename] = $this->downloadMediaFromMeta(
        $message['audio']['id'], $waConfig, $accountId, 'audio/ogg'
    );
    break;

// Update downloadMediaFromMeta signature:
private function downloadMediaFromMeta(
    string $mediaId, array $waConfig, string $accountId,
    string $hintMimeType = 'application/octet-stream'
): array {
    // ... existing code ...
    $mediaType = explode('/', $hintMimeType)[0]; // 'image', 'video', 'audio'
    (new \App\Models\MediaFileModel())->insert([
        'mime_type'  => $hintMimeType,
        'media_type' => $mediaType,
        // ...
    ]);
    return [$localPath, $hintMimeType, basename($localPath)];
}
```

---

### BUG 4 — No inbound `order` webhook handler (CRITICAL for catalog)
**File:** `app/Controllers/Api/WebhookController.php`

`order` type messages from catalog purchases fall through to `"Unsupported message type: order"`. No order recorded, no notification. Fixed in Step 7 below.

---

### BUG 5 — WhatsApp Config scope missing in SettingsController (MINOR)
**File:** `app/Controllers/SettingsController.php`, line 57

```php
// WRONG — fetches first config in DB, not current account's
$waConfig = (new WhatsAppConfigModel())->first();

// CORRECT
$waConfig = (new WhatsAppConfigModel())->where('account_id', session('account_id'))->first();
```

---

## 3. WhatChimp Catalog Feature Map

| Feature | WhatChimp | Rovix | Gap |
|---------|-----------|-------|-----|
| Fetch catalogs from Meta | ✅ | ❌ | Step 2 + 5 |
| Connect catalog to WA number | ✅ | ❌ | Step 2 + 5 |
| View catalog products | ✅ | ❌ | Step 5 |
| Send full catalog message | ✅ | ❌ | Step 2 + 6 |
| Send single product card | ✅ | ❌ | Step 2 + 6 |
| Send multi-product list | ✅ | ❌ | Step 2 + 6 |
| Receive order webhook | ✅ | ❌ BUG 4 | Step 7 |
| View orders + change status | ✅ | ❌ | Step 5 |
| Catalog in chatbot flow | ✅ | ❌ | Step 8 + 9 |
| Filter orders (catalog/status/search) | ✅ | ❌ | Step 5 |

---

## 4. Implementation Steps

### Step 1 — Migration 000032: catalog_orders table + whatsapp_config columns

**File: `app/Database/Migrations/2024-01-01-000032_AddCatalog.php`**

```php
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddCatalog extends Migration
{
    public function up()
    {
        // Add catalog columns to whatsapp_config
        $this->forge->addColumn('whatsapp_config', [
            'catalog_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'display_phone_number',
            ],
            'catalog_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'catalog_id',
            ],
            'catalog_synced_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'catalog_name',
            ],
        ]);

        // Catalog orders table
        $this->forge->addField([
            'id'              => ['type' => 'CHAR', 'constraint' => 36],
            'account_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'contact_id'      => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'conversation_id' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'catalog_id'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'order_items'     => ['type' => 'JSON', 'null' => true],
            'total_price'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'currency'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'customer_note'   => ['type' => 'TEXT', 'null' => true],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
                'default'    => 'new',
            ],
            'wa_order_id'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'reminder_sent_at'=> ['type' => 'DATETIME', 'null' => true],
            'payment_method'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['account_id', 'status']);
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('catalog_orders');
    }

    public function down()
    {
        $this->forge->dropTable('catalog_orders', true);
        $this->forge->dropColumn('whatsapp_config', ['catalog_id', 'catalog_name', 'catalog_synced_at']);
    }
}
```

---

### Step 2 — Migration 000033: Fix messages.content_type ENUM → VARCHAR

**CRITICAL** — existing `messages.content_type` is ENUM. New catalog types (`catalog`, `product`, `product_list`) not in ENUM → insert crashes.

**File: `app/Database/Migrations/2024-01-01-000033_FixMessageContentType.php`**

```php
<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class FixMessageContentType extends Migration
{
    public function up()
    {
        // Change ENUM to VARCHAR(50) to support catalog/product/product_list + future types
        $this->db->query(
            "ALTER TABLE messages MODIFY COLUMN content_type VARCHAR(50) NOT NULL DEFAULT 'text'"
        );
    }

    public function down()
    {
        // Restore original ENUM (only safe if no new type values exist)
        $this->db->query(
            "ALTER TABLE messages MODIFY COLUMN content_type ENUM(
                'text','image','video','document','audio','sticker',
                'location','interactive','template','reaction'
            ) NOT NULL DEFAULT 'text'"
        );
    }
}
```

---

### Step 3 — WhatsAppConfigModel.php (add catalog fields)

**File: `app/Models/WhatsAppConfigModel.php`**

```php
protected $allowedFields = [
    'id', 'account_id', 'phone_number_id', 'waba_id', 'access_token',
    'business_name', 'status', 'subscription_status', 'webhook_verify_token',
    'display_phone_number', 'verified_name', 'quality_rating', 'name_status',
    'account_mode', 'number_info_fetched_at',
    'catalog_id',           // ← NEW
    'catalog_name',         // ← NEW
    'catalog_synced_at',    // ← NEW
];
```

---

### Step 4 — CatalogOrderModel.php (new model)

**File: `app/Models/CatalogOrderModel.php`**

```php
<?php
namespace App\Models;

class CatalogOrderModel extends BaseModel
{
    protected $table         = 'catalog_orders';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'id', 'account_id', 'contact_id', 'conversation_id',
        'catalog_id', 'order_items', 'total_price', 'currency',
        'customer_note', 'status', 'wa_order_id',
        'reminder_sent_at', 'payment_method',
        'created_at', 'updated_at',
    ];
}
```

> BaseModel auto-generates UUID on insert + auto-injects `account_id` from session.  
> In webhook context (no session): bypass is active, so pass `account_id` manually in insert data.

---

### Step 5 — MetaApi.php (add 5 catalog methods)

Add to `app/Libraries/WhatsApp/MetaApi.php`:

```php
/**
 * Fetch all catalogs linked to this WABA from Meta.
 * Returns array of { id, name, category }
 */
public function getCatalogs(string $wabaId, string $accessToken): array
{
    return $this->callApi(
        'GET',
        "{$wabaId}/catalogs?fields=id,name,category",
        null,
        $accessToken
    );
}

/**
 * Fetch products from a catalog (up to 100 per call).
 */
public function getCatalogProducts(string $catalogId, string $accessToken): array
{
    return $this->callApi(
        'GET',
        "{$catalogId}/products?fields=id,name,description,price,sale_price,image_url,availability,retailer_id&limit=100",
        null,
        $accessToken
    );
}

/**
 * Send full catalog browsing button.
 * Customer taps "View catalog" → browses all products in WhatsApp.
 */
public function sendCatalogMessage(
    string $phoneNumberId,
    string $accessToken,
    string $to,
    string $bodyText,
    ?string $footerText = null
): array {
    $interactive = [
        'type'   => 'catalog_message',
        'body'   => ['text' => $bodyText],
        'action' => ['name' => 'catalog_message'],
    ];

    if ($footerText) {
        $interactive['footer'] = ['text' => $footerText];
    }

    return $this->callApi('POST', "{$phoneNumberId}/messages", [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'interactive',
        'interactive'       => $interactive,
    ], $accessToken);
}

/**
 * Send single product card.
 * $productRetailerId = retailer_id field from catalog product.
 */
public function sendSingleProduct(
    string $phoneNumberId,
    string $accessToken,
    string $to,
    string $catalogId,
    string $productRetailerId,
    ?string $bodyText = null
): array {
    return $this->callApi('POST', "{$phoneNumberId}/messages", [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'interactive',
        'interactive'       => [
            'type'   => 'product',
            'body'   => ['text' => $bodyText ?? ''],
            'action' => [
                'catalog_id'          => $catalogId,
                'product_retailer_id' => $productRetailerId,
            ],
        ],
    ], $accessToken);
}

/**
 * Send multi-product list (up to 30 products across sections).
 *
 * $sections format:
 * [
 *   [
 *     'title' => 'Featured',
 *     'product_items' => [
 *       ['product_retailer_id' => 'PROD_001'],
 *       ['product_retailer_id' => 'PROD_002'],
 *     ]
 *   ]
 * ]
 */
public function sendMultiProduct(
    string $phoneNumberId,
    string $accessToken,
    string $to,
    string $catalogId,
    string $headerText,
    string $bodyText,
    array  $sections,
    ?string $footerText = null
): array {
    $interactive = [
        'type'   => 'product_list',
        'header' => ['type' => 'text', 'text' => $headerText],
        'body'   => ['text' => $bodyText],
        'action' => [
            'catalog_id' => $catalogId,
            'sections'   => $sections,
        ],
    ];

    if ($footerText) {
        $interactive['footer'] = ['text' => $footerText];
    }

    return $this->callApi('POST', "{$phoneNumberId}/messages", [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'interactive',
        'interactive'       => $interactive,
    ], $accessToken);
}
```

---

### Step 6 — CatalogController.php (web UI controller)

**File: `app/Controllers/CatalogController.php`**

```php
<?php
namespace App\Controllers;

use App\Models\WhatsAppConfigModel;
use App\Models\CatalogOrderModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class CatalogController extends BaseController
{
    // GET /catalog
    public function index()
    {
        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        $products = [];
        if ($waConfig && !empty($waConfig['catalog_id'])) {
            try {
                $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
                $result      = (new MetaApi())->getCatalogProducts(
                    $waConfig['catalog_id'], $accessToken
                );
                $products = $result['data'] ?? [];
            } catch (\Exception $e) {
                // Catalog not synced yet — show empty state
            }
        }

        return view('catalog/index', [
            'pageTitle' => 'Catalog',
            'waConfig'  => $waConfig,
            'products'  => $products,
        ]);
    }

    // POST /catalog/connect
    public function connect()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $catalogId = trim($this->request->getPost('catalog_id') ?? '');
        if (empty($catalogId)) {
            return $this->response->setJSON(['error' => 'catalog_id required'])->setStatusCode(400);
        }

        $waConfigModel = new WhatsAppConfigModel();
        $waConfig      = $waConfigModel->where('account_id', session('account_id'))->first();

        if (!$waConfig) {
            return $this->response->setJSON(['error' => 'WhatsApp not connected'])->setStatusCode(400);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $result      = (new MetaApi())->getCatalogProducts($catalogId, $accessToken);
            $count       = count($result['data'] ?? []);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'error' => 'Cannot reach catalog: ' . $e->getMessage()
            ])->setStatusCode(400);
        }

        $waConfigModel->update($waConfig['id'], [
            'catalog_id'        => $catalogId,
            'catalog_synced_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'product_count' => $count]);
    }

    // POST /catalog/sync
    public function sync()
    {
        if (!can_edit_settings()) {
            return $this->response->setJSON(['error' => 'Access denied'])->setStatusCode(403);
        }

        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$waConfig || empty($waConfig['catalog_id'])) {
            return $this->response->setJSON(['error' => 'No catalog connected'])->setStatusCode(400);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $result      = (new MetaApi())->getCatalogProducts(
                $waConfig['catalog_id'], $accessToken
            );
            $count = count($result['data'] ?? []);

            (new WhatsAppConfigModel())->update($waConfig['id'], [
                'catalog_synced_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true, 'product_count' => $count]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['error' => $e->getMessage()])->setStatusCode(500);
        }
    }

    // GET /catalog/orders
    public function orders()
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('catalog_orders co')
            ->select('co.*, c.name as contact_name, c.phone')
            ->join('contacts c', 'c.id = co.contact_id', 'left')
            ->where('co.account_id', session('account_id'));

        // Filters (mirrors WhatChimp order list UI)
        $catalogFilter = $this->request->getGet('catalog_id');
        $statusFilter  = $this->request->getGet('status');
        $search        = $this->request->getGet('q');

        if ($catalogFilter) $builder->where('co.catalog_id', $catalogFilter);
        if ($statusFilter)  $builder->where('co.status', $statusFilter);
        if ($search) {
            $builder->groupStart()
                ->like('c.name', $search)
                ->orLike('c.phone', $search)
            ->groupEnd();
        }

        $orders = $builder->orderBy('co.created_at', 'DESC')->get()->getResultArray();

        // Decode order_items JSON for view
        foreach ($orders as &$order) {
            $order['order_items'] = json_decode($order['order_items'] ?? '[]', true);
        }

        // Unique catalogs for filter dropdown
        $catalogs = $db->table('catalog_orders')
            ->select('catalog_id')
            ->where('account_id', session('account_id'))
            ->where('catalog_id IS NOT NULL')
            ->distinct()
            ->get()->getResultArray();

        return view('catalog/orders', [
            'pageTitle'     => 'Catalog Orders',
            'orders'        => $orders,
            'catalogs'      => $catalogs,
            'statusOptions' => ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'],
        ]);
    }

    // POST /catalog/orders/:id/status
    public function updateOrderStatus(string $orderId)
    {
        $status  = $this->request->getPost('status');
        $allowed = ['new', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

        if (!in_array($status, $allowed)) {
            return $this->response->setJSON(['error' => 'Invalid status'])->setStatusCode(400);
        }

        $orderModel = new CatalogOrderModel();
        $order      = $orderModel->where('account_id', session('account_id'))->find($orderId);

        if (!$order) {
            return $this->response->setJSON(['error' => 'Order not found'])->setStatusCode(404);
        }

        $orderModel->update($orderId, [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true]);
    }
}
```

---

### Step 7 — Api/CatalogController.php (API endpoints)

**File: `app/Controllers/Api/CatalogController.php`**

```php
<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\WhatsAppConfigModel;
use App\Models\ConversationModel;
use App\Models\ContactModel;
use App\Models\MessageModel;
use App\Libraries\WhatsApp\MetaApi;
use App\Libraries\WhatsApp\Encryption;

class CatalogController extends BaseController
{
    /**
     * GET /api/catalog/fetch-catalogs
     * Auto-fetch all catalogs from Meta using waba_id.
     * No manual catalog ID paste needed.
     */
    public function fetchCatalogs()
    {
        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$waConfig || empty($waConfig['waba_id'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'WABA ID not configured in WhatsApp settings']);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $result      = (new MetaApi())->getCatalogs($waConfig['waba_id'], $accessToken);

            return $this->response->setJSON([
                'catalogs' => $result['data'] ?? [],
                // Each item: { id, name, category }
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/catalog/products
     * Used by Inbox product picker.
     */
    public function products()
    {
        $waConfig = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();

        if (!$waConfig || empty($waConfig['catalog_id'])) {
            return $this->response->setJSON(['products' => [], 'catalog_connected' => false]);
        }

        try {
            $accessToken = (new Encryption())->decrypt($waConfig['access_token']);
            $result      = (new MetaApi())->getCatalogProducts(
                $waConfig['catalog_id'], $accessToken
            );
            return $this->response->setJSON([
                'products'          => $result['data'] ?? [],
                'catalog_id'        => $waConfig['catalog_id'],
                'catalog_connected' => true,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/catalog/send-catalog
     * Send full catalog browsing button to a conversation.
     */
    public function sendCatalog()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $bodyText       = $this->request->getPost('body_text') ?? 'Browse our products 🛍️';
        $footerText     = $this->request->getPost('footer_text');

        if (empty($conversationId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'conversation_id required']);
        }

        [$conversation, $waConfig, $contact] = $this->resolveConversationContext($conversationId);
        if (!$conversation) return $this->response->setStatusCode(404)->setJSON(['error' => 'Conversation not found']);
        if (!$waConfig)     return $this->response->setStatusCode(400)->setJSON(['error' => 'WhatsApp not connected']);
        if (!$contact)      return $this->response->setStatusCode(400)->setJSON(['error' => 'Contact not found']);

        if (empty($waConfig['catalog_id'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'No catalog connected. Go to Settings → Catalog.']);
        }

        $accessToken  = (new Encryption())->decrypt($waConfig['access_token']);
        $messageModel = new MessageModel();

        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'catalog',
            'content_text'    => $bodyText,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = (new MetaApi())->sendCatalogMessage(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $bodyText,
                $footerText
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => '🛍️ Catalog',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/catalog/send-product
     * Send single product card.
     */
    public function sendProduct()
    {
        $conversationId    = $this->request->getPost('conversation_id');
        $productRetailerId = $this->request->getPost('product_retailer_id');
        $bodyText          = $this->request->getPost('body_text') ?? '';

        if (!$conversationId || !$productRetailerId) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'conversation_id and product_retailer_id required']);
        }

        [$conversation, $waConfig, $contact] = $this->resolveConversationContext($conversationId);
        if (!$conversation || !$waConfig || !$contact) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Resource not found']);
        }

        if (empty($waConfig['catalog_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No catalog connected']);
        }

        $accessToken  = (new Encryption())->decrypt($waConfig['access_token']);
        $messageModel = new MessageModel();

        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'product',
            'content_text'    => 'Product: ' . $productRetailerId,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = (new MetaApi())->sendSingleProduct(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $waConfig['catalog_id'],
                $productRetailerId,
                $bodyText
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => '🏷️ Product',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/catalog/send-multi-product
     * Body: { conversation_id, header_text, body_text, footer_text, sections (JSON string) }
     */
    public function sendMultiProduct()
    {
        $conversationId = $this->request->getPost('conversation_id');
        $headerText     = $this->request->getPost('header_text') ?? 'Our Products';
        $bodyText       = $this->request->getPost('body_text') ?? 'Browse and order below';
        $footerText     = $this->request->getPost('footer_text');
        $sections       = json_decode($this->request->getPost('sections') ?? '[]', true);

        if (!$conversationId || empty($sections)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'conversation_id and sections required']);
        }

        [$conversation, $waConfig, $contact] = $this->resolveConversationContext($conversationId);
        if (!$conversation || !$waConfig || !$contact || empty($waConfig['catalog_id'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Missing required data']);
        }

        $accessToken  = (new Encryption())->decrypt($waConfig['access_token']);
        $messageModel = new MessageModel();

        $messageId = $messageModel->insert([
            'conversation_id' => $conversationId,
            'account_id'      => session('account_id'),
            'sender_type'     => 'agent',
            'content_type'    => 'product_list',
            'content_text'    => $headerText,
            'status'          => 'sending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        try {
            $response = (new MetaApi())->sendMultiProduct(
                $waConfig['phone_number_id'],
                $accessToken,
                $contact['phone_normalized'],
                $waConfig['catalog_id'],
                $headerText,
                $bodyText,
                $sections,
                $footerText
            );

            $messageModel->update($messageId, [
                'whatsapp_message_id' => $response['messages'][0]['id'] ?? null,
                'status'              => 'sent',
            ]);

            (new ConversationModel())->update($conversationId, [
                'last_message_text' => '🛒 Product List',
                'last_message_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (\Exception $e) {
            $messageModel->update($messageId, ['status' => 'failed', 'error_message' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }
    }

    // ── Shared helper ─────────────────────────────────────────────────────────

    private function resolveConversationContext(string $conversationId): array
    {
        $conversation = (new ConversationModel())->find($conversationId);
        $waConfig     = (new WhatsAppConfigModel())
            ->where('account_id', session('account_id'))->first();
        $contact      = $conversation
            ? (new ContactModel())->find($conversation['contact_id'])
            : null;

        return [$conversation, $waConfig, $contact];
    }
}
```

---

### Step 8 — WebhookController.php (add order handler)

In `processInboundMessage()` switch, add before `default`:

```php
case 'order':
    $contentText = $this->processOrderMessage(
        $accountId,
        $contact['id'],
        $conversation['id'],
        $message['order']
    );
    break;
```

Add private method:

```php
private function processOrderMessage(
    string $accountId,
    string $contactId,
    string $conversationId,
    array $order
): string {
    $items     = $order['product_items'] ?? [];
    $catalogId = $order['catalog_id'] ?? null;
    $text      = $order['text'] ?? null;

    $total    = 0.0;
    $currency = 'USD';
    $summaries = [];

    foreach ($items as $item) {
        $price     = ($item['item_price'] ?? 0) / 1000; // Meta sends in thousandths
        $qty       = $item['quantity'] ?? 1;
        $currency  = $item['currency'] ?? 'USD';
        $retailerId = $item['product_retailer_id'];
        $total     += $price * $qty;
        $summaries[] = "{$qty}x {$retailerId} @ {$price} {$currency}";
    }

    // BaseModel auto-generates UUID but webhook has no session.
    // bypass is already active (set in handle()). Pass account_id manually.
    (new \App\Models\CatalogOrderModel())->insert([
        'account_id'      => $accountId,
        'contact_id'      => $contactId,
        'conversation_id' => $conversationId,
        'catalog_id'      => $catalogId,
        'order_items'     => json_encode($items),
        'total_price'     => round($total, 2),
        'currency'        => $currency,
        'customer_note'   => $text,
        'status'          => 'new',
        'wa_order_id'     => 'wa_' . uniqid(),
        'created_at'      => date('Y-m-d H:i:s'),
        'updated_at'      => date('Y-m-d H:i:s'),
    ]);

    return '🛒 Order: ' . implode(', ', $summaries) . ($text ? " | Note: {$text}" : '');
}
```

Also update `extractInteractiveResponse()`:
```php
private function extractInteractiveResponse(array $interactive): string
{
    if (isset($interactive['button_reply'])) return $interactive['button_reply']['title'];
    if (isset($interactive['list_reply']))   return $interactive['list_reply']['title'];
    if (isset($interactive['nfm_reply']))    return 'Flow response: ' . ($interactive['nfm_reply']['name'] ?? '');
    return 'Interactive response';
}
```

---

### Step 9 — FlowNodeSchemas.php (add catalog nodes)

In `getAllTypes()`:
```php
return [
    'start', 'send_message', 'send_buttons', 'send_list',
    'send_media', 'send_media_buttons', 'url_button',
    'request_location', 'collect_input', 'collect_form',
    'condition', 'set_tag', 'add_to_group', 'handoff', 'end',
    'send_catalog',   // ← NEW
    'send_product',   // ← NEW
];
```

In `getSchema()` match:
```php
'send_catalog' => self::sendCatalog(),
'send_product' => self::sendProduct(),
```

New private methods:
```php
private static function sendCatalog(): array
{
    return [
        'name'              => 'Send Catalog',
        'description'       => 'Send full product catalog to customer',
        'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path d="M3 1a1 1 0 0 0 0 2h1.22l.305 1.222a.997.997 0 0 0 .01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 0 0 0-2H6.414l1-1H14a1 1 0 0 0 .894-.553l3-6A1 1 0 0 0 17 3H6.28l-.31-1.243A1 1 0 0 0 5 1H3Z"/></svg>',
        'color'             => '#F59E0B',
        'has_single_output' => true,
        'terminates_flow'   => false,
        'config_fields'     => [
            self::field('body_text', 'Message Body', 'textarea', true, [
                'placeholder' => 'Check out our products! Browse and order below.',
                'max_length'  => 1024,
            ]),
            self::field('footer_text', 'Footer Text', 'text', false, [
                'placeholder' => 'Tap "View catalog" to browse',
                'max_length'  => 60,
            ]),
            self::field('next_node', 'Next Node', 'node_select', true),
        ],
    ];
}

private static function sendProduct(): array
{
    return [
        'name'              => 'Send Product',
        'description'       => 'Send a single product card',
        'icon'              => '<svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10 2a4 4 0 0 0-4 4v1H5a1 1 0 0 0-.994.89l-1 9A1 1 0 0 0 4 18h12a1 1 0 0 0 .994-1.11l-1-9A1 1 0 0 0 15 7h-1V6a4 4 0 0 0-4-4Zm2 5V6a2 2 0 1 0-4 0v1h4Z" clip-rule="evenodd"/></svg>',
        'color'             => '#EF4444',
        'has_single_output' => true,
        'terminates_flow'   => false,
        'config_fields'     => [
            self::field('product_retailer_id', 'Product Retailer ID', 'text', true, [
                'placeholder' => 'Enter product retailer_id from your catalog',
            ]),
            self::field('body_text', 'Message Body', 'textarea', false, [
                'placeholder' => 'Here is the product you asked about:',
                'max_length'  => 1024,
            ]),
            self::field('next_node', 'Next Node', 'node_select', true),
        ],
    ];
}
```

---

### Step 10 — FlowEngine.php (execute catalog nodes)

Find node execution switch (look for `case 'send_message':`) and add:

```php
case 'send_catalog':
    $this->executeSendCatalog($node, $waConfig, $accessToken, $contact);
    break;

case 'send_product':
    $this->executeSendProduct($node, $waConfig, $accessToken, $contact);
    break;
```

Add private methods:

```php
private function executeSendCatalog(
    array $node, array $waConfig, string $accessToken, array $contact
): void {
    if (empty($waConfig['catalog_id'])) {
        log_message('warning', 'Flow: send_catalog — no catalog connected for account ' . $waConfig['account_id']);
        return;
    }

    $config     = $node['config'] ?? [];
    $bodyText   = $config['body_text'] ?? 'Browse our products 🛍️';
    $footerText = $config['footer_text'] ?? null;

    (new MetaApi())->sendCatalogMessage(
        $waConfig['phone_number_id'],
        $accessToken,
        $contact['phone_normalized'],
        $bodyText,
        $footerText
    );
}

private function executeSendProduct(
    array $node, array $waConfig, string $accessToken, array $contact
): void {
    if (empty($waConfig['catalog_id'])) {
        log_message('warning', 'Flow: send_product — no catalog connected');
        return;
    }

    $config            = $node['config'] ?? [];
    $productRetailerId = $config['product_retailer_id'] ?? null;
    $bodyText          = $config['body_text'] ?? '';

    if (!$productRetailerId) {
        log_message('warning', 'Flow: send_product — missing product_retailer_id in node config');
        return;
    }

    (new MetaApi())->sendSingleProduct(
        $waConfig['phone_number_id'],
        $accessToken,
        $contact['phone_normalized'],
        $waConfig['catalog_id'],
        $productRetailerId,
        $bodyText
    );
}
```

---

### Step 11 — Routes.php (all catalog routes)

Add to `app/Config/Routes.php`:

```php
// ── Catalog (web UI) ──────────────────────────────────────────────────────
$routes->get('catalog',                            'CatalogController::index');
$routes->post('catalog/connect',                   'CatalogController::connect');
$routes->post('catalog/sync',                      'CatalogController::sync');
$routes->get('catalog/orders',                     'CatalogController::orders');
$routes->post('catalog/orders/(:segment)/status',  'CatalogController::updateOrderStatus/$1');

// ── Catalog (API — used by Inbox + Settings UI) ───────────────────────────
$routes->get('api/catalog/fetch-catalogs',         'Api\CatalogController::fetchCatalogs');
$routes->get('api/catalog/products',               'Api\CatalogController::products');
$routes->post('api/catalog/send-catalog',          'Api\CatalogController::sendCatalog');
$routes->post('api/catalog/send-product',          'Api\CatalogController::sendProduct');
$routes->post('api/catalog/send-multi-product',    'Api\CatalogController::sendMultiProduct');
```

---

### Step 12 — Views

#### `app/Views/catalog/index.php`
Catalog management page:
- No catalog: show "Fetch from Meta" button → calls `GET /api/catalog/fetch-catalogs` → populates dropdown → user picks → POST `/catalog/connect`
- Catalog connected: product grid, sync button, link to orders

#### `app/Views/catalog/orders.php`
Orders list mirroring WhatChimp screenshot:
- Filter bar: Any Catalog dropdown, Any Status dropdown, Search input
- Table columns: #, OrderId, Catalog, Phone Number, Buyer, Amount, Currency, Status (dropdown), Actions, Ordered at, Updated at, Reminder Sent at, Payment Method
- Status change: `POST /catalog/orders/:id/status`
- Expand row: show order_items array (product IDs, qty, price)

#### Update `app/Views/layouts/partials/sidebar.php`
```html
<li><a href="<?= base_url('catalog') ?>">🛍️ Catalog</a></li>
<li><a href="<?= base_url('catalog/orders') ?>">🛒 Orders</a></li>
```

#### Update `app/Views/settings/index.php` — add Catalog tab

```html
<!-- Catalog Tab -->
<div id="tab-catalog">
  <h3>Catalog Settings</h3>

  <div class="info-box">
    <strong>How to get your Catalog ID:</strong>
    <ol>
      <li>Go to <a href="https://business.facebook.com" target="_blank">business.facebook.com</a></li>
      <li>All Tools → Commerce Manager → select your catalog</li>
      <li>Copy the numeric ID shown in the URL or catalog info</li>
      <li>In WhatsApp Manager → Account Tools → Catalogue → connect same catalog</li>
    </ol>
  </div>

  <button onclick="fetchCatalogs()">📥 Fetch Catalogs from Meta</button>

  <select id="catalog-select" style="display:none">
    <option value="">Select a catalog...</option>
  </select>

  <button id="connect-btn" style="display:none" onclick="connectCatalog()">Connect</button>

  <?php if (!empty($waConfig['catalog_id'])): ?>
    <div class="connected">
      ✅ Connected: <code><?= esc($waConfig['catalog_id']) ?></code>
      <?php if (!empty($waConfig['catalog_name'])): ?>
        — <?= esc($waConfig['catalog_name']) ?>
      <?php endif; ?>
      <br>Last synced: <?= $waConfig['catalog_synced_at'] ?? 'Never' ?>
      <button onclick="syncCatalog()">🔄 Sync Now</button>
    </div>
  <?php endif; ?>
</div>

<script>
async function fetchCatalogs() {
    const res  = await fetch('/api/catalog/fetch-catalogs');
    const data = await res.json();
    if (data.error) { alert(data.error); return; }

    const select = document.getElementById('catalog-select');
    select.innerHTML = '<option value="">Select a catalog...</option>';
    (data.catalogs || []).forEach(c => {
        select.innerHTML += `<option value="${c.id}">${c.name} (${c.id})</option>`;
    });
    select.style.display = '';
    document.getElementById('connect-btn').style.display = '';
}

async function connectCatalog() {
    const catalogId = document.getElementById('catalog-select').value;
    if (!catalogId) { alert('Select a catalog first'); return; }

    const form = new FormData();
    form.append('catalog_id', catalogId);

    const res  = await fetch('/catalog/connect', { method: 'POST', body: form });
    const data = await res.json();
    if (data.success) {
        alert(`Connected! Found ${data.product_count} products.`);
        location.reload();
    } else {
        alert('Error: ' + data.error);
    }
}

async function syncCatalog() {
    const res  = await fetch('/catalog/sync', { method: 'POST' });
    const data = await res.json();
    alert(data.success ? `Synced! ${data.product_count} products.` : data.error);
}
</script>
```

#### Update `app/Views/inbox/index.php` — catalog send buttons

In compose area, add next to existing send button:
```html
<button onclick="openCatalogSend()" title="Send Catalog">🛍️</button>
<button onclick="openProductSend()" title="Send Product">🏷️</button>
```

JS:
```javascript
async function openCatalogSend() {
    const msg = prompt('Catalog message body:', 'Browse our products! 🛍️');
    if (!msg) return;

    await fetch('/api/catalog/send-catalog', {
        method: 'POST',
        body: new URLSearchParams({ conversation_id: CONVERSATION_ID, body_text: msg })
    });
}

async function openProductSend() {
    const res      = await fetch('/api/catalog/products');
    const data     = await res.json();
    if (!data.catalog_connected) { alert('No catalog connected. Go to Settings → Catalog.'); return; }

    const options  = data.products.map(p => `${p.retailer_id} — ${p.name}`).join('\n');
    const choice   = prompt(`Pick product (enter retailer_id):\n${options}`);
    if (!choice) return;

    await fetch('/api/catalog/send-product', {
        method: 'POST',
        body: new URLSearchParams({ conversation_id: CONVERSATION_ID, product_retailer_id: choice })
    });
}
```

---

## 5. Implementation Order

**Run in sequence — each step depends on previous:**

```
1.  php spark migrate           ← run AFTER adding migrations 000032 + 000033
2.  WhatsAppConfigModel         ← add 3 catalog fields to allowedFields
3.  CatalogOrderModel           ← new model file
4.  MetaApi.php                 ← add 5 catalog methods
5.  CatalogController.php       ← web controller
6.  Api/CatalogController.php   ← API controller (includes fetchCatalogs)
7.  WebhookController.php       ← add order case + processOrderMessage()
8.  FlowNodeSchemas.php         ← add send_catalog + send_product
9.  FlowEngine.php              ← execute catalog nodes
10. Routes.php                  ← 10 new routes
11. Views                       ← catalog/index, catalog/orders, sidebar, settings, inbox
```

Migration command:
```bash
php spark migrate
```

---

## 6. Meta API Requirements

Business must complete before catalog messages work:

1. Create catalog in Meta Commerce Manager (`business.facebook.com`)
2. Connect catalog to WA number: WhatsApp Manager → Account Tools → Catalogue → Choose Catalogue → Connect
3. Open Rovix Settings → Catalog tab → click "Fetch Catalogs from Meta" → select → Connect

Catalog ID = 16-digit number e.g. `1234567890123456`

---

## 7. Testing Checklist

- [ ] `php spark migrate` — both migrations run without error
- [ ] `/catalog` page loads, shows "Fetch from Meta" button
- [ ] Fetch catalogs → dropdown populates from Meta API
- [ ] Connect catalog → success + product count shown
- [ ] `/catalog` product grid appears after connect
- [ ] Sync button updates `catalog_synced_at`
- [ ] `/catalog/orders` loads with filter bar
- [ ] Inbox catalog button (🛍️) sends catalog message → appears in WhatsApp
- [ ] Inbox product button (🏷️) shows product picker → sends product card
- [ ] Customer orders via WhatsApp → webhook fires → order in `/catalog/orders`
- [ ] Order status dropdown updates correctly
- [ ] Flow editor shows `send_catalog` + `send_product` node types
- [ ] Flow with catalog node runs on trigger
- [ ] `/reports` page loads — no SQL error (Bug 1 fixed)
- [ ] Templates load in inbox compose (Bug 2 fix — lowercase 'approved')
- [ ] Incoming image/video classified correctly, not as 'document' (Bug 3 fix)
