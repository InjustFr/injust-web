<script setup>
import { ChevronDown, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '../../../components/ui/IconButton.vue';
import { intlLocale } from '../../../i18n/locale.js';
import { displayStatus, formatMoney, hasConflict } from '../../ordersView.js';
import OrderDetail from './OrderDetail.vue';

defineProps({ orders: { type: Array, required: true } });
const { t } = useI18n();
const openId = ref(null);
const date = (value) => new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium' }).format(new Date(value));
const toggle = (id) => { openId.value = openId.value === id ? null : id; };
</script>

<template>
    <div class="orders-table">
        <table>
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">{{ t('admin.orders.detail.open') }}</span></th>
                    <th scope="col">{{ t('admin.orders.columns.reference') }}</th>
                    <th scope="col">{{ t('admin.orders.columns.paidAt') }}</th>
                    <th scope="col">{{ t('admin.orders.columns.product') }}</th>
                    <th scope="col">{{ t('admin.orders.columns.customer') }}</th>
                    <th scope="col">{{ t('admin.orders.columns.country') }}</th>
                    <th scope="col" class="orders-table__amount">{{ t('admin.orders.columns.amount') }}</th>
                    <th scope="col">{{ t('admin.orders.columns.status') }}</th>
                </tr>
            </thead>
            <tbody v-for="order in orders" :key="order.id">
                <tr>
                    <td>
                        <IconButton :icon="openId === order.id ? ChevronDown : ChevronRight" :label="openId === order.id ? t('admin.orders.detail.close') : t('admin.orders.detail.open')" :aria-expanded="openId === order.id" @click="toggle(order.id)" />
                    </td>
                    <td class="tabular">{{ order.reference }}</td>
                    <td>{{ order.paidAt ? date(order.paidAt) : t('admin.orders.notPaid') }}</td>
                    <td class="orders-table__product">{{ t(`admin.orders.types.${order.productType}`) }} · {{ order.productLabel }}</td>
                    <td>{{ order.customer?.name ?? order.email }}</td>
                    <td>{{ order.customer?.country ?? '' }}</td>
                    <td class="orders-table__amount tabular">{{ formatMoney(order.amountCents, intlLocale()) }}</td>
                    <td><span class="orders-table__chip" :class="`orders-table__chip--${displayStatus(order)}`">{{ t(`admin.orders.statuses.${displayStatus(order)}`) }}</span> <span v-if="hasConflict(order)" class="orders-table__chip orders-table__chip--conflict">{{ t('admin.orders.detail.conflictBadge') }}</span></td>
                </tr>
                <tr v-if="openId === order.id">
                    <td colspan="8"><OrderDetail :order="order" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
.orders-table { overflow-x: auto; border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
table { width: 100%; border-collapse: collapse; font-size: var(--font-size-md); }
th, td { padding: var(--space-2) var(--space-3); text-align: left; vertical-align: middle; white-space: nowrap; }
th { color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 600; }
tbody { border-top: 0.0625rem solid var(--color-border); }
.orders-table__amount { text-align: right; }
.orders-table__product { min-width: 12rem; white-space: normal; }
.orders-table__chip { padding: 0 var(--space-2); border: 0.0625rem solid var(--color-border-strong); border-radius: var(--radius); font-size: var(--font-size-sm); }
.orders-table__chip--paid { border-color: var(--color-accent); color: var(--color-accent-strong); background: var(--color-accent-soft); }
.orders-table__chip--refunded, .orders-table__chip--withdrawn, .orders-table__chip--partiallyRefunded { color: var(--color-muted); }
.orders-table__chip--conflict { border-color: var(--color-accent); color: var(--color-accent-strong); }
.orders-table__chip--expired { color: var(--color-subtle); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
</style>
