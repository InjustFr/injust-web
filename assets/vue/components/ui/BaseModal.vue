<script setup>
import { X } from '@lucide/vue';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    title: { type: String, required: true },
});
const open = defineModel('open', { type: Boolean, required: true });

function focusFirstField(event) {
    const field = event.target.querySelector('.modal__body :is(input, textarea, button, [role="combobox"])');
    if (field) {
        event.preventDefault();
        field.focus();
    }
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="modal">
                <DialogContent class="modal__panel" :aria-describedby="undefined" @open-auto-focus="focusFirstField">
                    <header class="modal__header">
                        <DialogTitle class="modal__title">{{ title }}</DialogTitle>
                        <DialogClose class="modal__close" :aria-label="t('common.close')"><X size="1.125rem" aria-hidden="true" /></DialogClose>
                    </header>
                    <div class="modal__body"><slot /></div>
                </DialogContent>
            </DialogOverlay>
        </DialogPortal>
    </DialogRoot>
</template>
