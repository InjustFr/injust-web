<script setup>
import { FileText } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { intlLocale } from '../../../i18n/locale.js';
import { canRefund, formatMoney } from '../../ordersView.js';
import RefundModal from './RefundModal.vue';

const source = defineProps({ order: { type: Object, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const detail = ref(source.order);
api.load(`/api/admin/orders/${source.order.id}`, detail).catch(() => {});
const order = computed(() => ({ refundedCents: 0, withdrawal: null, creditNotes: [], ...source.order, ...detail.value }));
const refunding = ref(false);
const money = (cents) => formatMoney(cents, intlLocale());
const day = (value) => new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium' }).format(new Date(value));
const customer = computed(() => order.value.customer);
const address = computed(() => customer.value && [customer.value.line1, customer.value.line2, `${customer.value.postalCode} ${customer.value.city}`.trim(), customer.value.state, customer.value.country].filter(Boolean).join(', '));
const when = (value) => new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
const resendAcknowledgement = () => toast.attempt(() => api.post(`/api/admin/orders/${order.value.id}/withdrawal/resend`), t('admin.orders.detail.acknowledgementResent'));
const resend = () => toast.attempt(() => api.post(`/api/admin/orders/${order.id}/resend`), t('admin.orders.detail.resent'));
</script>

<template>
    <div class="order-detail">
        <section>
            <h3>{{ t('admin.orders.detail.customer') }}</h3>
            <dl v-if="customer">
                <dt>{{ t('admin.orders.detail.email') }}</dt><dd>{{ customer.email }}</dd>
                <dt>{{ t('admin.orders.detail.address') }}</dt><dd>{{ address }}</dd>
                <dt>{{ t('admin.orders.detail.company') }}</dt><dd>{{ customer.company ?? t('admin.orders.detail.none') }}</dd>
                <dt>{{ t('admin.orders.detail.vatId') }}</dt><dd>{{ customer.vatId ?? t('admin.orders.detail.none') }}</dd>
                <dt>{{ t('admin.orders.detail.cardCountry') }}</dt><dd>{{ customer.cardCountry ?? t('admin.orders.detail.none') }}</dd>
            </dl>
            <p v-else>{{ order.email }}</p>
            <p v-if="order.productFormat">{{ t('admin.orders.detail.format', { format: order.productFormat }) }}</p>
        </section>
        <section>
            <h3>{{ t('admin.orders.detail.consents') }}</h3>
            <p class="order-detail__muted">{{ t('admin.orders.detail.cgvVersion', { version: order.cgvVersion }) }}</p>
            <ul>
                <li v-for="consent in order.consents" :key="consent.kind">
                    {{ consent.text }} <span class="order-detail__muted">({{ t('admin.orders.detail.acceptedAt', { date: when(consent.acceptedAt) }) }})</span>
                </li>
            </ul>
        </section>
        <section>
            <h3>{{ t('admin.orders.detail.invoice') }}</h3>
            <p v-if="order.invoiceNumber">
                {{ t('admin.orders.detail.invoiceNumber', { number: order.invoiceNumber }) }}
                <BaseButton variant="ghost" :href="`/api/admin/orders/${order.id}/invoice`"><FileText size="1rem" aria-hidden="true" />{{ t('admin.orders.detail.openInvoice') }}</BaseButton>
            </p>
            <p v-else class="order-detail__muted">{{ t('admin.orders.detail.noInvoice') }}</p>
            <template v-if="order.status === 'paid'">
                <p class="order-detail__muted">{{ t('admin.orders.detail.confirmation', { status: order.confirmationSentAt ? t('admin.orders.detail.sentAt', { date: when(order.confirmationSentAt) }) : t('admin.orders.detail.notSent') }) }}</p>
                <ConfirmButton :label="t('admin.orders.detail.resend')" :message="t('admin.orders.detail.resendMessage')" :confirm-label="t('admin.orders.detail.resendAction')" @confirm="resend" />
            </template>
        </section>
        <section v-if="order.withdrawal">
            <h3>{{ t('admin.orders.detail.withdrawal') }}</h3>
            <p>{{ t('admin.orders.detail.withdrawalReceived', { date: when(order.withdrawal.receivedAt) }) }}</p>
            <p v-if="order.withdrawal.name">{{ t('admin.orders.detail.withdrawalName', { name: order.withdrawal.name }) }}</p>
            <p v-if="order.withdrawal.reason">{{ t('admin.orders.detail.withdrawalReason', { reason: order.withdrawal.reason }) }}</p>
            <p class="order-detail__muted">{{ order.withdrawal.acknowledgedAt ? t('admin.orders.detail.withdrawalAcknowledged', { date: when(order.withdrawal.acknowledgedAt) }) : t('admin.orders.detail.withdrawalNotAcknowledged') }}</p>
            <ConfirmButton :label="t('admin.orders.detail.resendAcknowledgement')" :message="t('admin.orders.detail.resendAcknowledgementMessage')" :confirm-label="t('admin.orders.detail.resendAcknowledgementAction')" @confirm="resendAcknowledgement" />
        </section>
        <section v-if="order.invoiceNumber">
            <h3>{{ t('admin.orders.detail.creditNotes') }}</h3>
            <p v-if="order.refundedCents > 0">{{ t('admin.orders.detail.refunded', { amount: money(order.refundedCents), total: money(order.amountCents) }) }}</p>
            <ul v-if="order.creditNotes.length > 0" class="order-detail__notes">
                <li v-for="note in order.creditNotes" :key="note.id">
                    <BaseButton variant="ghost" :href="`/api/admin/orders/${order.id}/credit-notes/${note.id}`"><FileText size="1rem" aria-hidden="true" />{{ t('admin.orders.detail.creditNote', { number: note.number }) }}</BaseButton>
                    <span class="order-detail__muted">{{ t('admin.orders.detail.creditNoteDetail', { amount: money(note.amountCents), date: day(note.issuedAt) }) }}<template v-if="note.reason"> · {{ note.reason }}</template></span>
                </li>
            </ul>
            <p v-else class="order-detail__muted">{{ t('admin.orders.detail.noCreditNotes') }}</p>
            <template v-if="canRefund(order)">
                <BaseButton variant="ghost" @click="refunding = true">{{ t('admin.orders.refund.button') }}</BaseButton>
                <RefundModal v-model:open="refunding" :order="order" />
            </template>
        </section>
    </div>
</template>

<style scoped>
.order-detail { display: grid; gap: var(--space-4); padding: var(--space-3); white-space: normal; background: var(--color-hover); }
.order-detail h3 { margin: 0 0 var(--space-2); font-size: var(--font-size-sm); letter-spacing: var(--tracking-caps); text-transform: uppercase; color: var(--color-muted); }
.order-detail p, .order-detail ul { margin: 0 0 var(--space-2); }
.order-detail ul { padding-left: var(--space-4); }
.order-detail dl { display: grid; grid-template-columns: max-content 1fr; gap: var(--space-1) var(--space-3); margin: 0; }
.order-detail dt { color: var(--color-muted); }
.order-detail dd { margin: 0; overflow-wrap: anywhere; }
.order-detail__muted { color: var(--color-muted); }
.order-detail .order-detail__notes { padding-left: 0; list-style: none; }
</style>
