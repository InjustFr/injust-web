<script setup>
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import IconButton from '../../../components/ui/IconButton.vue';

defineProps({ first: { type: Boolean, default: false }, last: { type: Boolean, default: false } });
const emit = defineEmits(['move']);
const { t } = useI18n();
</script>

<template>
    <IconButton :icon="ArrowUp" :label="t('admin.music.moveUp')" :disabled="first" @click="emit('move', -1)" />
    <IconButton :icon="ArrowDown" :label="t('admin.music.moveDown')" :disabled="last" @click="emit('move', 1)" />
</template>
