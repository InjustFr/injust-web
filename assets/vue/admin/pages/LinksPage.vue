<script setup>
import { Eye, EyeOff, Link, Pencil, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../../components/ui/BaseButton.vue';
import ConfirmButton from '../../components/ui/ConfirmButton.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import IconButton from '../../components/ui/IconButton.vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import PageSection from '../../components/ui/PageSection.vue';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { intlLocale } from '../../i18n/locale.js';
import LinkModal from '../components/links/LinkModal.vue';
import PublicUrl from '../components/links/PublicUrl.vue';
import OrderButtons from '../components/music/OrderButtons.vue';
import { hostOf, linkPayload } from '../linksForm.js';
import { moveId } from '../musicForm.js';

const { t, locale } = useI18n();
const api = useApi();
const toast = useToast();
const links = ref([]);
const editing = ref(null);
const modal = ref(false);
await api.load('/api/admin/links', links);

const name = (link) => link.label[locale.value] || link.label.fr || link.label.en;
const edit = (link) => { editing.value = link; modal.value = true; };
const patch = (link, changes) => toast.attempt(() => api.put(`/api/admin/links/${link.id}`, linkPayload({ ...link, icon: link.icon ?? '', ...changes })));
const reorder = (id, delta) => toast.attempt(() => api.put('/api/admin/links/order', { ids: moveId(links.value.map((item) => item.id), id, delta) }));
const remove = (id) => toast.attempt(() => api.del(`/api/admin/links/${id}`), t('admin.links.saved'));
</script>

<template>
    <div class="links-page">
        <PageHeader :title="t('admin.links.title')" :subtitle="t('admin.links.subtitle')" />
        <PageSection :title="t('admin.links.publicUrl')"><PublicUrl /></PageSection>
        <PageSection :title="t('admin.links.list')" :count="links.length">
            <template #actions>
                <BaseButton type="button" @click="edit(null)"><Plus size="1rem" aria-hidden="true" />{{ t('admin.links.new') }}</BaseButton>
            </template>
            <EmptyState v-if="links.length === 0" :icon="Link" :title="t('admin.links.empty')" />
            <ul v-else class="rows">
                <li v-for="(link, index) in links" :key="link.id" class="rows__item">
                    <span class="rows__label">
                        {{ name(link) }}
                        <span class="rows__sub">{{ hostOf(link.url) }}<template v-if="link.icon"> · {{ t(`admin.music.networks.${link.icon}`) }}</template></span>
                    </span>
                    <span class="tabular rows__sub">{{ t('admin.links.clicks', { n: new Intl.NumberFormat(intlLocale()).format(link.clicks) }, link.clicks) }}</span>
                    <IconButton :icon="Star" :label="t('admin.links.highlighted')" :pressed="link.highlighted" @click="patch(link, { highlighted: !link.highlighted })" />
                    <IconButton :icon="link.visible ? Eye : EyeOff" :label="t('admin.links.visible')" :pressed="link.visible" @click="patch(link, { visible: !link.visible })" />
                    <OrderButtons :first="index === 0" :last="index === links.length - 1" @move="(delta) => reorder(link.id, delta)" />
                    <IconButton :icon="Pencil" :label="t('admin.links.edit')" @click="edit(link)" />
                    <ConfirmButton :icon="Trash2" :label="t('admin.links.delete')" @confirm="remove(link.id)" />
                </li>
            </ul>
        </PageSection>
        <LinkModal v-model:open="modal" :link="editing" />
    </div>
</template>

<style scoped>
.links-page { display: flex; flex-direction: column; gap: var(--space-5); }
</style>
