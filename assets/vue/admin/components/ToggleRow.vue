<script setup>
import BaseSwitch from '../../components/ui/BaseSwitch.vue';

defineProps({
    label: { type: String, required: true },
    description: { type: String, default: null },
    error: { type: String, default: null },
    modelValue: { type: Boolean, required: true },
    disabled: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="toggle-row">
        <div class="toggle-row__text">
            <span class="toggle-row__label">{{ label }}</span>
            <span v-if="description" class="toggle-row__description">{{ description }}</span>
            <span v-if="error" class="toggle-row__error" role="alert">{{ error }}</span>
        </div>
        <BaseSwitch :model-value="modelValue" :disabled="disabled" :aria-label="label" @update:model-value="$emit('update:modelValue', $event)" />
    </div>
</template>

<style scoped>
.toggle-row { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); padding: var(--space-3) 0; border-bottom: 0.0625rem solid var(--color-border); }
.toggle-row__text { display: flex; flex-direction: column; gap: var(--space-1); min-width: 0; }
.toggle-row__label { font-weight: 600; }
.toggle-row__description { color: var(--color-muted); font-size: var(--font-size-sm); }
.toggle-row__error { color: var(--color-danger); font-size: var(--font-size-sm); }
</style>
