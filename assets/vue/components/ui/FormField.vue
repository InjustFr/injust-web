<script setup>
import { Label } from 'reka-ui';
import FieldError from './FieldError.vue';

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    as: { type: String, default: 'label' },
});

const id = `form-field-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <Label v-if="as === 'label'" :class="['form-field', { 'form-field--invalid': error }]">
        <span class="form-field__label">{{ label }}</span>
        <span class="form-field__control">
            <slot />
            <FieldError v-if="error">{{ error }}</FieldError>
            <span v-else-if="hint" class="form-field__hint">{{ hint }}</span>
        </span>
    </Label>
    <div v-else :class="['form-field', { 'form-field--invalid': error }]" role="group" :aria-labelledby="id">
        <span :id="id" class="form-field__label">{{ label }}</span>
        <div class="form-field__control">
            <slot />
            <FieldError v-if="error">{{ error }}</FieldError>
            <span v-else-if="hint" class="form-field__hint">{{ hint }}</span>
        </div>
    </div>
</template>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: var(--space-1); }
.form-field__control { display: flex; flex-direction: column; gap: var(--space-1); min-width: 0; }
.form-field__label { color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 600; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.form-field__hint { color: var(--color-subtle); font-size: var(--font-size-sm); }
.form-field--invalid :deep(:is(.control, .form-field__control > input)) { border-color: var(--color-danger); }
</style>
