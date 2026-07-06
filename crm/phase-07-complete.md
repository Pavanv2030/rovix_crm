## PHASE 7: Message Templates (Week 6)

### Prompt 7.1 — Template Manager (CRUD)

```
Build the WhatsApp message template manager for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/templates/page.tsx
- src/components/templates/template-list.tsx
- src/lib/api/templates.ts

IMPORTANT: WhatsApp templates must be approved by Meta before use. This module manages the template lifecycle.

Create app/Controllers/TemplatesController.php:

1. index() GET: List all templates
   - Load templates ordered by created_at DESC
   - Group by status: Draft, Pending, Approved, Rejected
   - Show template preview cards
   - Pass to view: $templates, $statusCounts

2. create() GET: Show template creation form
   - Pass empty form with template structure
   - Show Meta guidelines (character limits, variable rules)

3. store() POST: Create new template
   - Validate:
     - name required (lowercase, no spaces, underscores only)
     - language required (default 'en')
     - category required (marketing, utility, authentication)
     - body_text required (max 1024 chars)
     - footer_text optional (max 60 chars)
     - Variables: {{1}}, {{2}}, etc. (max 10 variables per template)
   - Insert template with status='draft'
   - Redirect to template detail with success

4. view($templateId) GET: Show template detail
   - Load template
   - Show preview with sample variables
   - Show submission status
   - Show quality score (if approved)
   - Show rejection reason (if rejected)
   - Pass to view: $template

5. edit($templateId) GET: Edit template
   - Only editable if status='draft' or 'rejected'
   - Approved templates cannot be edited (must create new version)
   - Pass to view: $template

6. update($templateId) POST: Update template
   - Validate status is 'draft' or 'rejected'
   - Update template fields
   - Redirect with success

7. delete($templateId) POST: Delete template
   - Verify has_min_role('admin')
   - Only allow delete if status='draft' or 'rejected'
   - Cannot delete approved templates (archive instead)
   - Delete template
   - Redirect to list

8. submitForApproval($templateId) POST: Submit to Meta for approval
   - Validate template is complete
   - Call Meta API to create template
   - Update status to 'pending'
   - Store meta_template_id from response
   - Redirect with success message: "Template submitted for approval"

Create app/Libraries/WhatsApp/TemplateSubmitter.php:

<?php
namespace App\Libraries\WhatsApp;

use App\Models\WhatsAppConfigModel;
use App\Models\MessageTemplateModel;

class TemplateSubmitter
{
    private MetaApi $metaApi;
    private Encryption $encryption;

    public function __construct()
    {
        $this->metaApi = new MetaApi();
        $this->encryption = new Encryption();
    }

    /**
     * Submit template to Meta for approval
     */
    public function submit(array $template, string $accountId): string
    {
        // Get WhatsApp config
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', $accountId)->first();

        if (!$waConfig) {
            throw new \Exception('WhatsApp not connected');
        }

        $accessToken = $this->encryption->decrypt($waConfig['access_token']);
        $wabaId = $waConfig['waba_id'];

        // Build template payload for Meta API
        $payload = [
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => strtoupper($template['category']),
            'components' => $this->buildComponents($template)
        ];

        // Call Meta API
        $client = \Config\Services::curlrequest([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ]
        ]);

        $response = $client->post(
            "https://graph.facebook.com/v21.0/{$wabaId}/message_templates",
            ['json' => $payload]
        );

        if ($response->getStatusCode() !== 200) {
            $error = json_decode($response->getBody(), true);
            throw new \Exception('Template submission failed: ' . ($error['error']['message'] ?? 'Unknown error'));
        }

        $result = json_decode($response->getBody(), true);
        return $result['id']; // Meta template ID
    }

    private function buildComponents(array $template): array
    {
        $components = [];

        // Header component
        if ($template['header_type'] !== 'none' && !empty($template['header_content'])) {
            $header = ['type' => 'HEADER'];

            if ($template['header_type'] === 'text') {
                $header['format'] = 'TEXT';
                $header['text'] = $template['header_content'];
            } elseif ($template['header_type'] === 'image') {
                $header['format'] = 'IMAGE';
                $header['example'] = ['header_handle' => [$template['header_content']]];
            } elseif ($template['header_type'] === 'video') {
                $header['format'] = 'VIDEO';
                $header['example'] = ['header_handle' => [$template['header_content']]];
            } elseif ($template['header_type'] === 'document') {
                $header['format'] = 'DOCUMENT';
                $header['example'] = ['header_handle' => [$template['header_content']]];
            }

            $components[] = $header;
        }

        // Body component (required)
        $body = [
            'type' => 'BODY',
            'text' => $template['body_text']
        ];

        // Add sample values for variables
        if (!empty($template['sample_values'])) {
            $samples = json_decode($template['sample_values'], true);
            if (!empty($samples['body'])) {
                $body['example'] = ['body_text' => [$samples['body']]];
            }
        }

        $components[] = $body;

        // Footer component (optional)
        if (!empty($template['footer_text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $template['footer_text']
            ];
        }

        // Buttons component (optional)
        if (!empty($template['buttons'])) {
            $buttons = json_decode($template['buttons'], true);
            $buttonComponents = [];

            foreach ($buttons as $button) {
                if ($button['type'] === 'QUICK_REPLY') {
                    $buttonComponents[] = [
                        'type' => 'QUICK_REPLY',
                        'text' => $button['text']
                    ];
                } elseif ($button['type'] === 'URL') {
                    $buttonComponents[] = [
                        'type' => 'URL',
                        'text' => $button['text'],
                        'url' => $button['url']
                    ];
                } elseif ($button['type'] === 'PHONE_NUMBER') {
                    $buttonComponents[] = [
                        'type' => 'PHONE_NUMBER',
                        'text' => $button['text'],
                        'phone_number' => $button['phone']
                    ];
                }
            }

            if (!empty($buttonComponents)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttonComponents
                ];
            }
        }

        return $components;
    }
}

Create app/Views/templates/index.php:

Layout: main.php, $pageTitle = 'Message Templates'

Header:
- "New Template" button (primary, slate-blue)
- Status tabs: All (badge) | Draft | Pending | Approved | Rejected

Template Grid:
Cards in responsive grid (3 columns on desktop, 1 on mobile)

Each card:
- Status badge (top-right corner, colored: draft=gray, pending=yellow, approved=green, rejected=red)
- Template name (bold)
- Category badge (small)
- Language badge (small, e.g., "EN")
- Body preview (truncated at 100 chars)
- Footer preview (if present)
- Button previews (if present)
- Actions:
  - View
  - Edit (if draft/rejected)
  - Submit for Approval (if draft)
  - Delete (if draft/rejected)

Empty state (if no templates):
"No templates yet. Create your first WhatsApp message template to get started."

Create app/Views/templates/create.php:

Layout: main.php, $pageTitle = 'Create Template'

Form sections:

1. Basic Info:
   - Name: input (lowercase, underscores only, validation: /^[a-z0-9_]+$/)
   - Category: dropdown (Marketing, Utility, Authentication)
   - Language: dropdown (English, Hindi, Spanish, etc.)

2. Header (optional):
   - Type: dropdown (None, Text, Image, Video, Document)
   - Content: textarea (if text) or URL input (if media)

3. Body (required):
   - Textarea with character counter (max 1024)
   - Variable helper: "Add Variable" button inserts {{1}}, {{2}}, etc.
   - Preview panel (right side, live preview with sample data)

4. Footer (optional):
   - Input (max 60 chars)

5. Buttons (optional):
   - Button type: Quick Reply, Call to Action (URL), Call to Action (Phone)
   - Max 3 buttons
   - Each button: text input + URL/phone input

6. Sample Values (for approval):
   - For each variable in body: sample input
   - Required for template submission

Alpine.js for live preview:
x-data="{
  name: '',
  category: 'utility',
  language: 'en',
  headerType: 'none',
  headerContent: '',
  bodyText: '',
  footerText: '',
  buttons: [],
  sampleValues: {},
  
  get variableCount() {
    const matches = this.bodyText.match(/\{\{\d+\}\}/g);
    return matches ? matches.length : 0;
  },
  
  addVariable() {
    const nextNum = this.variableCount + 1;
    this.bodyText += ` {{${nextNum}}}`;
  },
  
  addButton(type) {
    if (this.buttons.length >= 3) return;
    this.buttons.push({ type, text: '', url: '', phone: '' });
  }
}"

Preview panel shows WhatsApp-style message bubble with actual formatting.

Create app/Views/templates/view.php:

Layout: main.php, $pageTitle = $template['name']

Left panel (40%):
- Template details card:
  - Name
  - Status badge (large)
  - Category
  - Language
  - Created date
  - Last updated
  - Quality score (if approved)
  - Meta template ID (if submitted)
  - Rejection reason (if rejected)

Actions:
- Submit for Approval (if draft)
- Edit (if draft/rejected)
- Archive (if approved - for future feature)
- Delete (if draft/rejected)

Right panel (60%):
- WhatsApp-style preview:
  - Shows exactly how message appears in WhatsApp
  - Header (if present)
  - Body with variables replaced by sample values
  - Footer (if present)
  - Buttons (if present)

- Variable mapping table:
  - Shows each {{N}} variable
  - Sample value column
  - Description column (for documentation)

Add routes:
GET /templates → TemplatesController::index
GET /templates/create → TemplatesController::create
POST /templates → TemplatesController::store
GET /templates/{id} → TemplatesController::view
GET /templates/{id}/edit → TemplatesController::edit
POST /templates/{id} → TemplatesController::update
POST /templates/{id}/submit → TemplatesController::submitForApproval
POST /templates/{id}/delete → TemplatesController::delete
```

