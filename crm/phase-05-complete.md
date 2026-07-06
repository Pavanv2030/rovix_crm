## PHASE 5: Contacts Module (Week 4-5)

### Prompt 5.1 — Contacts CRUD & List View

```
Build the contacts management module for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/contacts/page.tsx
- src/components/contacts/contact-list.tsx
- src/components/contacts/contact-detail.tsx

Create app/Controllers/ContactsController.php:

1. index() GET: Contacts list page
   - Load all contacts with pagination (50 per page)
   - Join with contact_tags to show tags
   - Count conversations per contact
   - Count deals per contact
   - Search filters: name, phone, email, company, tags
   - Sort options: name, created_at, last_contact_date
   - Pass to view: $contacts, $tags, $totalCount

2. view($contactId) GET: Single contact profile
   - Load contact with all relationships:
     - Tags (from contact_tags join tags)
     - Custom field values (from contact_custom_values join custom_fields)
     - Notes (from contact_notes join profiles for author info)
     - Conversations (recent 10)
     - Deals (all)
   - Calculate stats: total conversations, open deals count, total deal value
   - Pass to view: $contact, $tags, $customFields, $notes, $conversations, $deals, $stats

3. create() GET: Show create form
   - Load available tags
   - Load custom fields
   - Pass to view: $tags, $customFields

4. store() POST: Create new contact
   - Validate: phone required, normalize phone
   - Check duplicate: UNIQUE(account_id, phone_normalized)
   - Insert contact
   - Insert tags if provided (contact_tags)
   - Insert custom field values if provided
   - Redirect to contact profile with success message

5. edit($contactId) GET: Show edit form
   - Load contact with current tags and custom values
   - Pass to view: $contact, $tags, $customFields

6. update($contactId) POST: Update contact
   - Validate inputs
   - Update contact record
   - Sync tags (delete old, insert new)
   - Update custom field values
   - Redirect with success message

7. delete($contactId) POST: Delete contact
   - Verify has_min_role('admin') — only admin+ can delete
   - CASCADE delete via FK: contact_tags, contact_custom_values, contact_notes
   - SET NULL on conversations.contact_id, deals.contact_id
   - Delete contact
   - Redirect to list with success message

Create app/Views/contacts/index.php:

Layout: main.php, $pageTitle = 'Contacts'

Header:
- Search input (Alpine.js live filter)
- Filter dropdowns: Tags, Date added
- Sort dropdown: Name, Recent, Last contact
- "Import CSV" button
- "New Contact" button (primary, slate-blue)

Contacts table:
Columns: Avatar | Name | Phone | Email | Company | Tags | Conversations | Deals | Actions

Each row:
- Avatar: initials or image
- Name: clickable → /contacts/{id}
- Phone: formatted with country code
- Tags: colored chips (max 3 visible, "+2 more")
- Conversations: count with link to inbox filtered by contact
- Deals: count + total value (₹X)
- Actions: Edit | Delete (with confirmation modal)

Alpine.js for filtering:
x-data="{
  searchQuery: '',
  selectedTags: [],
  contacts: <?= json_encode($contacts) ?>,
  filteredContacts() {
    return this.contacts.filter(c => {
      const matchesSearch = c.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                           c.phone.includes(this.searchQuery);
      const matchesTags = this.selectedTags.length === 0 || 
                         c.tags.some(t => this.selectedTags.includes(t.id));
      return matchesSearch && matchesTags;
    });
  }
}"

Responsive: Table → card layout on mobile
```

### Prompt 5.2 — Contact Profile & Timeline

