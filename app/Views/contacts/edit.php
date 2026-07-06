<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= base_url('contacts/' . $contact['id']) ?>" class="text-gray-400 hover:text-gray-600">← Contact</a>
        <h1 class="text-xl font-bold text-gray-900">Edit Contact</h1>
    </div>

    <form action="<?= base_url('contacts/' . $contact['id'] . '/update') ?>" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Basic Info</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                <input type="text" name="phone" value="<?= esc(old('phone', $contact['phone'])) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="<?= esc(old('name', $contact['name'])) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= esc(old('email', $contact['email'])) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                    <input type="text" name="company" value="<?= esc(old('company', $contact['company'])) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                    <select name="channel" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select channel...</option>
                        <?php foreach (['Cold Call','WhatsApp','Referral','Walk-in','Website','Social Media','Email','Exhibition','Other'] as $ch): ?>
                        <option value="<?= $ch ?>" <?= old('channel', $contact['channel'] ?? '') === $ch ? 'selected' : '' ?>><?= $ch ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vertical</label>
                    <input type="text" name="vertical" value="<?= esc(old('vertical', $contact['vertical'] ?? '')) ?>"
                           placeholder="e.g. Interior Design, IT, Real Estate"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach (['New','Active','Follow-up','Lost'] as $st): ?>
                        <option value="<?= $st ?>" <?= old('status', $contact['status'] ?? 'New') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Rep</label>
                    <select name="assigned_agent_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Unassigned</option>
                        <?php foreach ($agents ?? [] as $agent): ?>
                        <option value="<?= esc($agent['user_id']) ?>"
                                <?= old('assigned_agent_id', $contact['assigned_agent_id'] ?? '') === $agent['user_id'] ? 'selected' : '' ?>>
                            <?= esc($agent['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Follow-up Date</label>
                    <input type="date" name="follow_up_date" value="<?= esc(old('follow_up_date', $contact['follow_up_date'] ?? '')) ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

        </div>

        <!-- Tags -->
        <?php if (!empty($allTags)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Tags</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($allTags as $tag): ?>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="tag_ids[]" value="<?= esc($tag['id']) ?>"
                           <?= in_array($tag['id'], $selectedTagIds) ? 'checked' : '' ?> class="rounded">
                    <span class="text-sm px-2 py-0.5 rounded-full text-white" style="background-color: <?= esc($tag['color'] ?? '#3B82F6') ?>">
                        <?= esc($tag['name']) ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Custom Fields -->
        <?php if (!empty($customFields)): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Custom Fields</h2>
            <?php foreach ($customFields as $field): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= esc($field['field_name']) ?></label>
                <?php if ($field['field_type'] === 'dropdown'): ?>
                    <?php $opts = json_decode($field['field_options'] ?? '[]', true); ?>
                    <select name="custom_fields[<?= esc($field['id']) ?>]"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select...</option>
                        <?php foreach ($opts as $opt): ?>
                        <option <?= $field['field_value'] === $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field['field_type'] === 'date'): ?>
                    <input type="date" name="custom_fields[<?= esc($field['id']) ?>]" value="<?= esc($field['field_value'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php elseif ($field['field_type'] === 'number'): ?>
                    <input type="number" name="custom_fields[<?= esc($field['id']) ?>]" value="<?= esc($field['field_value'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php else: ?>
                    <input type="text" name="custom_fields[<?= esc($field['id']) ?>]" value="<?= esc($field['field_value'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">Update Contact</button>
            <a href="<?= base_url('contacts/' . $contact['id']) ?>" class="px-6 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
