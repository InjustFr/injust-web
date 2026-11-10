<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageHeader from '../../components/ui/PageHeader.vue';
import { useApi } from '../../composables/useApi.js';
import LinksPanel from '../components/music/LinksPanel.vue';
import ReleasesPanel from '../components/music/ReleasesPanel.vue';
import TextsPanel from '../components/music/TextsPanel.vue';
import VideosPanel from '../components/music/VideosPanel.vue';

const TABS = ['releases', 'videos', 'texts', 'links'];

const { t } = useI18n();
const music = ref(null);
const tab = ref('releases');
await useApi().load('/api/admin/music', music);
</script>

<template>
    <div class="music-page">
        <PageHeader :title="t('admin.music.title')" :subtitle="t('admin.music.subtitle')" />
        <nav class="music-page__tabs" :aria-label="t('admin.music.title')">
            <button v-for="name in TABS" :key="name" type="button" class="music-page__tab" :aria-pressed="tab === name" @click="tab = name">
                {{ t(`admin.music.tabs.${name}`) }}
            </button>
        </nav>
        <ReleasesPanel v-if="tab === 'releases'" :releases="music.releases" />
        <VideosPanel v-else-if="tab === 'videos'" :videos="music.videos" />
        <TextsPanel v-else-if="tab === 'texts'" :text="music.text" />
        <LinksPanel v-else :links="music.socialLinks" />
    </div>
</template>

<style scoped>
.music-page { display: flex; flex-direction: column; gap: var(--space-5); }
.music-page__tabs { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.music-page__tab { padding: var(--space-2) var(--space-4); border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: none; color: var(--color-muted); font-weight: 600; cursor: pointer; }
.music-page__tab[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }
</style>