```
Build the detailed contact profile view for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/contacts/[id]/page.tsx
- src/components/contacts/contact-timeline.tsx

Create app/Views/contacts/view.php:

Layout: main.php, $pageTitle = $contact['name']

Left panel (30%):
Contact Card:
- Large avatar
- Name (editable inline with Alpine.js)
- Phone (click to open WhatsApp)
- Email (click to send email)
- Company
- Tags (editable, add/remove)
- Custom fields (each field rendered based on field_type)
- "Edit Contact" button
- "Delete Contact" button (admin only, with confirmation)

Stats cards:
- Total Conversations: {count}
- Open Deals: {count} (₹{value})
- Won Deals: {count} (₹{value})
- First Contact: {date}

Right panel (70%):
Tabs: Timeline | Notes | Deals | Conversations

Timeline Tab:
- Chronological activity feed (newest first)
- Events from multiple sources:
  - Contact created
  - Tag added/removed
  - Custom field updated
  - Conversation started/closed
  - Deal created/won/lost
  - Note added
  - Automation triggered
  - Broadcast received
- Each event: icon, description, timestamp, actor (agent name)

Notes Tab:
- List of all notes from contact_notes
- Each note: text, author avatar + name, timestamp, delete button (if own note or admin)
- "Add Note" textarea at top (Alpine.js, saves via AJAX)

Deals Tab:
- List of all deals associated with this contact
- Each deal: title, pipeline → stage, value, status, expected close date
- "Create Deal" button → opens modal

Conversations Tab:
- List of recent conversations
- Each: last message preview, timestamp, status badge, unread count
- Click → opens in inbox

Alpine.js for inline editing:
x-data="{
  editingField: null,
  editValue: '',
  async saveField(field) {
    await fetch('/api/contacts/<?= $contact['id'] ?>/update-field', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ field, value: this.editValue })
    });
    this.editingField = null;
    location.reload();
  }
}"
```

### Prompt 5.3 — Tags & Custom Fields Management

```
Build tag and custom field management for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/settings/tags/page.tsx
- src/app/(dashboard)/settings/custom-fields/page.tsx

Create app/Controllers/Api/TagsController.php:

1. index() GET: List all tags for current account
   - Return JSON: [{ id, name, color, contact_count }]

2. store() POST: Create new tag
   - Validate: name required, unique per account, color hex format
   - Insert into tags
   - Return JSON: created tag

3. update($tagId) POST: Update tag
   - Validate: name unique (except self)
   - Update tag
   - Return JSON: updated tag

4. delete($tagId) DELETE: Delete tag
   - CASCADE delete: contact_tags entries
   - Delete tag
   - Return JSON: success

Create app/Controllers/Api/CustomFieldsController.php:

1. index() GET: List all custom fields
   - Return JSON: [{ id, field_name, field_type, field_options }]

2. store() POST: Create custom field
   - Validate: field_name required, field_type ENUM
   - If field_type='dropdown', validate field_options is JSON array
   - Insert into custom_fields
   - Return JSON: created field

3. update($fieldId) POST: Update custom field
   - Update custom_fields
   - Return JSON: updated field

4. delete($fieldId) DELETE: Delete custom field
   - CASCADE delete: contact_custom_values
   - Delete custom_fields
   - Return JSON: success

Create app/Views/settings/tags.php:
- List of tags with color preview
- Each tag: name, color swatch, contact count, edit/delete buttons
- "New Tag" button → modal with name input + color picker
- Color picker: Simple palette of 12 preset colors

Create app/Views/settings/custom_fields.php:
- List of custom fields
- Each: field_name, field_type, actions
- "New Field" button → modal with:
  - Field name input
  - Field type dropdown: Text, Number, Date, Dropdown
  - If dropdown selected: Options input (comma-separated)
- Edit/delete buttons

Add to Settings sidebar:
- Tags
- Custom Fields
```

### Prompt 5.4 — CSV Import

