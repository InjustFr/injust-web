<script setup>
import { Disc3, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { moveId } from '../../forms.js';
import { imageUrl } from '../../settingsForm.js';
import OrderButtons from './OrderButtons.vue';
import ReleaseModal from './ReleaseModal.vue';

const props = defineProps({ releases: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const editing = ref(null);
const open = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/music/releases/order', { ids: moveId(props.releases.map((item) => item.id), id, delta) }));

function edit(id) {
    editing.value = id;
    open.value = true;
}

const feature = (release) => toast.attempt(() => api.put(`/api/admin/music/releases/${release.id}/featured`, { featured: !release.featured }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/music/releases/${id}`), t('admin.music.saved'));
</script>

<template>
    <PageSection :title="t('admin.music.tabs.releases')" :count="releases.length">
        <template #actions>
            <BaseButton @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.music.releases.new') }}</BaseButton>
        </template>
        <EmptyState :icon="Disc3" v-if="releases.length === 0" :title="t('admin.music.releases.empty')" />
        <ul v-else class="rows">
            <li v-for="(release, index) in releases" :key="release.id" class="rows__item">
                <img v-if="release.cover" class="rows__thumb" :src="imageUrl(release.cover)" alt="" />
                <span v-else class="rows__thumb rows__thumb--empty" />
                <span class="rows__label">
                    {{ release.title }}
                    <span class="rows__sub">{{ t(`admin.music.releases.types.${release.type}`) }} · {{ release.releaseDate }}<template v-if="release.upcoming"> · {{ t('admin.music.releases.upcoming') }}</template></span>
                </span>
                <IconButton :icon="Star" :label="t('admin.music.releases.featured')" :pressed="release.featured" @click="feature(release)" />
                <OrderButtons :first="index === 0" :last="index === releases.length - 1" @move="(delta) => reorder(release.id, delta)" />
                <IconButton :icon="Pencil" :label="t('admin.music.edit')" @click="edit(release.id)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.music.delete')" @confirm="remove(release.id)" />
            </li>
        </ul>
        <ReleaseModal v-model:open="open" :release="releases.find((release) => release.id === editing) ?? null" @saved="editing = $event" />
    </PageSection>
</template>
