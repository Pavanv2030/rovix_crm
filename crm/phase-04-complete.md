## PHASE 4: Inbox Module (Week 3-4)

### Prompt 4.1 — Inbox Conversation List UI

```
Build the inbox conversation list interface for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/(dashboard)/inbox/page.tsx
- src/components/inbox/conversation-list.tsx
- src/components/inbox/conversation-item.tsx

Create app/Controllers/InboxController.php:

1. index() GET: Main inbox page
   - Load conversations ordered by last_message_at DESC
   - Join with contacts table to get contact details
   - Join with profiles table for assigned agent info
   - Group by status tabs: All, Open, Pending, Closed
   - Show unread count badge
   - Paginate: 50 per page
   - Pass to view: $conversations, $statusCounts, $selectedStatus

2. conversation($conversationId) GET: Load single conversation thread
   - Verify conversation belongs to current account (tenant check)
   - Load all messages ordered by created_at ASC
   - Join with media_files for media messages
   - Mark messages as read if sender_type='customer'
   - Decrement conversation.unread_count
   - Load contact details, tags, custom fields, deals
   - Pass to view: $conversation, $messages, $contact

3. search() GET: Search conversations
   - Search by contact name, phone, last message text
   - Filter by assigned agent, status, tags
   - Return JSON for Alpine.js live search

Create app/Views/inbox/index.php:

Uses main.php layout, $pageTitle = 'Inbox'

Left sidebar (40% width):
- Tabs: All | Open (badge) | Pending | Closed
- Search input with Alpine.js x-model="searchQuery"
- Filter dropdown: Assigned to me, Unassigned, All
- Conversation list (scrollable):
  - Each item shows:
    - Contact avatar (or initials if no avatar)
    - Contact name (bold if unread)
    - Last message preview (truncate at 50 chars)
    - Timestamp (relative: "2m ago", "1h ago", "Yesterday")
    - Unread badge (count)
    - Assigned agent badge (small avatar)
  - Active conversation: highlighted with slate-blue background
  - Click → load conversation thread via Alpine.js fetch or page reload

Right panel (60% width):
- If no conversation selected: Empty state "Select a conversation to start"
- If conversation selected: Load conversation thread (Prompt 4.2)

Alpine.js data:
x-data="{
  searchQuery: '',
  selectedFilter: 'all',
  conversations: <?= json_encode($conversations) ?>,
  filteredConversations() {
    return this.conversations.filter(c => 
      c.contact_name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
      c.phone.includes(this.searchQuery)
    )
  }
}"

Styling: Clean WhatsApp-style interface, white background, hover states, smooth transitions

Add routes:
GET /inbox → InboxController::index
GET /inbox/conversation/{id} → InboxController::conversation
GET /inbox/search → InboxController::search
```

### Prompt 4.2 — Conversation Thread & Message Composer

