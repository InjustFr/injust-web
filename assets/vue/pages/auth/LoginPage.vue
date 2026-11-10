<script setup>
import { useI18n } from 'vue-i18n';
import AuthLayout from '../../layouts/AuthLayout.vue';
import AuthMessage from '../../components/auth/AuthMessage.vue';
import BaseButton from '../../components/ui/BaseButton.vue';

defineProps({
    error: { type: String, required: true },
});

const { t } = useI18n();
</script>

<template>
    <AuthLayout :title="t('auth.login.title')">
        <AuthMessage>{{ error }}</AuthMessage>
        <p class="login__intro">{{ t('auth.login.intro') }}</p>
        <BaseButton href="/login">{{ t('auth.login.retry') }}</BaseButton>
        <template #footer><a href="/">{{ t('auth.login.site') }}</a></template>
    </AuthLayout>
</template>

<style scoped>
.login__intro { color: var(--color-muted); font-size: var(--font-size-md); }
</style>
