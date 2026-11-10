<script setup>
import { Copy } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import { useToast } from '../../../composables/useToast.js';

const { t } = useI18n();
const toast = useToast();
const manual = ref(false);
const publicUrl = `${window.location.origin}/links`;

async function copy() {
    try {
        await navigator.clipboard.writeText(publicUrl);
        toast.success(t('admin.links.copied'));
    } catch {
        manual.value = true;
        toast.error(t('admin.links.copyFailed'));
    }
}
</script>

<template>
    <div class="public-url">
        <a :href="publicUrl" target="_blank" rel="noopener" class="public-url__link">{{ publicUrl }}</a>
        <BaseButton type="button" variant="secondary" @click="copy"><Copy size="1rem" aria-hidden="true" />{{ t('admin.links.copy') }}</BaseButton>
        <input v-if="manual" class="public-url__manual" type="text" readonly :value="publicUrl" :aria-label="t('admin.links.publicUrl')" @focus="$event.target.select()" />
    </div>
</template>

<style scoped>
.public-url { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
.public-url__link { font-weight: 600; overflow-wrap: anywhere; }
.public-url__manual { flex-basis: 100%; }
</style>