```
Build the conversation thread and message composer for Rovix AI Leads Tool.

Reference original wacrm files:
- src/components/inbox/conversation-thread.tsx
- src/components/inbox/message-composer.tsx
- src/components/inbox/message-item.tsx

Create app/Views/inbox/partials/conversation_thread.php:

This is loaded in the right panel when a conversation is selected.

Header (sticky at top):
- Contact avatar + name + phone
- Actions: Assign to dropdown, Close conversation button, More menu (Add tag, Add note, View profile)
- Tags displayed as colored chips

Thread area (scrollable, flex-col-reverse for bottom-to-top scroll):
- Group messages by date: "Today", "Yesterday", "Dec 15, 2024"
- Each message bubble:
  - If sender_type='customer': Left side, gray background
  - If sender_type='agent': Right side, slate-blue background, white text
  - If sender_type='system': Centered, small gray text
  - Content based on content_type:
    - text: Display content_text with URL detection (auto-link)
    - image: <img> with lightbox on click
    - video: <video> player
    - document: Download icon + filename
    - audio: <audio> player
    - template: Show template name + "Template message"
  - Reply indicator: If reply_to_message_id, show quoted message above
  - Timestamp: bottom right, small text (HH:MM format)
  - Status indicator for outgoing: sending (clock), sent (✓), delivered (✓✓), read (✓✓ blue)
  - Reactions: Show emoji below bubble if message_reactions exist
  - Error state: Red border + error_message if status='failed'

Composer area (sticky at bottom):
- Text input (textarea, auto-expand up to 5 lines)
- Buttons:
  - Attach media (image, video, document, audio) — opens file picker
  - Templates — opens modal to select template
  - Emoji picker (optional, can skip for MVP)
- Send button (slate-blue, disabled if empty)

Alpine.js for composer:
x-data="{
  messageText: '',
  selectedFile: null,
  sending: false,
  async sendMessage() {
    if (!this.messageText.trim() && !this.selectedFile) return;
    this.sending = true;
    const formData = new FormData();
    formData.append('conversation_id', '<?= $conversation['id'] ?>');
    formData.append('content_type', this.selectedFile ? 'image' : 'text');
    formData.append('content_text', this.messageText);
    if (this.selectedFile) formData.append('media', this.selectedFile);
    
    const response = await fetch('/api/whatsapp/send', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    
    if (response.ok) {
      this.messageText = '';
      this.selectedFile = null;
      // Reload thread or append message optimistically
      window.location.reload();
    }
    this.sending = false;
  }
}"

Real-time updates (optional for MVP):
- For now: Reload page every 10s with meta refresh or Alpine interval
- Future: WebSocket or polling endpoint for new messages

Keyboard shortcuts:
- Enter: Send message
- Shift+Enter: New line
- Ctrl+K: Open template selector

Create app/Views/inbox/partials/template_selector_modal.php:
- Modal overlay (Alpine.js x-show)
- List of approved templates from message_templates table
- Search/filter by name
- Click template → show variable inputs → send
```

### Prompt 4.3 — Media Upload & Voice Notes

```
Build media upload and voice note recording for Rovix AI Leads Tool.

Reference original wacrm files:
- src/components/inbox/media-upload.tsx
- src/components/inbox/voice-recorder.tsx

Create app/Controllers/Api/MediaController.php:

1. upload() POST: Handle media file uploads
   - Validate: max 16MB, allowed MIME types (image/*, video/*, audio/*, application/pdf, application/msword, etc.)
   - Generate UUID filename
   - Save to writable/uploads/chat-media/YYYY/MM/
   - Insert into media_files table: file_path, mime_type, file_size, original_filename, media_type
   - Return JSON: { success: true, media_id: uuid, url: /uploads/chat-media/... }
   - On error: return validation messages

2. download($mediaId) GET: Serve media file
   - Verify media belongs to current account (tenant check via media_files.account_id)
   - Update last_accessed_at
   - Set proper Content-Type header
   - Stream file with readfile()

Create app/Commands/CleanupMedia.php:
- Command name: media:cleanup
- Description: Delete media files older than 90 days
- Logic:
  1. SELECT * FROM media_files WHERE created_at < NOW() - INTERVAL 90 DAY
  2. For each: unlink(WRITEPATH . 'uploads/chat-media/' . $file_path)
  3. DELETE from media_files
  4. Log: "Cleaned up X files, freed Y MB"

Add to RunScheduled.php: Run media:cleanup daily at 2 AM

Create writable/uploads/chat-media/.htaccess:
<FilesMatch "\.php$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

Create public/assets/js/voice-recorder.js (optional for MVP):
- Use MediaRecorder API
- Record audio in webm/opus format
- Show recording timer
- Stop → upload as audio message
- Note: Requires HTTPS for getUserMedia API

Update composer in conversation_thread.php:
- File input for media with Alpine.js @change handler
- Preview selected image before sending
- Show upload progress bar
- Voice recorder button (optional)

Add routes:
POST /api/media/upload → MediaController::upload (auth + account filter)
GET /api/media/download/{id} → MediaController::download (auth + account filter)
```

