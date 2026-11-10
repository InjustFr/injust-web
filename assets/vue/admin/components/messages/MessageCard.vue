<script setup>
import { RotateCcw, Check, Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { replyHref } from '../../messagesList.js';

const props = defineProps({ message: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const toggle = () => toast.attempt(() => api.put(`/api/admin/messages/${props.message.id}/handled`, { handled: !props.message.handled }));
const remove = () => toast.attempt(() => api.del(`/api/admin/messages/${props.message.id}`), t('admin.messages.deleted'));
</script>

<template>
    <article class="message" :class="{ 'message--handled': message.handled }">
        <header class="message__head">
            <strong>{{ message.name }}</strong>
            <a :href="replyHref(message.email)">{{ message.email }}</a>
            <span class="message__tag">{{ t(`admin.messages.topics.${message.topic}`) }}</span>
            <span v-if="message.offerLabel" class="message__tag">{{ message.offerLabel }}</span>
            <span class="message__actions">
                <IconButton :icon="message.handled ? RotateCcw : Check" :label="message.handled ? t('admin.messages.reopen') : t('admin.messages.handle')" @click="toggle" />
                <ConfirmButton :icon="Trash2" :label="t('admin.messages.delete')" @confirm="remove" />
            </span>
        </header>
        <p class="message__body">{{ message.body }}</p>
    </article>
</template>

<style scoped>
.message { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.message--handled { opacity: 0.6; }
.message__head { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2) var(--space-3); }
.message__tag { padding: 0 var(--space-2); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); color: var(--color-muted); font-size: var(--font-size-sm); }
.message__actions { display: inline-flex; margin-left: auto; }
.message__body { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
