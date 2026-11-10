<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { intlLocale } from '../../../i18n/locale.js';
import { formatMoney, thresholdProgress, totalsTable } from '../../ordersView.js';

const props = defineProps({ totals: { type: Object, required: true }, year: { type: Number, required: true } });
const { t } = useI18n();
const money = (cents) => formatMoney(cents, intlLocale());
const table = computed(() => totalsTable(props.totals.byCountryAndType));
const threshold = computed(() => money(props.totals.euDigitalThresholdCents));
const meters = computed(() => ['digital', 'lessons'].map((key) => {
    const cents = key === 'digital' ? props.totals.euConsumerDigitalCrossBorderCents : props.totals.euConsumerOnlineLessonsCents;
    const progress = thresholdProgress(cents, props.totals.euDigitalThresholdCents);
    return { key, cents, ...progress };
}));
</script>

<template>
    <section class="totals">
        <h2 class="totals__title">{{ t('admin.orders.totals.title', { year }) }}</h2>
        <p v-if="table.rows.length === 0" class="totals__muted">{{ t('admin.orders.totals.empty') }}</p>
        <div v-else class="totals__scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ t('admin.orders.totals.country') }}</th>
                        <th v-for="type in table.types" :key="type" scope="col" class="totals__num">{{ t(`admin.orders.types.${type}`) }}</th>
                        <th scope="col" class="totals__num">{{ t('admin.orders.totals.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in table.rows" :key="row.country">
                        <th scope="row">{{ row.country }}</th>
                        <td v-for="type in table.types" :key="type" class="totals__num tabular">{{ money(row.cells[type] ?? 0) }}</td>
                        <td class="totals__num tabular">{{ money(row.total) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">{{ t('admin.orders.totals.total') }}</th>
                        <td v-for="type in table.types" :key="type" class="totals__num tabular">{{ money(table.totals[type]) }}</td>
                        <td class="totals__num tabular">{{ money(table.total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div v-for="meter in meters" :key="meter.key" class="totals__meter">
            <h3>{{ t(`admin.orders.totals.${meter.key}.title`) }}</h3>
            <div class="totals__bar" :class="`totals__bar--${meter.state}`" role="progressbar" :aria-valuenow="meter.percent" aria-valuemin="0" aria-valuemax="100" :aria-label="t(`admin.orders.totals.${meter.key}.title`)">
                <div class="totals__fill" :style="{ width: `${meter.percent}%` }" />
            </div>
            <p class="tabular">
                {{ t('admin.orders.totals.progress', { amount: money(meter.cents), threshold }) }}
                <strong v-if="meter.state === 'warning'">{{ t('admin.orders.totals.warning') }}</strong>
                <strong v-if="meter.state === 'crossed'">{{ t('admin.orders.totals.crossed') }}</strong>
            </p>
            <p class="totals__muted">{{ t(`admin.orders.totals.${meter.key}.hint`, { threshold }) }}</p>
        </div>
    </section>
</template>

<style scoped>
.totals { display: flex; flex-direction: column; gap: var(--space-3); padding: var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); }
.totals__title { margin: 0; font-size: 1.125rem; }
.totals__scroll { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: var(--space-1) var(--space-3); text-align: left; white-space: nowrap; }
tfoot { border-top: 0.0625rem solid var(--color-border-strong); font-weight: 600; }
.totals__num { text-align: right; }
.totals__muted { margin: 0; color: var(--color-muted); }
.totals__meter h3 { margin: 0 0 var(--space-1); font-size: var(--font-size-md); }
.totals__meter p { margin: var(--space-1) 0 0; }
.totals__bar { height: 0.5rem; overflow: hidden; border-radius: var(--radius); background: var(--color-hover); }
.totals__fill { height: 100%; background: var(--color-accent); }
.totals__bar--warning .totals__fill, .totals__bar--crossed .totals__fill { background: var(--color-danger); }
</style>