```
Build CSV contact import for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/contacts/import/page.tsx
- src/lib/csv-parser.ts

Create app/Controllers/ContactsController.php (add method):

8. import() GET: Show CSV import page
   - Instructions: CSV format, column mapping
   - Upload form
   - Pass to view: $customFields (for mapping)

9. processImport() POST: Process uploaded CSV
   - Validate: file uploaded, .csv extension, max 10MB
   - Parse CSV using league/csv package (add via composer)
   - First row = headers
   - Show preview: first 5 rows
   - Column mapping interface: map CSV columns to contact fields
   - Store uploaded file in writable/uploads/csv-imports/
   - Store mapping in session
   - Redirect to confirm page

10. confirmImport() POST: Execute import
    - Get mapping from session
    - Read CSV file
    - For each row:
      - Normalize phone
      - Check if contact exists (by phone_normalized)
      - If exists: update, if not: insert
      - Handle tags (comma-separated in CSV)
      - Handle custom fields
      - Track: created_count, updated_count, skipped_count, error_count
    - Display summary
    - Delete CSV file
    - Redirect to contacts list

Create app/Views/contacts/import.php:

Step 1: Upload CSV
- File input
- Format requirements:
  Required columns: phone
  Optional columns: name, email, company, tags (comma-separated)
  Custom fields: use field names as column headers
- Sample CSV download link

Step 2: Map Columns (after upload)
- Preview table showing first 5 rows
- Dropdowns for each CSV column to map to contact fields
- Auto-detect: if CSV column name matches field name, pre-select
- Skip column option

Step 3: Confirm & Import
- Summary: X rows to import
- Duplicate handling: Update existing | Skip duplicates
- Progress bar (Alpine.js or page refresh with session progress)

Step 4: Results
- Summary: X created, Y updated, Z errors
- Error log: row number, phone, error message
- "Go to Contacts" button

Install CSV parser:
composer require league/csv

Add to .gitignore:
writable/uploads/csv-imports/
```

### Testing Phase 5

Manual test checklist:

```bash
# 1. Contacts list
http://localhost/rovix-ai-leads-tool/public/contacts

# Test: List loads, search works, filters work

# 2. Create contact
- Click "New Contact"
- Fill: Name, Phone (required), Email, Company
- Select tags
- Fill custom fields
- Save

# Test: Contact created, redirects to profile

# 3. View contact profile
- Click contact name
- Verify: all details show, tags display, stats accurate

# 4. Edit contact
- Click "Edit Contact"
- Change name, add tag
- Save

# Test: Changes saved, profile updated

# 5. Add note
- Go to Notes tab
- Type note → Save

# Test: Note appears with your name + timestamp

# 6. Timeline
- View Timeline tab

# Test: Shows contact created event, tag added, note added

# 7. Delete contact (as admin)
- Click "Delete Contact"
- Confirm

# Test: Contact deleted, conversations/deals remain but contact_id set to NULL

# 8. Tags management
- Go to Settings → Tags
- Create new tag: "VIP" with red color
- Edit tag: change color to gold
- Delete tag (with no contacts)

# Test: Tags CRUD works

# 9. Custom fields
- Settings → Custom Fields
- Create field: "Lead Source", type Dropdown, options: "Website,Referral,Ad"
- Edit contact → fill custom field
- View contact → custom field displays

# Test: Custom fields work

# 10. CSV Import
- Create test CSV:
  phone,name,email,tags
  919876543210,Test User,test@example.com,"Hot Lead,VIP"
  919876543211,Another User,test2@example.com,Hot Lead

- Go to Contacts → Import CSV
- Upload file
- Map columns (auto-detected)
- Confirm import

# Test: 2 contacts created, tags assigned, no errors

# 11. Duplicate import
- Re-upload same CSV with "Update existing" option

# Test: 0 created, 2 updated, no duplicates

# 12. Tenant isolation
- Create second account (different user)
- Login as second user
- View contacts list

# Test: Only see contacts for second account, not first account's contacts
```

**Pass Criteria:**
- ✅ All CRUD operations work for contacts
- ✅ Tags can be added/removed
- ✅ Custom fields render correctly (text, number, date, dropdown)
- ✅ Timeline shows all events chronologically
- ✅ Notes can be added/deleted
- ✅ CSV import handles 1000+ rows without timeout
- ✅ Duplicate detection works (by phone_normalized)
- ✅ Tenant isolation: accounts only see their own contacts
- ✅ Phone normalization works: +91 98765 43210 → 919876543210
- ✅ Delete contact doesn't break conversations/deals (FK SET NULL)

**Common Issues:**
- CSV import times out: Increase max_execution_time in php.ini, or process in batches
- Phone duplicates: Check phone_normalized is being set correctly
- Tags not showing: Join query missing, check contact_tags table
- Can't delete tag: FK constraint, check if tag is used by contacts
- Custom field dropdown not working: Check field_options is valid JSON array

---
