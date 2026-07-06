<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= base_url('pipelines') ?>" class="text-gray-400 hover:text-gray-600">← Pipelines</a>
        <h1 class="text-xl font-bold text-gray-900">New Pipeline</h1>
    </div>

    <form action="<?= base_url('pipelines') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Pipeline Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="<?= old('name') ?>" required placeholder="e.g. Sales Pipeline"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Stages Builder -->
        <script>window.__defaultStages = <?= json_encode($defaultStages, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
        <div class="bg-white rounded-xl border border-gray-200 p-6" x-data="{
            stages: window.__defaultStages || [],
            colors: ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#14B8A6','#F97316','#6366F1','#84CC16'],
            addStage() { this.stages.push({ name: '', color: '#3B82F6' }); },
            removeStage(idx) { if (this.stages.length > 1) this.stages.splice(idx, 1); }
        }">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Stages</h2>
                <button type="button" @click="addStage()"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Stage</button>
            </div>

            <div class="space-y-3">
                <template x-for="(stage, idx) in stages" :key="idx">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full flex-shrink-0" :style="'background-color:' + stage.color"></div>
                        <input type="text" :name="'stage_names[' + idx + ']'" x-model="stage.name"
                               placeholder="Stage name" required
                               class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

                        <!-- Color picker dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open"
                                    class="w-8 h-8 rounded-lg border border-gray-300 hover:border-gray-400"
                                    :style="'background-color:' + stage.color"></button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 class="absolute right-0 mt-1 p-2 bg-white rounded-lg shadow-lg border border-gray-100 z-20">
                                <div class="grid grid-cols-5 gap-1">
                                    <template x-for="color in colors" :key="color">
                                        <button type="button" @click="stage.color = color; open = false"
                                                :style="'background-color:' + color"
                                                :class="stage.color === color ? 'ring-2 ring-offset-1 ring-blue-900' : ''"
                                                class="w-6 h-6 rounded-full"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" :name="'stage_colors[' + idx + ']'" :value="stage.color">

                        <button type="button" @click="removeStage(idx)"
                                class="text-gray-400 hover:text-red-500 transition-colors text-lg leading-none">×</button>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex gap-3 mt-4">
            <button type="submit" class="px-6 py-2 bg-blue-900 text-white text-sm rounded-lg hover:bg-blue-800 font-medium">
                Create Pipeline
            </button>
            <a href="<?= base_url('pipelines') ?>" class="px-6 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                Cancel
            </a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
