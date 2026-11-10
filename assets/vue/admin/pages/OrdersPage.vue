<script setup>
import { Download, ShoppingBag } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import BaseSelect from '../../components/ui/BaseSelect.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import OrdersTable from '../components/orders/OrdersTable.vue';
import TotalsPanel from '../components/orders/TotalsPanel.vue';
import { STATUSES, exportUrl, ordersUrl, yearOptions } from '../ordersView.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const currentYear = new Date().getFullYear();
const year = ref(currentYear);
const status = ref('');
const orders = ref([]);
const totals = ref(null);
const years = yearOptions(currentYear).map((value) => ({ value, label: String(value) }));
const statuses = computed(() => [{ value: '', label: t('admin.orders.allStatuses') }, ...STATUSES.map((value) => ({ value, label: t(`admin.orders.statuses.${value}`) }))]);

const totalsUrl = () => `/api/admin/orders/totals?year=${year.value}`;
await Promise.all([api.load(ordersUrl({ status: status.value, year: year.value }), orders), api.load(totalsUrl(), totals)]);

watch([year, status], () => {
    toast.attempt(() => Promise.all([api.load(ordersUrl({ status: status.value, year: year.value }), orders), api.load(totalsUrl(), totals)]));
});
</script>

<template>
    <div class="orders-page">
        <PageHeader :title="t('admin.orders.title')" :subtitle="t('admin.orders.subtitle')">
            <BaseButton variant="ghost" :href="exportUrl(year)"><Download size="1rem" aria-hidden="true" />{{ t('admin.orders.export') }}</BaseButton>
        </PageHeader>
        <div class="orders-page__filters">
            <label class="orders-page__filter">{{ t('admin.orders.year') }}<BaseSelect v-model="year" :options="years" name="year" /></label>
            <label class="orders-page__filter">{{ t('admin.orders.status') }}<BaseSelect v-model="status" :options="statuses" name="status" /></label>
        </div>
        <TotalsPanel v-if="totals" :totals="totals" :year="year" />
        <EmptyState v-if="orders.length === 0" :icon="ShoppingBag" :title="t('admin.orders.empty')" />
        <OrdersTable v-else :orders="orders" />
    </div>
</template>

<style scoped>
.orders-page { display: flex; flex-direction: column; gap: var(--space-5); }
.orders-page__filters { display: flex; flex-wrap: wrap; gap: var(--space-4); }
.orders-page__filter { display: flex; flex-direction: column; gap: var(--space-1); color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 600; }
</style>
