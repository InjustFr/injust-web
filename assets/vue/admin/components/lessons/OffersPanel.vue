<script setup>
import { GraduationCap, Pencil, Plus, Trash2 } from '@lucide/vue';
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
import OfferModal from './OfferModal.vue';

const props = defineProps({ offers: { type: Array, required: true } });
const { t } = useI18n();
const api = useApi();
const toast = useToast();
const editing = ref(null);
const open = ref(false);
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/lessons/offers/order', { ids: moveId(props.offers.map((item) => item.id), id, delta) }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/lessons/offers/${id}`), t('admin.lessons.saved'));
const price = (cents) => new Intl.NumberFormat(intlLocale(), { style: 'currency', currency: 'EUR' }).format(cents / 100);

function edit(id) {
    editing.value = id;
    open.value = true;
}
</script>

<template>
    <PageSection :title="t('admin.lessons.offers.title')" :count="offers.length">
        <template #actions>
            <BaseButton @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.lessons.offers.new') }}</BaseButton>
        </template>
        <EmptyState v-if="offers.length === 0" :icon="GraduationCap" :title="t('admin.lessons.offers.empty')" />
        <ul v-else class="rows">
            <li v-for="(offer, index) in offers" :key="offer.id" class="rows__item">
                <span class="rows__label">
                    {{ offer.title.en || offer.title.fr }}
                    <span class="rows__sub">{{ t(`admin.lessons.kinds.${offer.kind}`) }}<template v-if="offer.kind === 'pack'"> {{ t('admin.lessons.offers.packSessions', { count: offer.sessions }) }}</template> · {{ t(`admin.lessons.formats.${offer.format}`) }} · {{ t('admin.lessons.offers.duration', { minutes: offer.minutes }) }} · {{ price(offer.price.cents) }}</span>
                </span>
                <OrderButtons :first="index === 0" :last="index === offers.length - 1" @move="(delta) => reorder(offer.id, delta)" />
                <IconButton :icon="Pencil" :label="t('admin.lessons.edit')" @click="edit(offer.id)" />
                <ConfirmButton :icon="Trash2" :label="t('admin.lessons.delete')" @confirm="remove(offer.id)" />
            </li>
        </ul>
        <OfferModal v-model:open="open" :offer="offers.find((offer) => offer.id === editing) ?? null" />
    </PageSection>
</template>
