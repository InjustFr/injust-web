<script setup>
defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: null },
});
</script>

<template>
    <div class="empty-state">
        <svg class="empty-state__moss" viewBox="0 0 64 40" aria-hidden="true">
            <path class="empty-state__mound" d="M4 38c0-8 6-13 12-12 2-7 9-11 16-8 4-6 13-6 17 1 7-1 12 5 11 12z" />
            <path class="empty-state__stem" d="M32 18c0-6 1-10 4-13M36 5c3-1 6 0 7 2-3 2-6 1-7-2z" />
            <circle class="empty-state__drop" cx="22" cy="10" r="3" />
        </svg>
        <p class="empty-state__title">{{ title }}</p>
        <p v-if="hint" class="empty-state__hint">{{ hint }}</p>
        <slot />
    </div>
</template>

<style scoped>
.empty-state { display: flex; flex-direction: column; align-items: center; gap: var(--space-1); padding: var(--space-6) var(--space-5); border: 0.0625rem dashed var(--color-border-strong); border-radius: var(--radius); text-align: center; }
.empty-state__moss { width: 4rem; height: 2.5rem; margin-bottom: var(--space-2); }
.empty-state__mound { fill: var(--color-accent-soft); stroke: var(--color-accent); stroke-width: 1.5; stroke-linejoin: round; }
.empty-state__stem { fill: none; stroke: var(--color-accent); stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
.empty-state__drop { fill: var(--color-dew); }
.empty-state__title { color: var(--color-ink); font-weight: 600; }
.empty-state__hint { color: var(--color-muted); font-size: var(--font-size-md); }
</style>
