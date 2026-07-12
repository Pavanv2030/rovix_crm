<?php $this->extend('layouts/main'); ?>
<?php $this->section('content'); ?>

<div class="p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($pageTitle) ?></h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage your WhatsApp product catalog</p>
        </div>
        <div class="flex gap-2">
            <?php if (!empty($waConfig['catalog_id'])): ?>
            <a href="<?= base_url('catalog/orders') ?>"
               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                View Orders
            </a>
            <button onclick="syncCatalog()"
                    class="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600">
                Sync Now
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden CSRF token for JS -->
    <div style="display:none">
        <?= csrf_field() ?>
    </div>

    <?php if (empty($waConfig['catalog_id'])): ?>
    <!-- No catalog connected -->
    <div class="bg-white rounded-xl border border-gray-200 p-10 text-center" x-data="{ loading: false, catalogs: [], selected: '' }">
        <div class="w-14 h-14 rounded-2xl bg-amber-100 flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-amber-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-1">No Catalog Connected</h2>
        <p class="text-sm text-gray-500 mb-6">Fetch your catalogs from Meta and connect one to start sending products.</p>

        <div class="max-w-sm mx-auto space-y-3">
            <button @click="fetchCatalogs($data)" :disabled="loading"
                    class="w-full px-4 py-2.5 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600 disabled:opacity-50">
                <span x-show="!loading">Fetch Catalogs from Meta</span>
                <span x-show="loading" x-cloak>Fetching...</span>
            </button>

            <template x-if="catalogs.length > 0">
                <div class="space-y-2">
                    <select x-model="selected" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500">
                        <option value="">Select a catalog...</option>
                        <template x-for="c in catalogs" :key="c.id">
                            <option :value="c.id" x-text="`${c.name} (${c.id})`"></option>
                        </template>
                    </select>
                    <button @click="connectCatalog($data)" :disabled="!selected"
                            class="w-full px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-40">
                        Connect Catalog
                    </button>
                </div>
            </template>

            <!-- Manual entry -->
            <div class="relative flex items-center gap-2 pt-1">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="text-xs text-gray-400 flex-shrink-0">or enter manually</span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>
            <div class="flex gap-2">
                <input x-model="selected" type="text" placeholder="Catalog ID (e.g. 2249713862264121)"
                       class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 font-mono">
                <button @click="connectCatalog($data)" :disabled="!selected"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-40 flex-shrink-0">
                    Connect
                </button>
            </div>
        </div>

        <div class="mt-8 bg-amber-50 rounded-lg p-4 text-left text-sm text-amber-800 max-w-md mx-auto">
            <p class="font-semibold mb-1">How to set up your catalog:</p>
            <ol class="list-decimal list-inside space-y-1 text-amber-700">
                <li>Go to Meta Commerce Manager and create a catalog</li>
                <li>In WhatsApp Manager → Account Tools → Catalogue → Connect it</li>
                <li>Click "Fetch Catalogs from Meta" above and select your catalog</li>
            </ol>
        </div>
    </div>

    <?php else: ?>
    <!-- Catalog connected — show products -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-green-600">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-gray-900">Catalog connected: <code class="font-mono text-amber-700"><?= esc($waConfig['catalog_id']) ?></code></p>
            <p class="text-xs text-gray-500">Last synced: <?= $waConfig['catalog_synced_at'] ? date('d M Y H:i', strtotime($waConfig['catalog_synced_at'])) : 'Never' ?> &bull; <?= count($products) ?> products</p>
        </div>
        <button onclick="disconnectCatalog()" class="text-xs text-red-500 hover:text-red-700">Disconnect</button>
    </div>

    <?php if (!empty($productsError)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
        <p class="text-sm font-semibold text-red-700 mb-1">Failed to load products from Meta</p>
        <p class="text-xs text-red-600 font-mono"><?= esc($productsError) ?></p>
        <p class="text-xs text-red-500 mt-2">This usually means your access token is missing the <code>catalog_management</code> permission. Products still show to customers via WhatsApp — only the CRM listing is affected.</p>
    </div>
    <?php endif; ?>
    <?php if (empty($products)): ?>
    <div class="text-center py-12 text-gray-400">
        <p class="text-sm">No products found. Click "Sync Now" to refresh from Meta.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <?php foreach ($products as $product): ?>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <?php if (!empty($product['image_url'])): ?>
            <img src="<?= esc($product['image_url']) ?>" alt="<?= esc($product['name']) ?>" class="w-full h-36 object-cover">
            <?php else: ?>
            <div class="w-full h-36 bg-gray-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                </svg>
            </div>
            <?php endif; ?>
            <div class="p-3">
                <p class="text-xs font-semibold text-gray-900 truncate"><?= esc($product['name']) ?></p>
                <p class="text-xs text-gray-400 truncate mt-0.5"><?= esc($product['retailer_id'] ?? $product['id']) ?></p>
                <?php if (!empty($product['price'])): ?>
                <p class="text-sm font-bold text-amber-600 mt-1"><?= esc($product['price']) ?></p>
                <?php endif; ?>
                <span class="inline-block mt-1.5 text-xs px-1.5 py-0.5 rounded <?= ($product['availability'] ?? '') === 'in stock' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600' ?>">
                    <?= esc($product['availability'] ?? 'unknown') ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
async function fetchCatalogs(data) {
    data.loading = true;
    try {
        const res  = await fetch('<?= base_url('api/catalog/fetch-catalogs') ?>');
        const json = await res.json();
        if (json.error) { alert(json.error); return; }
        data.catalogs = json.catalogs || [];
        if (!data.catalogs.length) alert('No catalogs found. Make sure your WABA has a connected catalog in Meta Commerce Manager.');
    } finally {
        data.loading = false;
    }
}

function csrfForm(extra = {}) {
    const token = document.querySelector('input[name="csrf_test_name"]').value;
    const form = new FormData();
    form.append('csrf_test_name', token);
    for (const [k, v] of Object.entries(extra)) form.append(k, v);
    return form;
}

async function connectCatalog(data) {
    if (!data.selected) return;
    const form = csrfForm({ catalog_id: data.selected });
    const res  = await fetch('<?= base_url('catalog/connect') ?>', {
        method: 'POST',
        body: form
    });
    const json = await res.json();
    if (json.success) {
        alert('Connected! Found ' + json.product_count + ' products.');
        location.reload();
    } else {
        alert('Error: ' + json.error);
    }
}

async function syncCatalog() {
    try {
        const form = csrfForm();
        const res  = await fetch('<?= base_url('catalog/sync') ?>', {
            method: 'POST',
            body: form
        });
        const json = await res.json();
        if (json.success) {
            alert('Synced! ' + json.product_count + ' products.');
            location.reload();
        } else {
            alert('Error: ' + (json.error || 'Sync failed'));
        }
    } catch (err) {
        alert('Error: ' + (err.message || 'Network or server error'));
    }
}

function disconnectCatalog() {
    if (!confirm('Disconnect catalog? This will not delete orders.')) return;
    const form = csrfForm();
    fetch('<?= base_url('catalog/disconnect') ?>', {
        method: 'POST',
        body: form
    })
        .then(r => r.json())
        .then(json => {
            if (json.success) location.reload();
            else alert('Error: ' + (json.error || 'Failed to disconnect'));
        })
        .catch(() => alert('Network error'));
}
</script>

<?php $this->endSection(); ?>
