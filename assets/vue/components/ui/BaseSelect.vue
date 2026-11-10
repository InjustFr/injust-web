<script setup>
import { computed } from 'vue';
import { Check, ChevronDown } from '@lucide/vue';
import { SelectContent, SelectItem, SelectItemIndicator, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue, SelectViewport } from 'reka-ui';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineOptions({ inheritAttrs: false });

const props = defineProps({
    options: { type: Array, required: true },
    placeholder: { type: String, default: undefined },
});
const model = defineModel({ type: [String, Number, null], default: '' });

const NONE = '__none__';
const toItemValue = (value) => (value === '' || value === null ? NONE : String(value));

const items = computed(() => props.options.map((option) => ({ ...option, itemValue: toItemValue(option.value) })));

const selected = computed({
    get: () => items.value.find((option) => option.value === model.value)?.itemValue ?? '',
    set: (itemValue) => {
        model.value = items.value.find((option) => option.itemValue === itemValue)?.value ?? null;
    },
});
</script>

<template>
    <SelectRoot v-model="selected">
        <SelectTrigger class="control select" v-bind="$attrs">
            <SelectValue class="select__value" :placeholder="placeholder ?? t('common.choose')" />
            <ChevronDown class="select__chevron" size="1rem" aria-hidden="true" />
        </SelectTrigger>
        <SelectPortal>
            <SelectContent class="popover select__content" position="popper" :side-offset="4">
                <SelectViewport>
                    <SelectItem v-for="item in items" :key="item.itemValue" :value="item.itemValue" class="popover__item">
                        <span v-if="item.color" class="select__swatch" :style="{ background: `var(--project-${item.color})` }" aria-hidden="true" />
                        <SelectItemText>{{ item.label }}</SelectItemText>
                        <SelectItemIndicator class="popover__hint"><Check size="0.875rem" aria-hidden="true" /></SelectItemIndicator>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>

<style scoped>
.select { display: inline-flex; align-items: center; justify-content: space-between; gap: var(--space-2); text-align: left; cursor: pointer; }
.select__value { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.select[data-placeholder] .select__value { color: var(--color-subtle); }
.select__chevron { flex-shrink: 0; color: var(--color-muted); }
</style>

<style>
.select__content { min-width: var(--reka-select-trigger-width); max-height: var(--reka-select-content-available-height); overflow: hidden; }
.select__swatch { width: 0.625rem; height: 0.625rem; border-radius: 50%; flex-shrink: 0; }
</style>
