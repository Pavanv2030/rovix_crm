<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php helper(['format', 'auth', 'role']); ?>

<!-- Back nav -->
<div class="flex items-center gap-2 mb-4 text-sm">
    <a href="<?= base_url('contacts') ?>" class="text-gray-400 hover:text-gray-600">← Contacts</a>
    <span class="text-gray-300">/</span>
    <span class="text-gray-700 font-medium"><?= esc($contact['name'] ?? $contact['phone'] ?? 'Contact') ?></span>
</div>

<div class="flex flex-col lg:flex-row gap-6" x-data="{ activeTab: 'timeline' }">

    <!-- Left Panel: Contact Card -->
    <div class="w-full lg:w-72 flex-shrink-0 space-y-4">

        <!-- Contact Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 text-center">
            <div class="w-16 h-16 rounded-full bg-blue-900 text-white flex items-center justify-center text-2xl font-bold mx-auto mb-3">
                <?= strtoupper(substr($contact['name'] ?? $contact['phone'] ?? '?', 0, 1)) ?>
            </div>
            <h2 class="text-lg font-semibold text-gray-900"><?= esc($contact['name'] ?? 'Unknown') ?></h2>
            <?php if ($contact['company']): ?>
            <p class="text-sm text-gray-500 mt-0.5"><?= esc($contact['company']) ?></p>
            <?php endif; ?>

            <!-- Contact Info -->
            <div class="mt-4 space-y-2 text-left">
                <a href="https://wa.me/<?= esc($contact['phone_normalized']) ?>" target="_blank"
                   class="flex items-center gap-2 text-sm text-gray-600 hover:text-green-600 transition-colors">
                    <?= rx_icon('mobile', 'w-4 h-4') ?> <?= esc(format_phone($contact['phone'])) ?>
                </a>
                <?php if ($contact['email']): ?>
                <a href="mailto:<?= esc($contact['email']) ?>"
                   class="flex items-center gap-2 text-sm text-gray-600 hover:text-blue-600 transition-colors">
                    <?= rx_icon('mail', 'w-4 h-4') ?> <?= esc($contact['email']) ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Tags -->
            <?php if (!empty($tags)): ?>
            <div class="mt-4 flex flex-wrap gap-1 justify-center">
                <?php foreach ($tags as $tag): ?>
                <span class="text-xs px-2 py-0.5 rounded-full text-white" style="background-color: <?= esc($tag['color'] ?? '#3B82F6') ?>">
                    <?= esc($tag['name']) ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="mt-5 space-y-2">
                <a href="<?= base_url('contacts/' . $contact['id'] . '/edit') ?>"
                   class="block w-full py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 transition-colors">
                    Edit Contact
                </a>
                <?php if (has_min_role('admin')): ?>
                <form action="<?= base_url('contacts/' . $contact['id'] . '/delete') ?>" method="POST"
                      onsubmit="return confirm('Delete this contact? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full py-2 border border-red-300 text-red-600 text-sm rounded-lg hover:bg-red-50 transition-colors">
                        Delete Contact
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Stats</h3>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Conversations</span>
                <span class="font-medium"><?= $stats['total_conversations'] ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Open Deals</span>
                <span class="font-medium"><?= $stats['open_deals'] ?>
                    <?php if ($stats['open_deal_value'] > 0): ?>
                    <span class="text-xs text-gray-400">(₹<?= number_format($stats['open_deal_value']) ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Won Deals</span>
                <span class="font-medium text-green-600"><?= $stats['won_deals'] ?>
                    <?php if ($stats['won_deal_value'] > 0): ?>
                    <span class="text-xs">(₹<?= number_format($stats['won_deal_value']) ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">First Contact</span>
                <span class="font-medium text-xs"><?= date('d M Y', strtotime($stats['first_contact'])) ?></span>
            </div>
        </div>

        <!-- Custom Fields -->
        <?php if (!empty($customFields)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Custom Fields</h3>
            <div class="space-y-2">
                <?php foreach ($customFields as $field): ?>
                <div>
                    <div class="text-xs text-gray-400"><?= esc($field['field_name']) ?></div>
                    <div class="text-sm text-gray-700"><?= esc($field['field_value'] ?? '—') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Panel: Tabs -->
    <div class="flex-1 bg-white rounded-xl border border-gray-200 overflow-hidden">

        <!-- Tab Nav -->
        <div class="border-b border-gray-200 px-6 flex gap-6">
            <?php foreach (['timeline' => 'Timeline', 'notes' => 'Notes', 'deals' => 'Deals', 'conversations' => 'Conversations'] as $tab => $label): ?>
            <button @click="activeTab = '<?= $tab ?>'"
                    :class="activeTab === '<?= $tab ?>' ? 'border-blue-900 text-blue-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="py-4 text-sm font-medium border-b-2 transition-colors">
                <?= $label ?>
                <?php if ($tab === 'notes' && count($notes) > 0): ?>
                <span class="ml-1 text-xs bg-gray-100 rounded-full px-1.5"><?= count($notes) ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Tab Content -->
        <div class="p-6">

            <!-- Timeline -->
            <div x-show="activeTab === 'timeline'">
                <div class="relative pl-6 border-l-2 border-gray-200 space-y-6">
                    <!-- Contact Created -->
                    <div class="relative">
                        <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full bg-blue-900 border-2 border-white"></div>
                        <div class="text-sm font-medium text-gray-800">Contact created</div>
                        <div class="text-xs text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($contact['created_at'])) ?></div>
                    </div>

                    <?php foreach ($notes as $note): ?>
                    <div class="relative">
                        <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full bg-yellow-400 border-2 border-white"></div>
                        <div class="text-sm font-medium text-gray-800">Note added by <?= esc($note['author_name'] ?? 'Agent') ?></div>
                        <div class="text-xs text-gray-600 mt-0.5"><?= esc(substr($note['note_text'], 0, 80)) ?><?= strlen($note['note_text']) > 80 ? '...' : '' ?></div>
                        <div class="text-xs text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($note['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <?php foreach ($conversations as $conv): ?>
                    <div class="relative">
                        <div class="absolute -left-[1.6rem] w-4 h-4 rounded-full bg-green-500 border-2 border-white"></div>
                        <div class="text-sm font-medium text-gray-800">Conversation <?= esc($conv['status']) ?></div>
                        <div class="text-xs text-gray-400 mt-0.5"><?= date('d M Y, H:i', strtotime($conv['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($notes) && empty($conversations)): ?>
                    <p class="text-sm text-gray-400 pl-0">No activity yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notes -->
            <div x-show="activeTab === 'notes'" x-data="{
                noteText: '',
                async saveNote() {
                    if (!this.noteText.trim()) return;
                    const fd = new FormData();
                    fd.append('conversation_id', '');
                    fd.append('contact_id', '<?= esc($contact['id']) ?>');
                    fd.append('note_text', this.noteText);
                    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
                    const res = await fetch('<?= base_url('api/contacts/note') ?>', { method: 'POST', body: fd });
                    if (res.ok) { this.noteText = ''; location.reload(); }
                }
            }">
                <textarea x-model="noteText" rows="3" placeholder="Add a note..."
                          class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"></textarea>
                <button @click="saveNote()" class="px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 mb-6">Save Note</button>

                <div class="space-y-4">
                    <?php foreach ($notes as $note): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700"><?= esc($note['author_name'] ?? 'Agent') ?></span>
                            <span class="text-xs text-gray-400"><?= date('d M Y, H:i', strtotime($note['created_at'])) ?></span>
                        </div>
                        <p class="text-sm text-gray-600"><?= nl2br(esc($note['note_text'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($notes)): ?>
                    <p class="text-sm text-gray-400">No notes yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deals -->
            <div x-show="activeTab === 'deals'">
                <?php if (empty($deals)): ?>
                <p class="text-sm text-gray-400">No deals linked to this contact.</p>
                <?php endif; ?>
                <div class="space-y-3">
                    <?php foreach ($deals as $deal): ?>
                    <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900 text-sm"><?= esc($deal['title']) ?></div>
                            <div class="text-xs text-gray-500 mt-0.5">
                                <?php if ($deal['expected_close_date']): ?>
                                Close: <?= date('d M Y', strtotime($deal['expected_close_date'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-gray-900">₹<?= number_format($deal['value'] ?? 0) ?></div>
                            <span class="text-xs px-2 py-0.5 rounded-full
                                <?= $deal['status'] === 'won' ? 'bg-green-100 text-green-700' : ($deal['status'] === 'lost' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                                <?= ucfirst($deal['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Conversations -->
            <div x-show="activeTab === 'conversations'">
                <?php if (empty($conversations)): ?>
                <p class="text-sm text-gray-400">No conversations yet.</p>
                <?php endif; ?>
                <div class="space-y-3">
                    <?php foreach ($conversations as $conv): ?>
                    <a href="<?= base_url('inbox/conversation/' . $conv['id']) ?>"
                       class="flex items-center justify-between border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-600 truncate"><?= esc($conv['last_message_text'] ?? 'No messages yet') ?></div>
                            <div class="text-xs text-gray-400 mt-0.5"><?= $conv['last_message_at'] ? date('d M Y, H:i', strtotime($conv['last_message_at'])) : '' ?></div>
                        </div>
                        <div class="flex items-center gap-2 ml-3">
                            <?php if ($conv['unread_count'] > 0): ?>
                            <span class="bg-green-500 text-white text-xs rounded-full px-1.5 py-0.5"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                            <span class="text-xs px-2 py-0.5 rounded-full
                                <?= $conv['status'] === 'open' ? 'bg-green-100 text-green-700' : ($conv['status'] === 'closed' ? 'bg-gray-100 text-gray-600' : 'bg-yellow-100 text-yellow-700') ?>">
                                <?= ucfirst($conv['status']) ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<?= $this->endSection() ?>
