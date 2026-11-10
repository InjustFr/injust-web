<script setup>
import { Plus, Trash2 } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import BaseSelect from '../../../components/ui/BaseSelect.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import FormField from '../../../components/ui/FormField.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { imageUpload, imageUrl } from '../../settingsForm.js';
import { PLATFORMS, RELEASE_TYPES, parseTracks, releaseForm, releasePayload } from '../../musicForm.js';

const props = defineProps({ release: { type: Object, default: null } });
const emit = defineEmits(['saved']);
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const form = reactive(releaseForm(null));
const errors = ref({});
const saving = ref(false);
const paste = ref('');
const LANGUAGES = ['fr', 'en'];
const types = RELEASE_TYPES.map((value) => ({ value, label: t(`admin.music.releases.types.${value}`) }));
const platforms = PLATFORMS.map((value) => ({ value, label: t(`admin.music.platforms.${value}`) }));

watch(open, (isOpen) => {
    if (!isOpen) return;
    errors.value = {};
    paste.value = '';
    Object.assign(form, releaseForm(props.release));
});

function fillTracks() {
    form.tracks = parseTracks(paste.value);
    paste.value = '';
}

async function save() {
    errors.value = {};
    saving.value = true;
    const url = props.release ? `/api/admin/music/releases/${props.release.id}` : '/api/admin/music/releases';
    const done = await toast.attempt(async () => {
        try {
            const saved = await (props.release ? api.put(url, releasePayload(form)) : api.post(url, releasePayload(form)));
            emit('saved', saved?.id ?? null);
        } catch (error) {
            errors.value = error.fieldErrors ?? {};
            throw error;
        }
    }, t('admin.music.saved'));
    saving.value = false;
    if (done) open.value = false;
}

function upload(event) {
    const body = imageUpload(event.target.files);
    event.target.value = '';
    if (body) toast.attempt(() => api.post(`/api/admin/music/releases/${props.release.id}/cover`, body), t('admin.music.saved'));
}

const removeCover = () => toast.attempt(() => api.del(`/api/admin/music/releases/${props.release.id}/cover`), t('admin.music.saved'));
</script>

<template>
    <BaseModal v-model:open="open" :title="release ? t('admin.music.releases.edit') : t('admin.music.releases.new')">
        <form class="release" @submit.prevent="save">
            <FormField :label="t('admin.music.releases.titleField')" :error="errors.title ?? null">
                <input v-model="form.title" type="text" name="title" required maxlength="200" />
            </FormField>
            <FormField :label="t('admin.music.releases.slug')" :hint="t('admin.music.releases.slugHint')" :error="errors.slug ?? null">
                <input v-model="form.slug" type="text" name="slug" maxlength="200" />
            </FormField>
            <FormField :label="t('admin.music.releases.type')" as="div">
                <BaseSelect v-model="form.type" :options="types" name="type" />
            </FormField>
            <FormField :label="t('admin.music.releases.date')" :error="errors.releaseDate ?? null">
                <input v-model="form.releaseDate" type="date" name="releaseDate" required />
            </FormField>
            <FormField v-for="language in LANGUAGES" :key="language" :label="t('admin.music.releases.description', { language: language.toUpperCase() })">
                <textarea v-model="form.description[language]" :name="`description-${language}`" rows="3" />
            </FormField>

            <fieldset class="release__group">
                <legend>{{ t('admin.music.releases.tracks') }}</legend>
                <FormField :label="t('admin.music.releases.paste')" :hint="t('admin.music.releases.pasteHint')">
                    <textarea v-model="paste" name="paste" rows="3" />
                </FormField>
                <BaseButton variant="ghost" :disabled="paste.trim() === ''" @click="fillTracks">{{ t('admin.music.releases.fill') }}</BaseButton>
                <div v-for="(track, index) in form.tracks" :key="index" class="release__row">
                    <span class="tabular">{{ index + 1 }}</span>
                    <input v-model="track.title" type="text" :name="`track-title-${index}`" :aria-label="t('admin.music.releases.trackTitle')" maxlength="200" />
                    <input v-model="track.duration" type="text" :name="`track-duration-${index}`" :aria-label="t('admin.music.releases.trackDuration')" placeholder="3:45" />
                    <IconButton :icon="Trash2" :label="t('admin.music.delete')" variant="danger" @click="form.tracks.splice(index, 1)" />
                </div>
                <BaseButton variant="ghost" @click="form.tracks.push({ title: '', duration: '' })"><Plus size="1rem" aria-hidden="true" />{{ t('admin.music.releases.addTrack') }}</BaseButton>
            </fieldset>

            <fieldset class="release__group">
                <legend>{{ t('admin.music.releases.links') }}</legend>
                <div v-for="(link, index) in form.links" :key="index" class="release__row">
                    <BaseSelect v-model="link.platform" :options="platforms" :name="`link-platform-${index}`" />
                    <input v-model="link.url" type="url" :name="`link-url-${index}`" :aria-label="t('admin.music.links.url')" />
                    <IconButton :icon="Trash2" :label="t('admin.music.delete')" variant="danger" @click="form.links.splice(index, 1)" />
                </div>
                <BaseButton variant="ghost" @click="form.links.push({ platform: 'spotify', url: '' })"><Plus size="1rem" aria-hidden="true" />{{ t('admin.music.releases.addLink') }}</BaseButton>
            </fieldset>

            <FormField :label="t('admin.music.releases.smartLink')" :error="errors.smartLinkUrl ?? null">
                <input v-model="form.smartLinkUrl" type="url" name="smartLinkUrl" />
            </FormField>
            <FormField :label="t('admin.music.releases.spotify')" :error="errors.spotifyUrl ?? null">
                <input v-model="form.spotifyUrl" type="text" name="spotifyUrl" />
            </FormField>

            <div v-if="release" class="release__cover">
                <img v-if="release.cover" class="release__preview" :src="imageUrl(release.cover)" :alt="t('admin.music.releases.cover')" />
                <label class="release__upload">
                    {{ t('admin.music.releases.cover') }}
                    <input class="visually-hidden" type="file" accept="image/*" name="cover" @change="upload" />
                </label>
                <ConfirmButton v-if="release.cover" :label="t('admin.music.remove')" @confirm="removeCover" />
            </div>

            <BaseButton type="submit" :loading="saving">{{ t('admin.music.save') }}</BaseButton>
        </form>
    </BaseModal>
</template>

<style scoped>
.release { display: flex; flex-direction: column; gap: var(--space-3); }
.release > .button { align-self: flex-start; }
.release__group { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: var(--space-3); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.release__group legend { padding: 0 var(--space-2); color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.release__row { display: flex; align-items: center; gap: var(--space-2); }
.release__row input { flex: 1; min-width: 0; }
.release__cover { display: flex; align-items: center; gap: var(--space-3); }
.release__preview { width: 5rem; height: 5rem; object-fit: cover; border-radius: var(--radius); }
.release__upload { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-weight: 600; cursor: pointer; }
.release__upload:focus-within { outline: 0.125rem solid var(--color-accent); }
</style>