### Prompt 7.2 — Template Approval Workflow

```
Build the template approval workflow and status sync for Rovix AI Leads Tool.

Reference: Webhook already handles template status updates (Phase 3.3)

Create app/Controllers/Api/TemplatesController.php:

1. checkStatus($templateId) GET: Check approval status from Meta
   - Get template
   - Call Meta API to get current status
   - Update local status if changed
   - Return JSON: { status, quality_score, rejection_reason }

2. sync() POST: Sync all pending templates with Meta
   - Find all templates with status='pending'
   - For each: call Meta API to get status
   - Update local records
   - Return JSON: { synced_count, approved_count, rejected_count }

Update app/Controllers/TemplatesController.php:

Add method refreshStatus($templateId):
- Manually refresh status from Meta
- Called when user clicks "Refresh Status" button on pending template
- Updates template record
- Shows flash message with new status

Create app/Commands/SyncTemplateStatus.php:

<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\MessageTemplateModel;
use App\Models\WhatsAppConfigModel;
use App\Models\BaseModel;
use App\Libraries\WhatsApp\Encryption;

class SyncTemplateStatus extends BaseCommand
{
    protected $group = 'Templates';
    protected $name = 'templates:sync';
    protected $description = 'Sync template approval status from Meta';

    public function run(array $params)
    {
        BaseModel::setBypassAccountScope(true);

        $templateModel = new MessageTemplateModel();
        $pendingTemplates = $templateModel->where('status', 'pending')->findAll();

        if (empty($pendingTemplates)) {
            CLI::write('No pending templates to sync', 'yellow');
            return;
        }

        CLI::write('Syncing ' . count($pendingTemplates) . ' pending templates...', 'green');

        $synced = 0;
        $approved = 0;
        $rejected = 0;

        foreach ($pendingTemplates as $template) {
            try {
                $status = $this->checkTemplateStatus($template);
                
                if ($status['status'] !== 'pending') {
                    $templateModel->update($template['id'], [
                        'status' => $status['status'],
                        'quality_score' => $status['quality_score'] ?? null
                    ]);

                    $synced++;
                    if ($status['status'] === 'approved') $approved++;
                    if ($status['status'] === 'rejected') $rejected++;

                    CLI::write("  ✓ {$template['name']}: {$status['status']}", 'cyan');
                }
            } catch (\Exception $e) {
                CLI::write("  ✗ {$template['name']}: " . $e->getMessage(), 'red');
            }
        }

        CLI::write("\nSync complete: {$synced} updated ({$approved} approved, {$rejected} rejected)", 'green');
    }

    private function checkTemplateStatus(array $template): array
    {
        // Get WhatsApp config for this account
        $waConfigModel = new WhatsAppConfigModel();
        $waConfig = $waConfigModel->where('account_id', $template['account_id'])->first();

        if (!$waConfig) {
            throw new \Exception('WhatsApp not connected');
        }

        $encryption = new Encryption();
        $accessToken = $encryption->decrypt($waConfig['access_token']);

        // Call Meta API
        $client = \Config\Services::curlrequest([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ]
        ]);

        $response = $client->get(
            "https://graph.facebook.com/v21.0/{$template['meta_template_id']}?fields=status,quality_score"
        );

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Failed to fetch template status');
        }

        $result = json_decode($response->getBody(), true);

        return [
            'status' => strtolower($result['status']),
            'quality_score' => $result['quality_score'] ?? null
        ];
    }
}

Add to RunScheduled.php:
Run templates:sync every hour (check at minute 0 of each hour)

Create app/Views/templates/partials/status_badge.php:

<?php
$colors = [
    'draft' => 'bg-gray-500',
    'pending' => 'bg-yellow-500',
    'approved' => 'bg-green-500',
    'rejected' => 'bg-red-500',
    'paused' => 'bg-orange-500',
    'disabled' => 'bg-gray-700'
];

$icons = [
    'draft' => '📝',
    'pending' => '⏳',
    'approved' => '✅',
    'rejected' => '❌',
    'paused' => '⏸',
    'disabled' => '🚫'
];
?>

<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white <?= $colors[$status] ?>">
    <span class="mr-1"><?= $icons[$status] ?></span>
    <?= ucfirst($status) ?>
</span>

Update templates/view.php to show:
- If status='rejected': Display rejection reason prominently
- If status='pending': Show "Refresh Status" button + last checked timestamp
- If status='approved': Show quality score (High, Medium, Low)
- Link to Meta Business Manager for manual review

Add helper text in create form:
"WhatsApp Template Guidelines:
- Marketing templates: Promotional content, requires opt-in
- Utility templates: Account updates, order confirmations
- Authentication templates: OTP, password resets
- Variables: Use {{1}}, {{2}} format
- Character limits: Body 1024 chars, Footer 60 chars
- Approval time: 24-48 hours typically"
```

