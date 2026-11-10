<script setup>
import { Inbox } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
});
</script>

<template>
    <div class="empty-state">
        <component :is="icon ?? Inbox" class="empty-state__icon" :size="28" :stroke-width="1.5" aria-hidden="true" />
        <p class="empty-state__title">{{ title }}</p>
        <p v-if="hint" class="empty-state__hint">{{ hint }}</p>
        <slot />
    </div>
</template>

<style scoped>
.empty-state { display: flex; flex-direction: column; align-items: center; gap: var(--space-1); padding: var(--space-6) var(--space-5); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius); text-align: center; }
.empty-state__icon { margin-bottom: var(--space-2); color: var(--color-subtle); }
.empty-state__title { color: var(--color-ink); font-weight: 600; }
.empty-state__hint { color: var(--color-muted); font-size: var(--font-size-md); }
</style>
