# Rovix Migration Plan - Phases 4-15 Completion

This file contains the remaining detailed prompts for Phases 4-15 to append to rovix-migration-plan-complete.md

---

## Continuation from Phase 3.4

Create app/Commands/RunScheduled.php - see main document

### Testing Phase 3 (add to main document after Phase 3.4)

```bash
# Test commands
php spark queue:process
php spark run:scheduled

# Verify webhook endpoint
curl "http://localhost:8080/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test&hub_challenge=hello"

# Should return: hello
```

**Pass Criteria:**
- All Phase 3 endpoints working
- Jobs process successfully
- Messages send via API
- Webhook receives and processes events

---

## PHASE 4: Inbox Module (Week 3-4)

### Prompt 4.1 — Conversation List + Thread View with Real-time Updates

```
Build the Inbox module for Rovix AI Leads Tool with conversation list and message thread.

Reference original wacrm files:
- wacrm-main/src/app/(app)/inbox/page.tsx
- wacrm-main/src/components/inbox/conversation-list.tsx  
- wacrm-main/src/components/inbox/message-thread.tsx
- wacrm-main/src/components/inbox/message-bubble.tsx

Database tables: conversations, messages, contacts

Create app/Controllers/InboxController.php:

Methods:
1. index() GET - Main inbox page
   - Query conversations with JOIN to contacts for contact details
   - Include last_message_at, unread_count, status
   - Filter by status (open/pending/closed) via query param
   - Filter by assigned_agent_id if role is 'agent'
   - Order by last_message_at DESC
   - Paginate 50 per page
   - Pass data to view: conversations list, filters, stats

2. thread($conversationId) GET - Load conversation thread
   - Verify conversation belongs to current account
   - Query messages for this conversation ORDER BY created_at ASC
   - Include sender info (agent name if sender_type=agent)
   - Mark messages as read: update conversation.unread_count = 0
   - Return JSON with messages array

3. pollUpdates() GET - Long-polling endpoint for real-time updates
   - Accept since_timestamp parameter
   - Query conversations updated after since_timestamp
   - Wait up to 30 seconds or return immediately if changes found
   - Return JSON: updated_conversations array, new_message_count

Create app/Views/inbox/index.php:

Layout: Three-column design
- Left: Conversation list (scrollable)
- Middle: Message thread (selected conversation)
- Right: Contact sidebar (contact details, tags, notes)

Left Column - Conversation List:
- Search box at top (filter by contact name/phone)
- Filter tabs: All / Open / Pending / Closed
- Each conversation card shows:
  - Contact avatar (or initials bubble)
  - Contact name + phone
  - Last message preview (truncated to 60 chars)
  - Timestamp (format_relative_time)
  - Unread badge (if unread_count > 0)
  - Status indicator dot (green=open, yellow=pending, gray=closed)
- Active conversation highlighted
- Click conversation → load thread in middle column

Middle Column - Message Thread:
- Header bar:
  - Contact name + phone
  - Status dropdown (open/pending/closed) → AJAX update
  - Assign dropdown (list team members) → AJAX update
  - Close button (set status=closed)
- Scrollable message area:
  - Message bubbles: customer (left, gray), agent (right, blue), system (center, small)
  - Show timestamp on hover
  - Media messages: render image/video/document preview
  - Reaction emojis below bubble (click to add reaction)
- Composer at bottom (see Prompt 4.2)

Right Column - Contact Sidebar:
- Contact avatar + name
- Phone number (click to copy)
- Email (if set)
- Tags (colored chips)
- Quick actions: Add tag, Add note, Create deal
- Activity timeline (last 10 events)

JavaScript (Alpine.js + AJAX):
- Poll for updates every 10 seconds: fetch('/inbox/poll-updates?since=' + lastPoll)
- Update conversation list if new messages
- Play notification sound if new unread
- Auto-scroll message thread to bottom when opened
- Smooth scroll on new message arrival

