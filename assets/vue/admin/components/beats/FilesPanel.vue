<script setup>
import { useI18n } from 'vue-i18n';
import { FILE_SLOTS } from '../../beatsForm.js';
import FileSlot from './FileSlot.vue';

defineProps({ beat: { type: Object, required: true } });
const { t } = useI18n();
</script>

<template>
    <section class="files" :aria-label="t('admin.beats.files.title', { title: beat.title })">
        <FileSlot v-for="kind in FILE_SLOTS" :key="kind" :beat="beat" :kind="kind" />
    </section>
</template>

<style scoped>
.files { display: grid; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr)); gap: var(--space-3); padding: var(--space-3); }
</style>
