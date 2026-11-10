<script setup>
import { nextTick, ref } from 'vue';
import { youtubeEmbedUrl } from '../embedUrls.js';

const props = defineProps({
    youtubeId: { type: String, required: true },
    title: { type: String, required: true },
    thumbnail: { type: String, default: null },
    play: { type: String, required: true },
});

const playing = ref(false);
const frame = ref(null);

async function start() {
    playing.value = true;
    await nextTick();
    frame.value?.focus();
}
</script>

<template>
    <iframe
        v-if="playing"
        ref="frame"
        class="video-frame"
        :src="youtubeEmbedUrl(props.youtubeId)"
        :title="title"
        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
        allowfullscreen
        referrerpolicy="strict-origin-when-cross-origin"
    ></iframe>
    <button v-else type="button" class="video-button" :aria-label="play" @click="start">
        <img v-if="thumbnail" :src="thumbnail" alt="" width="1280" height="720" loading="lazy">
        <span class="video-play" aria-hidden="true"></span>
    </button>
</template>