### Testing Phase 7

Manual test checklist:

```bash
# 1. Navigate to templates
http://localhost:8080/templates

# Test: Empty state shows

# 2. Create template
- Click "New Template"
- Name: welcome_message
- Category: Utility
- Body: "Hi {{1}}, welcome to {{2}}! Your account is now active."
- Add sample values: ["John", "Rovix AI"]
- Footer: "Reply STOP to unsubscribe"
- Add Quick Reply button: "Get Started"
- Save

# Test: Template created with status='draft'

# 3. View template detail
- Click template card

# Test: Preview shows WhatsApp-style message with variables replaced

# 4. Submit for approval
- Click "Submit for Approval"

# Test: Template status changes to 'pending', meta_template_id stored

# 5. Check Meta Business Manager
- Login to business.facebook.com
- Navigate to WhatsApp Manager > Message Templates
- Verify template appears in pending state

# 6. Simulate approval (or wait for real approval)
- In Meta: approve or reject the template
- OR manually update DB: UPDATE message_templates SET status='approved' WHERE id=X

# 7. Sync status
php spark templates:sync

# Test: Status updates to 'approved' or 'rejected'

# 8. View approved template
- Refresh templates list
- Template shows green "Approved" badge

# Test: Cannot edit approved template (button disabled/hidden)

# 9. Create rejected template scenario
- Create another template with problematic content
- Submit for approval
- Wait for rejection
- Sync status

# Test: Shows "Rejected" badge + rejection reason

# 10. Edit rejected template
- Click "Edit" on rejected template
- Fix issues
- Resubmit

# Test: Status resets to 'pending', new meta_template_id assigned

# 11. Test template with different components
- Create template with image header
- Create template with 3 buttons (Quick Reply, URL, Phone)
- Create template with 5 variables

# Test: All components render correctly in preview

# 12. Test template name validation
- Try name with spaces: "welcome message"
- Try name with capitals: "WelcomeMessage"
- Try name with special chars: "welcome-message!"

# Test: Validation fails, shows error messages

# 13. Test variable detection
- Body: "Hi {{1}}, your code is {{2}}. Valid for {{3}} minutes."
- Check variable count

# Test: Shows 3 variables, requires 3 sample values

# 14. Webhook status update
- Simulate webhook call with template status update
curl -X POST http://localhost:8080/api/whatsapp/webhook \
  -H "X-Hub-Signature-256: sha256=..." \
  -d '{"entry":[{"changes":[{"value":{"message_template_status_update":{"message_template_id":"123","event":"approved"}}}]}]}'

# Test: Template status updates automatically

# 15. Tenant isolation
- Login as different account
- View templates

# Test: Only see own templates, not other accounts'
```

