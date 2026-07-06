<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= base_url('pipelines') ?>" class="text-gray-400 hover:text-gray-600">← Pipelines</a>
        <h1 class="text-xl font-bold text-gray-900">Edit Pipeline</h1>
    </div>

    <form action="<?= base_url('pipelines/' . $pipeline['id']) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Pipeline Name</label>
            <input type="text" name="name" value="<?= esc($pipeline['name']) ?>" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Existing Stages -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4" x-data="{
            newStages: [],
            colors: ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#14B8A6','#F97316','#6366F1','#84CC16'],
            addStage() { this.newStages.push({ name: '', color: '#3B82F6' }); }
        }">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Existing Stages</h2>
                <button type="button" @click="addStage()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Stage</button>
            </div>

            <!-- Existing stages -->
            <div class="space-y-3 mb-4">
                <?php foreach ($stages as $i => $stage): ?>
                <div class="flex items-center gap-3">
                    <input type="hidden" name="stage_ids[<?= $i ?>]" value="<?= esc($stage['id']) ?>">
                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: <?= esc($stage['color']) ?>"></div>
                    <input type="text" name="stage_names[<?= $i ?>]" value="<?= esc($stage['name']) ?>"
                           class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="color" name="stage_colors[<?= $i ?>]" value="<?= esc($stage['color']) ?>"
                           class="w-8 h-8 rounded border border-gray-300 cursor-pointer">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- New stages (added dynamically) -->
            <div class="space-y-3">
                <template x-for="(stage, idx) in newStages" :key="idx">
                    <div class="flex items-center gap-3 bg-blue-50 rounded-lg p-2">
                        <span class="text-xs text-blue-600 font-medium w-8">New</span>
                        <input type="text" :name="'new_stage_names[' + idx + ']'" x-model="stage.name"
                               placeholder="Stage name"
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <input type="color" :name="'new_stage_colors[' + idx + ']'" x-model="stage.color"
                               class="w-8 h-8 rounded border border-gray-300 cursor-pointer">
                        <button type="button" @click="newStages.splice(idx, 1)" class="text-gray-400 hover:text-red-500 text-lg">×</button>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
                Update Pipeline
            </button>
            <a href="<?= base_url('pipelines/' . $pipeline['id'] . '/board') ?>"
               class="px-6 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
