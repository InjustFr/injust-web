<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import BaseModal from '../../../components/ui/BaseModal.vue';
import FormField from '../../../components/ui/FormField.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { intlLocale } from '../../../i18n/locale.js';
import { centsToEuros } from '../../forms.js';
import { REASON_MAX, formatMoney, proRataCents, refundPayload, remainingCents } from '../../ordersView.js';

const props = defineProps({ order: { type: Object, required: true } });
const open = defineModel('open', { type: Boolean, required: true });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const money = (cents) => formatMoney(cents, intlLocale());
const remaining = computed(() => remainingCents(props.order));
const euros = ref('');
const reason = ref('');
const mode = ref('none');
const done = ref(0);
const total = ref(1);
const percent = ref(50);
const saving = ref(false);
const failed = ref(false);
const suggestion = computed(() => proRataCents({ amountCents: props.order.amountCents, remainingCents: remaining.value, mode: mode.value, done: done.value, total: total.value, percent: percent.value }));
const payload = computed(() => refundPayload(euros.value, reason.value, remaining.value));

watch(open, (isOpen) => {
    if (!isOpen) return;
    euros.value = centsToEuros(remaining.value);
    reason.value = '';
    mode.value = 'none';
    failed.value = false;
});

async function submit() {
    failed.value = payload.value === null;
    if (failed.value) return;
    saving.value = true;
    const sent = await toast.attempt(() => api.post(`/api/admin/orders/${props.order.id}/refunds`, payload.value), t('admin.orders.refund.requested'));
    saving.value = false;
    if (sent) open.value = false;
}
</script>

<template>
    <BaseModal v-model:open="open" :title="t('admin.orders.refund.title')">
        <form class="refund-form" @submit.prevent="submit">
            <p>{{ t('admin.orders.refund.remaining', { amount: money(remaining) }) }}</p>
            <p class="refund-form__muted">{{ t('admin.orders.refund.info') }}</p>
            <fieldset class="refund-form__helper">
                <legend>{{ t('admin.orders.refund.proRata') }}</legend>
                <label v-for="value in ['none', 'sessions', 'percent']" :key="value"><input v-model="mode" type="radio" name="mode" :value="value" /> {{ t(`admin.orders.refund.mode${value[0].toUpperCase()}${value.slice(1)}`) }}</label>
                <template v-if="mode === 'sessions'">
                    <FormField :label="t('admin.orders.refund.done')"><input v-model.number="done" type="number" min="0" name="done" /></FormField>
                    <FormField :label="t('admin.orders.refund.total')"><input v-model.number="total" type="number" min="1" name="total" /></FormField>
                </template>
                <FormField v-if="mode === 'percent'" :label="t('admin.orders.refund.percent')"><input v-model.number="percent" type="number" min="0" max="100" name="percent" /></FormField>
                <p v-if="mode !== 'none'">
                    {{ t('admin.orders.refund.suggest', { amount: money(suggestion) }) }}
                    <BaseButton variant="ghost" type="button" @click="euros = centsToEuros(suggestion)">{{ t('admin.orders.refund.use') }}</BaseButton>
                </p>
            </fieldset>
            <FormField :label="t('admin.orders.refund.amount')" :error="failed ? t('admin.orders.refund.invalid', { amount: money(remaining) }) : null">
                <input v-model="euros" type="text" inputmode="decimal" name="amount" required />
            </FormField>
            <FormField :label="t('admin.orders.refund.reason')">
                <textarea v-model="reason" name="reason" rows="2" :maxlength="REASON_MAX" />
            </FormField>
            <BaseButton type="submit" :loading="saving">{{ t('admin.orders.refund.submit', { amount: payload ? money(payload.amountCents) : '' }) }}</BaseButton>
        </form>
    </BaseModal>
</template>

<style scoped>
.refund-form { display: flex; flex-direction: column; gap: var(--space-3); }
.refund-form p { margin: 0; }
.refund-form__muted { color: var(--color-muted); font-size: var(--font-size-sm); }
.refund-form__helper { display: flex; flex-direction: column; gap: var(--space-2); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); padding: var(--space-3); }
.refund-form > .button { align-self: flex-start; }
</style>
