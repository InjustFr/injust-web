<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    tone: { type: String, default: null },
});
</script>

<template>
    <header class="page-header" :style="tone ? { '--tone': tone } : undefined">
        <div class="page-header__text">
            <h1 :class="['page-header__title', { 'page-header__title--toned': tone }]">{{ title }}</h1>
            <p v-if="subtitle" class="page-header__subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.default" class="page-header__actions"><slot /></div>
    </header>
</template>

<style scoped>
.page-header { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: var(--space-3); padding-bottom: var(--space-4); border-bottom: 0.0625rem solid var(--color-border); }
.page-header__text { display: flex; flex-direction: column; gap: var(--space-1); min-width: 0; }
.page-header__title { overflow-wrap: anywhere; }
.page-header__title--toned { color: var(--tone); }
.page-header__subtitle { color: var(--color-muted); }
.page-header__actions { display: flex; align-items: center; gap: var(--space-1); }
</style>
