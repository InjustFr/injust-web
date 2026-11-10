<script setup>
import { SwitchRoot, SwitchThumb } from 'reka-ui';

const model = defineModel({ type: Boolean, required: true });
</script>

<template>
    <SwitchRoot v-model="model" class="switch">
        <SwitchThumb class="switch__thumb" />
    </SwitchRoot>
</template>

<style scoped>
.switch { flex: none; position: relative; width: 2.5rem; height: 1.5rem; padding: 0; border: none; border-radius: 999px; background: var(--color-border-strong); cursor: pointer; transition: background var(--transition); }
.switch[data-state="checked"] { background: var(--color-accent); }
.switch:disabled { opacity: 0.5; cursor: not-allowed; }
.switch__thumb { display: block; width: 1.125rem; height: 1.125rem; margin-left: 0.1875rem; border-radius: 50%; background: var(--color-surface); transition: transform var(--transition); }
.switch__thumb[data-state="checked"] { transform: translateX(1rem); }
</style>
