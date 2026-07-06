<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= base_url('contacts') ?>" class="text-gray-400 hover:text-gray-600">← Contacts</a>
        <h1 class="text-xl font-bold text-gray-900">Import Contacts</h1>
    </div>

    <!-- Step indicator -->
    <div class="flex items-center gap-2 mb-8">
        <?php $steps = ['Upload CSV', 'Map Columns', 'Confirm', 'Results']; ?>
        <?php foreach ($steps as $i => $label): ?>
        <div class="flex items-center <?= $i > 0 ? 'flex-1' : '' ?>">
            <?php if ($i > 0): ?>
            <div class="flex-1 h-px <?= $step > $i ? 'bg-blue-900' : 'bg-gray-200' ?>"></div>
            <?php endif; ?>
            <div class="flex items-center gap-1.5">
                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                    <?= $step === $i + 1 ? 'bg-blue-900 text-white' : ($step > $i + 1 ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500') ?>">
                    <?= $step > $i + 1 ? rx_icon('check', 'w-3.5 h-3.5', '!text-white') : ($i + 1) ?>
                </div>
                <span class="text-xs font-medium <?= $step === $i + 1 ? 'text-blue-900' : 'text-gray-400' ?>"><?= $label ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($step === 1): ?>
    <!-- Step 1: Upload -->
    <div class="bg-white rounded-xl border border-gray-200 p-8">
        <h2 class="text-lg font-semibold mb-2">Upload CSV File</h2>
        <p class="text-sm text-gray-500 mb-6">Upload a CSV file with your contacts. Max 10MB.</p>

        <div class="bg-blue-50 rounded-lg p-4 mb-6 text-sm">
            <p class="font-medium text-blue-800 mb-2">CSV Format:</p>
            <ul class="text-blue-700 space-y-1">
                <li><?= rx_icon('check', 'w-4 h-4') ?> <strong>phone</strong> (required) — include country code, e.g. 919876543210</li>
                <li><?= rx_icon('check', 'w-4 h-4') ?> <strong>name</strong> — contact name</li>
                <li><?= rx_icon('check', 'w-4 h-4') ?> <strong>email</strong> — email address</li>
                <li><?= rx_icon('check', 'w-4 h-4') ?> <strong>company</strong> — company name</li>
                <li><?= rx_icon('check', 'w-4 h-4') ?> <strong>tags</strong> — comma-separated, e.g. "Hot Lead,VIP"</li>
            </ul>
            <p class="mt-2 text-blue-600 text-xs">
                <a href="#" onclick="downloadSample(); return false;" class="underline">Download sample CSV</a>
            </p>
        </div>

        <form action="<?= base_url('contacts/import/process') ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors"
                 x-data="{ filename: '' }"
                 @dragover.prevent
                 @drop.prevent="filename = $event.dataTransfer.files[0]?.name">
                <div class="mb-3"><?= rx_icon('document', 'w-12 h-12', 'mx-auto') ?></div>
                <p class="text-gray-600 text-sm mb-3">Drag & drop your CSV file here, or click to browse</p>
                <input type="file" name="csv_file" accept=".csv" required
                       x-ref="fileInput"
                       @change="filename = $event.target.files[0]?.name"
                       class="hidden" id="csv-input">
                <label for="csv-input" class="cursor-pointer px-4 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800">
                    Choose File
                </label>
                <p x-show="filename" class="mt-2 text-sm text-green-600 font-medium" x-text="'Selected: ' + filename"></p>
            </div>
            <button type="submit" class="mt-4 w-full py-2.5 bg-blue-900 text-white rounded-lg hover:bg-blue-800 font-medium">
                Upload & Preview
            </button>
        </form>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- Step 2: Map Columns -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold mb-1">Map CSV Columns</h2>
        <p class="text-sm text-gray-500 mb-4">Tell us which CSV column corresponds to which contact field.</p>

        <!-- Preview table -->
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50">
                        <?php foreach ($headers as $h): ?>
                        <th class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-600"><?= esc($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <?php foreach ($row as $cell): ?>
                        <td class="border border-gray-200 px-3 py-1.5 text-gray-600"><?= esc(substr($cell, 0, 30)) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form action="<?= base_url('contacts/import/confirm') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="space-y-3 mb-6">
                <h3 class="text-sm font-semibold text-gray-700">Column Mapping</h3>
                <?php
                $contactFields = ['skip' => '-- Skip --', 'phone' => 'Phone', 'name' => 'Name', 'email' => 'Email', 'company' => 'Company', 'tags' => 'Tags'];
                foreach ($customFields as $cf) {
                    $contactFields['cf_' . $cf['id']] = $cf['field_name'];
                }
                ?>
                <?php foreach ($headers as $h): ?>
                <div class="flex items-center gap-4">
                    <div class="w-40 text-sm text-gray-600 font-medium"><?= esc($h) ?></div>
                    <div class="text-gray-400">→</div>
                    <select name="mapping[<?= esc($h) ?>]" class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($contactFields as $val => $label): ?>
                        <option value="<?= esc($val) ?>" <?= strtolower($h) === strtolower($label) || strtolower($h) === $val ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="border border-gray-200 rounded-lg p-4 mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Duplicate Handling</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="duplicate_mode" value="update" checked class="text-blue-900">
                        Update existing contacts (recommended)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="duplicate_mode" value="skip">
                        Skip duplicates
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 bg-blue-900 text-white rounded-lg hover:bg-blue-800 font-medium">
                Import Contacts
            </button>
        </form>
    </div>

    <?php elseif ($step === 4): ?>
    <!-- Step 4: Results -->
    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <div class="mb-4"><?= rx_icon('check-circle', 'w-12 h-12', 'mx-auto') ?></div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Import Complete</h2>

        <div class="grid grid-cols-4 gap-4 my-6">
            <div class="bg-green-50 rounded-lg p-4">
                <div class="text-2xl font-bold text-green-700"><?= $created ?></div>
                <div class="text-xs text-green-600">Created</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="text-2xl font-bold text-blue-700"><?= $updated ?></div>
                <div class="text-xs text-blue-600">Updated</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="text-2xl font-bold text-gray-700"><?= $skipped ?></div>
                <div class="text-xs text-gray-500">Skipped</div>
            </div>
            <div class="bg-red-50 rounded-lg p-4">
                <div class="text-2xl font-bold text-red-700"><?= $errorCount ?></div>
                <div class="text-xs text-red-600">Errors</div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="text-left bg-red-50 rounded-lg p-4 mb-4 max-h-40 overflow-y-auto">
            <p class="text-sm font-semibold text-red-700 mb-2">Errors:</p>
            <?php foreach ($errors as $err): ?>
            <p class="text-xs text-red-600"><?= esc($err) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a href="<?= base_url('contacts') ?>" class="inline-block px-6 py-2.5 bg-blue-900 text-white rounded-lg hover:bg-blue-800">
            Go to Contacts
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function downloadSample() {
    const csv = 'phone,name,email,company,tags\n919876543210,John Doe,john@example.com,Acme Corp,"Hot Lead,VIP"\n919876543211,Jane Smith,jane@example.com,,"Regular"';
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contacts_sample.csv';
    a.click();
}
</script>
<?= $this->endSection() ?>
