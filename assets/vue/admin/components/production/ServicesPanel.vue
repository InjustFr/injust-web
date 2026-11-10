<script setup>
import { Pencil, Plus, SlidersHorizontal, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../../components/ui/BaseButton.vue';
import ConfirmButton from '../../../components/ui/ConfirmButton.vue';
import EmptyState from '../../../components/ui/EmptyState.vue';
import IconButton from '../../../components/ui/IconButton.vue';
import PageSection from '../../../components/ui/PageSection.vue';
import { useApi } from '../../../composables/useApi.js';
import { useToast } from '../../../composables/useToast.js';
import { intlLocale } from '../../../i18n/locale.js';
import { moveId } from '../../musicForm.js';
import OrderButtons from '../music/OrderButtons.vue';
import ServiceModal from './ServiceModal.vue';

const props = defineProps({ services: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const editing = ref(null);
const open = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/production/services/order', { ids: moveId(props.services.map((item) => item.id), id, delta) }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/production/services/${id}`), t('admin.production.saved'));
const euros = (cents) => new Intl.NumberFormat(intlLocale(), { style: 'currency', currency: 'EUR' }).format(cents / 100);
const priceLabel = (service) => {
    if (service.pricing === 'fixed') return euros(service.price.cents);
    return service.price.cents === 0 ? t('admin.production.services.onQuote') : t('admin.production.services.from', { price: euros(service.price.cents) });
};

function edit(id) {
    editing.value = id;
    open.value = true;
}
</script>

<template>
    <PageSection :title="t('admin.production.services.title')" :count="services.length">
        <template #actions>
            <BaseButton @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.production.services.new') }}</BaseButton>
        </template>
        <EmptyState v-if="services.length === 0" :icon="SlidersHorizontal" :title="t('admin.production.services.empty')" />
        <ul v-else class="rows">
            <li v-for="(service, index) in services" :key="service.id" class="rows__item">
                <span class="rows__label">
                    {{ service.title.en || service.title.fr }}
                    <span class="rows__sub">{{ t(`admin.production.kinds.${service.kind}`) }} · {{ priceLabel(service) }}<template v-if="service.deliveryDays !== null"> · {{ t('admin.production.services.days', { count: service.deliveryDays }) }}</template></span>
                </span>
                <OrderButtons :first="index === 0" :last="index === services.length - 1" @move="(delta) => reorder(service.id, delta)" />
                <IconButton :icon="Pencil" :label="t('admin.production.edit')" @click="edit(service.id)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.production.delete')" @confirm="remove(service.id)" />
            </li>
        </ul>
        <ServiceModal v-model:open="open" :service="services.find((service) => service.id === editing) ?? null" />
    </PageSection>
</template>
