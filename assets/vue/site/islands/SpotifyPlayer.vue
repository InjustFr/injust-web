<script setup>
import { nextTick, ref } from 'vue';
import { spotifyEmbedUrl } from '../embedUrls.js';

const props = defineProps({
    kind: { type: String, required: true },
    id: { type: String, required: true },
    title: { type: String, required: true },
    load: { type: String, required: true },
    notice: { type: String, required: true },
});

const loaded = ref(false);
const frame = ref(null);

async function play() {
    loaded.value = true;
    await nextTick();
    frame.value?.focus();
}
</script>

<template>
    <iframe
        v-if="loaded"
        ref="frame"
        :class="['spotify-frame', `spotify-frame-${props.kind}`]"
        :src="spotifyEmbedUrl(props)"
        :title="title"
        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
        referrerpolicy="strict-origin-when-cross-origin"
    ></iframe>
    <button v-else type="button" class="spotify-load" @click="play">
        <span class="spotify-load-label">{{ load }}</span>
        <span class="spotify-notice">{{ notice }}</span>
    </button>
</template>
