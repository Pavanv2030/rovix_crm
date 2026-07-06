<?php $this->extend('layouts/main'); ?>
<?php $this->section('content'); ?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900"><?= esc($pageTitle) ?></h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage your catalog orders</p>
        </div>
        <a href="<?= base_url('catalog') ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            View Catalog
        </a>
    </div>

    <!-- Filter bar -->
    <form method="get" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Catalog</label>
            <select name="catalog_id" class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-500">
                <option value="">Any Catalog</option>
                <?php foreach ($catalogs as $c): ?>
                <option value="<?= esc($c['catalog_id']) ?>" <?= ($currentFilters['catalog_id'] ?? '') === $c['catalog_id'] ? 'selected' : '' ?>>
                    <?= esc($c['catalog_id']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-500">
                <option value="">Any Status</option>
                <?php foreach ($statusOptions as $s): ?>
                <option value="<?= esc($s) ?>" <?= ($currentFilters['status'] ?? '') === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <input type="text" name="q" value="<?= esc($currentFilters['q'] ?? '') ?>"
                   placeholder="Name or phone..."
                   class="px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-amber-500 w-44">
        </div>
        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-amber-500 rounded-lg hover:bg-amber-600">
            Filter
        </button>
        <?php if (array_filter($currentFilters ?? [])): ?>
        <a href="<?= base_url('catalog/orders') ?>" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
            Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- Orders table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <?php if (empty($orders)): ?>
        <div class="text-center py-16 text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 mx-auto mb-3 opacity-40">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
            <p class="text-sm">No orders yet. Orders appear when customers purchase through WhatsApp catalog.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Order ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Buyer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Currency</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Ordered At</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Items</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($orders as $i => $order): ?>
                    <tr class="hover:bg-gray-50 transition-colors" x-data="{ expanded: false }">
                        <td class="px-4 py-3 text-gray-400"><?= $i + 1 ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-600"><?= esc(substr($order['wa_order_id'] ?? $order['id'], 0, 16)) ?></td>
                        <td class="px-4 py-3 font-medium text-gray-900"><?= esc($order['contact_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= esc($order['phone'] ?? '—') ?></td>
                        <td class="px-4 py-3 font-semibold text-gray-900"><?= number_format((float)($order['total_price'] ?? 0), 2) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= esc($order['currency'] ?? 'USD') ?></td>
                        <td class="px-4 py-3">
                            <select onchange="updateOrderStatus('<?= esc($order['id']) ?>', this.value)"
                                    class="text-xs px-2 py-1 rounded-lg border <?= match($order['status']) {
                                        'new'        => 'border-blue-200 bg-blue-50 text-blue-700',
                                        'confirmed'  => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                                        'processing' => 'border-amber-200 bg-amber-50 text-amber-700',
                                        'shipped'    => 'border-purple-200 bg-purple-50 text-purple-700',
                                        'delivered'  => 'border-green-200 bg-green-50 text-green-700',
                                        'cancelled'  => 'border-red-200 bg-red-50 text-red-700',
                                        default      => 'border-gray-200 bg-gray-50 text-gray-700',
                                    } ?>">
                                <?php foreach ($statusOptions as $s): ?>
                                <option value="<?= esc($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs"><?= $order['created_at'] ? date('d M Y H:i', strtotime($order['created_at'])) : '—' ?></td>
                        <td class="px-4 py-3">
                            <button @click="expanded = !expanded" class="text-xs text-amber-600 hover:text-amber-800 font-medium">
                                <span x-show="!expanded"><?= count($order['order_items']) ?> item(s)</span>
                                <span x-show="expanded" x-cloak>Hide</span>
                            </button>
                        </td>
                    </tr>
                    <tr x-show="expanded" x-cloak class="bg-amber-50">
                        <td colspan="9" class="px-6 py-3">
                            <div class="text-xs text-gray-700 space-y-1">
                                <?php foreach ($order['order_items'] as $item): ?>
                                <div class="flex gap-4">
                                    <span class="font-medium"><?= esc($item['product_retailer_id'] ?? '?') ?></span>
                                    <span>qty: <?= (int)($item['quantity'] ?? 1) ?></span>
                                    <span>price: <?= esc($item['item_price'] ?? 0) ?> <?= esc($item['currency'] ?? '') ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php if (!empty($order['customer_note'])): ?>
                                <div class="text-gray-500 italic mt-1">Note: <?= esc($order['customer_note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function updateOrderStatus(orderId, status) {
    const form = new FormData();
    form.append('status', status);
    const res  = await fetch(`<?= base_url('catalog/orders') ?>/${orderId}/status`, { method: 'POST', body: form });
    const json = await res.json();
    if (!json.success) alert('Update failed: ' + json.error);
}
</script>

<?php $this->endSection(); ?>
