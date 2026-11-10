<script setup>
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from './BaseButton.vue';
import IconButton from './IconButton.vue';

const { t } = useI18n();

defineProps({
    label: { type: String, required: true },
    confirmLabel: { type: String, default: undefined },
    message: { type: String, default: undefined },
    icon: { type: [Object, Function], default: null },
    variant: { type: String, default: 'ghost' },
});
const emit = defineEmits(['confirm']);

const open = ref(false);
</script>

<template>
    <IconButton v-if="icon" :icon="icon" :label="label" variant="danger" aria-haspopup="dialog" @click="open = true" />
    <BaseButton v-else :variant="variant" aria-haspopup="dialog" @click="open = true">{{ label }}</BaseButton>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="modal">
                <AlertDialogContent class="modal__panel">
                    <AlertDialogTitle class="modal__title">{{ label }}</AlertDialogTitle>
                    <AlertDialogDescription class="confirm-dialog__message">{{ message ?? t('ui.confirm.message') }}</AlertDialogDescription>
                    <div class="actions-row">
                        <AlertDialogCancel as-child><BaseButton variant="ghost">{{ t('common.cancel') }}</BaseButton></AlertDialogCancel>
                        <AlertDialogAction as-child><BaseButton variant="danger" @click="emit('confirm')">{{ confirmLabel ?? t('ui.confirm.action') }}</BaseButton></AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialogOverlay>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>

<style>
.confirm-dialog__message { margin: 0; color: var(--color-muted); }
</style>
