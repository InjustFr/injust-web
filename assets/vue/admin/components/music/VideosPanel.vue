<script setup>
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import FormField from '../../../components/ui/FormField.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { moveId } from '../../musicForm.js';
import { imageUrl } from '../../settingsForm.js';
import OrderButtons from './OrderButtons.vue';

const props = defineProps({ videos: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = ref({ url: '', title: '' });
const errors = ref({});
const saving = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/music/videos/order', { ids: moveId(props.videos.map((item) => item.id), id, delta) }));

async function add() {
    errors.value = {};
    saving.value = true;
    const done = await toast.attempt(async () => {
        try {
            await api.post('/api/admin/music/videos', { url: form.value.url.trim(), title: form.value.title.trim() });
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.music.saved'));
    if (done) form.value = { url: '', title: '' };
    saving.value = false;
}

const remove = (id) => toast.attempt(() => api.del(`/api/admin/music/videos/${id}`), t('admin.music.saved'));
</script>

<template>
    <PageSection :title="t('admin.music.tabs.videos')" :count="videos.length">
        <form class="row-form" @submit.prevent="add">
            <FormField :label="t('admin.music.videos.url')" :error="errors.url ?? null">
                <input v-model="form.url" type="text" name="videoUrl" required />
            </FormField>
            <FormField :label="t('admin.music.videos.title')" :error="errors.title ?? null">
                <input v-model="form.title" type="text" name="videoTitle" required maxlength="200" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.music.add') }}</BaseButton>
        </form>
        <EmptyState v-if="videos.length === 0" :title="t('admin.music.videos.empty')" />
        <ul v-else class="rows">
            <li v-for="(video, index) in videos" :key="video.id" class="rows__item">
                <img v-if="video.thumbnail" class="rows__thumb" :src="imageUrl(video.thumbnail)" alt="" />
                <span v-else class="rows__thumb rows__thumb--empty" />
                <span class="rows__label">{{ video.title }}</span>
                <OrderButtons :first="index === 0" :last="index === videos.length - 1" @move="(delta) => reorder(video.id, delta)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.music.delete')" @confirm="remove(video.id)" />
            </li>
        </ul>
    </PageSection>
</template>

<style>
.row-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: var(--space-3); align-items: end; }
.rows { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.rows__item { display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2) var(--space-3); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.rows__label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rows__sub { color: var(--color-muted); font-size: var(--font-size-sm); }
.rows__thumb { width: 4rem; height: 2.5rem; object-fit: cover; border-radius: var(--radius-inner); background: var(--color-hover); flex-shrink: 0; }
</style>