### Prompt 4.4 — Conversation Actions & Assignment

```
Build conversation management actions for Rovix AI Leads Tool.

Reference original wacrm files:
- src/app/api/conversations/[id]/assign/route.ts
- src/app/api/conversations/[id]/close/route.ts

Create app/Controllers/Api/ConversationController.php:

1. assign() POST: Assign conversation to agent
   - Input: conversation_id, agent_id (can be null for unassign)
   - Verify agent_id is a valid user in current account
   - Update conversations.assigned_agent_id
   - Insert system message: "Conversation assigned to {agent_name}"
   - Return JSON success

2. updateStatus() POST: Change conversation status
   - Input: conversation_id, status (open|pending|closed)
   - Update conversations.status
   - If closing: reset unread_count to 0
   - Insert system message: "Conversation marked as {status}"
   - Return JSON success

3. addTag() POST: Tag a contact from inbox
   - Input: conversation_id, tag_id
   - Get contact_id from conversation
   - Insert into contact_tags (if not exists)
   - Return JSON success

4. addNote() POST: Add note to contact
   - Input: conversation_id, note_text
   - Insert into contact_notes with current user_id
   - Return JSON success

Create app/Views/inbox/partials/conversation_actions_dropdown.php:
- Alpine.js dropdown menu
- Actions:
  - Assign to: Submenu with list of agents (from profiles WHERE account_role IN ('owner','admin','agent'))
  - Mark as: Open | Pending | Closed
  - Add tag: Modal with tag selector + "Create new tag" option
  - Add note: Modal with textarea
  - View contact profile: Link to /contacts/{id}
  - View deals: Show associated deals if any

Update conversation_thread.php header to include this dropdown.

Add routes:
POST /api/conversations/assign → ConversationController::assign
POST /api/conversations/status → ConversationController::updateStatus
POST /api/conversations/tag → ConversationController::addTag
POST /api/conversations/note → ConversationController::addNote
```

### Testing Phase 4

Manual test checklist:

```bash
# 1. Navigate to inbox
http://localhost/rovix-ai-leads-tool/public/inbox

# 2. Verify conversation list loads
- Check conversations appear
- Check unread badges show
- Check status tabs work (All, Open, Pending, Closed)

# 3. Click a conversation
- Thread loads in right panel
- Messages display correctly
- Customer messages on left (gray)
- Agent messages on right (blue)

# 4. Send a text message
- Type in composer
- Click send
- Should call /api/whatsapp/send
- Message appears in thread with "sending" status

# 5. Upload an image
- Click attach button
- Select image < 16MB
- Preview shows
- Send → uploads to /api/media/upload
- Message appears with image thumbnail

# 6. Assign conversation
- Click assign dropdown
- Select an agent
- System message appears: "Assigned to Agent Name"

# 7. Close conversation
- Click "Close conversation"
- Status changes to closed
- Moves to "Closed" tab

# 8. Search conversations
- Type contact name or phone in search
- List filters in real-time

# 9. Add tag to contact
- Open actions menu
- Click "Add tag"
- Select tag
- Tag chip appears in conversation header

# 10. Add note
- Open actions menu
- Click "Add note"
- Enter text → save
- Verify note saved (check contact profile later)
```

**Pass Criteria:**
- ✅ Conversation list shows all conversations for current account only
- ✅ Tenant isolation works (can't see other accounts' conversations)
- ✅ Messages send via WhatsApp API
- ✅ Media uploads work, files saved to writable/uploads/chat-media/
- ✅ Media downloads require auth + tenant check
- ✅ Conversations can be assigned, tagged, closed
- ✅ Search filters conversations client-side
- ✅ UI is responsive (mobile-friendly)

**Common Issues:**
- Messages not sending: Check WhatsApp config exists and access_token is valid
- Media upload fails: Check writable/uploads/chat-media/ directory exists and is writable (chmod 755)
- Can see other accounts' data: BaseModel scoping not working, check session('account_id') is set
- Images don't display: Check media download route has proper Content-Type headers

---
