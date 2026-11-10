<script setup>
defineProps({
    title: { type: String, required: true },
    count: { type: Number, default: null },
});
</script>

<template>
    <section class="page-section">
        <header class="page-section__header">
            <h2 class="page-section__title">{{ title }}<span v-if="count !== null" class="page-section__count tabular">{{ count }}</span></h2>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>

<style scoped>
.page-section { display: flex; flex-direction: column; gap: var(--space-3); }
.page-section__header { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); min-height: 2.25rem; }
.page-section__title { display: flex; align-items: baseline; gap: var(--space-2); color: var(--color-muted); font-size: var(--font-size-xs); letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.page-section__count { color: var(--color-subtle); font-weight: 400; }
</style>
