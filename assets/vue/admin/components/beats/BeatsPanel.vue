<script setup>
import { AudioLines, ChevronDown, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseSwitch from '../../../components/ui/BaseSwitch.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { moveId } from '../../forms.js';
import { imageUrl } from '../../settingsForm.js';
import { useImageFocus } from '../../../composables/useImageFraming.js';
import { beatForm, beatPayload, fileChips, keyLabel } from '../../beatsForm.js';
import OrderButtons from '../music/OrderButtons.vue';
import BeatModal from './BeatModal.vue';
import FilesPanel from './FilesPanel.vue';

const props = defineProps({ beats: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const focus = useImageFocus();
const editing = ref(null);
const open = ref(false);
const expanded = ref(new Set());
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/beats/order', { ids: moveId(props.beats.map((beat) => beat.id), id, delta) }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/beats/${id}`), t('admin.beats.saved'));
const show = (beat, visible) => toast.attempt(() => api.put(`/api/admin/beats/${beat.id}`, beatPayload({ ...beatForm(beat), visible })));

function edit(id) {
    editing.value = id;
    open.value = true;
}

function toggleFiles(id) {
    const next = new Set(expanded.value);
    if (!next.delete(id)) next.add(id);
    expanded.value = next;
}
</script>

<template>
    <PageSection :title="t('admin.beats.beats.title')" :count="beats.length">
        <template #actions>
            <BaseButton @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.beats.beats.new') }}</BaseButton>
        </template>
        <EmptyState v-if="beats.length === 0" :icon="AudioLines" :title="t('admin.beats.beats.empty')" />
        <ul v-else class="rows">
            <li v-for="(beat, index) in beats" :key="beat.id" class="beat-row">
                <div class="rows__item beat-row__main">
                    <img v-if="beat.cover" class="rows__thumb" :src="imageUrl(beat.cover)" :style="{ objectPosition: focus.position(beat.cover) }" alt="" />
                    <span v-else class="rows__thumb rows__thumb--empty" />
                    <span class="rows__label">
                        {{ beat.title }}
                        <span class="rows__sub">{{ beat.bpm }} BPM · {{ keyLabel(beat.key) }}<template v-if="beat.tags.length > 0"> · {{ beat.tags.join(', ') }}</template></span>
                    </span>
                    <span class="chips">
                        <span v-for="chip in fileChips(beat)" :key="chip.slot" class="chip" :class="{ 'chip--on': chip.present }">{{ t(`admin.beats.slots.${chip.slot}`) }}<span v-if="!chip.present" class="visually-hidden"> ({{ t('admin.beats.files.empty') }})</span></span>
                        <span v-if="beat.soldExclusivelyAt" class="chip chip--sold">{{ t('admin.beats.beats.sold') }}</span>
                        <span v-else-if="beat.visible && !beat.listed" class="chip chip--warn">{{ t('admin.beats.beats.notListed') }}</span>
                    </span>
                    <BaseSwitch :model-value="beat.visible" :aria-label="t('admin.beats.beats.visibleSwitch', { title: beat.title })" @update:model-value="(visible) => show(beat, visible)" />
                    <OrderButtons :first="index === 0" :last="index === beats.length - 1" @move="(delta) => reorder(beat.id, delta)" />
                    <IconButton :icon="ChevronDown" :label="expanded.has(beat.id) ? t('admin.beats.beats.hideFiles') : t('admin.beats.beats.showFiles')" :pressed="expanded.has(beat.id)" @click="toggleFiles(beat.id)" />
                    <IconButton :icon="Pencil" :label="t('admin.beats.edit')" @click="edit(beat.id)" />
                    <ConfirmButton :icon="Trash2" :label="t('admin.beats.delete')" @confirm="remove(beat.id)" />
                </div>
                <FilesPanel v-show="expanded.has(beat.id)" :beat="beat" />
            </li>
        </ul>
        <BeatModal v-model:open="open" :beat="beats.find((beat) => beat.id === editing) ?? null" />
    </PageSection>
</template>

<style scoped>
.beat-row { border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.beat-row__main { border: 0; flex-wrap: wrap; }
.chips { display: flex; flex-wrap: wrap; gap: var(--space-1); }
.chip { padding: 0 var(--space-2); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 600; text-decoration: line-through; }
.chip--on { border-color: var(--color-border-strong); color: var(--color-ink); text-decoration: none; }
.chip--sold { border-color: var(--color-ink); color: var(--color-ink); text-decoration: none; }
.chip--warn { color: var(--color-muted); text-decoration: none; }
</style>