**Pass Criteria:**
- ✅ Template CRUD works (create, view, edit, delete)
- ✅ Template submission to Meta API succeeds
- ✅ meta_template_id is stored
- ✅ Status sync works (manual and scheduled)
- ✅ Webhook updates template status automatically
- ✅ Preview renders correctly (header, body, footer, buttons)
- ✅ Variable detection works ({{1}}, {{2}}, etc.)
- ✅ Sample values validated (must match variable count)
- ✅ Name validation works (lowercase, underscores only)
- ✅ Character limits enforced (body 1024, footer 60)
- ✅ Cannot edit approved templates
- ✅ Can edit and resubmit rejected templates
- ✅ Rejection reason displays for rejected templates
- ✅ Quality score displays for approved templates
- ✅ Tenant isolation (accounts only see own templates)

**Common Issues:**
- Submission fails: Check WABA_ID configured, check access_token has template permissions
- Status not updating: Check webhook signature verification passes, check meta_template_id matches
- Preview not rendering: Check variables replaced correctly, check buttons JSON valid
- Name validation fails: Ensure lowercase, no spaces, underscores only
- Sample values not saving: Check JSON encoding, check field accepts JSON
- Can't resubmit rejected: Check status check allows 'rejected' status for editing
- Sync command times out: Reduce batch size, add sleep between API calls

---
