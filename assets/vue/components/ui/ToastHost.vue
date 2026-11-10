<script setup>
import { X } from '@lucide/vue';
import { ToastClose, ToastDescription, ToastProvider, ToastRoot, ToastViewport } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { useToast } from '../../composables/useToast.js';

const { t } = useI18n();
const { toasts, dismiss } = useToast();
</script>

<template>
    <ToastProvider :label="t('ui.toast.label')" swipe-direction="right">
        <ToastRoot
            v-for="toast in toasts"
            :key="toast.id"
            :duration="toast.duration"
            :type="toast.type === 'error' ? 'foreground' : 'background'"
            :class="['toast', `toast--${toast.type}`]"
            data-test="toast"
            @update:open="(open) => !open && dismiss(toast.id)"
        >
            <ToastDescription class="toast__message">{{ toast.message }}</ToastDescription>
            <ToastClose class="toast__close" :aria-label="t('common.close')"><X size="1rem" aria-hidden="true" /></ToastClose>
        </ToastRoot>
        <ToastViewport class="toast-host" />
    </ToastProvider>
</template>

<style>
.toast-host { position: fixed; right: var(--space-4); bottom: calc(var(--space-4) + env(safe-area-inset-bottom)); z-index: 100; display: flex; flex-direction: column; gap: var(--space-2); width: min(24rem, calc(100vw - 2 * var(--space-4))); margin: 0; padding: 0; list-style: none; outline: none; }
.toast { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-left: 0.25rem solid var(--color-accent); border-radius: var(--radius); background: var(--color-surface); color: var(--color-ink); box-shadow: var(--shadow); }
.toast--error { border-left-color: var(--color-danger); }
.toast__message { flex: 1; }
.toast__close { display: inline-flex; border: none; background: none; color: var(--color-muted); cursor: pointer; }
.toast__close:hover { color: var(--color-ink); }
.toast[data-state="open"] { animation: toast-in var(--transition); }
.toast[data-state="closed"] { animation: toast-out var(--transition); }
.toast[data-swipe="move"] { transform: translateX(var(--reka-toast-swipe-move-x)); }
@keyframes toast-in { from { opacity: 0; transform: translateY(0.5rem); } }
@keyframes toast-out { to { opacity: 0; transform: translateY(0.5rem); } }
@media (max-width: 48rem) { .toast-host { bottom: calc(5.5rem + env(safe-area-inset-bottom)); } }
</style>
