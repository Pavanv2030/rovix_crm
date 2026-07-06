<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<script>
window.__pipelines = <?= json_encode($pipelines, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= base_url('pipelines') ?>" class="text-gray-400 hover:text-gray-600">← Pipelines</a>
        <h1 class="text-xl font-bold text-gray-900">New Deal</h1>
    </div>

    <form action="<?= base_url('deals') ?>" method="POST"
          x-data="{
              selectedPipeline: '<?= esc($selectedPipelineId ?? '') ?>',
              stages: [],
              init() {
                  if (this.selectedPipeline) this.loadStages();
              },
              loadStages() {
                  const pipeline = window.__pipelines.find(p => p.id === this.selectedPipeline);
                  this.stages = pipeline ? pipeline.stages : [];
              }
          }">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= esc($redirectTo ?? base_url('pipelines')) ?>">

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Deal Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="<?= old('title') ?>" required placeholder="e.g. Acme Corp - Premium Plan"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pipeline <span class="text-red-500">*</span></label>
                    <select name="pipeline_id" x-model="selectedPipeline" @change="loadStages()" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select pipeline...</option>
                        <?php foreach ($pipelines as $p): ?>
                        <option value="<?= esc($p['id']) ?>" <?= ($selectedPipelineId ?? '') === $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stage <span class="text-red-500">*</span></label>
                    <select name="stage_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select stage...</option>
                        <template x-for="stage in stages" :key="stage.id">
                            <option :value="stage.id"
                                    :selected="stage.id === '<?= esc($selectedStageId ?? '') ?>'"
                                    x-text="stage.name"></option>
                        </template>
                        <?php if (!empty($selectedStageId)): ?>
                        <option value="<?= esc($selectedStageId) ?>" selected style="display:none">Selected Stage</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Value</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">₹</span>
                        <input type="number" name="value" value="<?= old('value', '0') ?>" min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expected Close Date</label>
                    <input type="date" name="expected_close_date" value="<?= old('expected_close_date') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contact</label>
                <select name="contact_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No contact linked</option>
                    <?php foreach ($contacts as $c): ?>
                    <option value="<?= esc($c['id']) ?>"><?= esc($c['name'] ?? $c['phone']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Assigned To</label>
                <select name="assigned_agent_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Unassigned</option>
                    <?php foreach ($agents as $agent): ?>
                    <option value="<?= esc($agent['user_id']) ?>"><?= esc($agent['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="3" placeholder="Any notes about this deal..."
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><?= old('notes') ?></textarea>
            </div>
        </div>

        <div class="flex gap-3 mt-4">
            <button type="submit" class="px-6 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
                Create Deal
            </button>
            <a href="javascript:history.back()" class="px-6 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
