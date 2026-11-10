<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { filterBeats, formatBpm, formatDuration, idle, nextSort, player, progress, sortBeats } from '../beatTable.js';

const props = defineProps({
    rows: { type: Array, required: true },
    tags: { type: Array, required: true },
    keys: { type: Array, required: true },
    bounds: { type: Object, required: true },
    filter: { type: Object, required: true },
    contact: { type: String, required: true },
    labels: { type: Object, required: true },
});

const chosenTags = ref([...props.filter.tags]);
const key = ref(props.filter.key);
const min = ref(props.filter.min ?? '');
const max = ref(props.filter.max ?? '');
const sort = ref({ by: 'position', direction: 'ascending' });
const state = ref(idle);
const time = ref(0);
const duration = ref(0);
const audio = ref(null);

const shown = computed(() => sortBeats(filterBeats(props.rows, { tags: chosenTags.value, key: key.value, min: min.value, max: max.value }), sort.value));
const count = computed(() => props.labels.counts[shown.value.length]);
const current = computed(() => props.rows.find((row) => row.id === state.value.current) ?? null);
const played = computed(() => progress(time.value, duration.value));

const columns = [
    { by: 'title', label: props.labels.columns.title },
    { by: 'bpm', label: props.labels.columns.bpm },
];

function ariaSort(by) {
    return sort.value.by === by ? sort.value.direction : 'none';
}

function toggleTag(tag) {
    chosenTags.value = chosenTags.value.includes(tag) ? chosenTags.value.filter((chosen) => chosen !== tag) : [...chosenTags.value, tag];
}

function reset() {
    chosenTags.value = [];
    key.value = '';
    min.value = '';
    max.value = '';
}

function apply(next) {
    const changed = next.current !== state.value.current;
    state.value = next;
    const element = audio.value;
    if (next.current === null) {
        element.pause();
        time.value = 0;

        return;
    }
    if (changed) {
        time.value = 0;
        duration.value = 0;
        element.src = current.value.preview;
    }
    if (next.playing) {
        element.play().catch(() => {});
    } else {
        element.pause();
    }
}

function send(event) {
    state.value = player(state.value, event);
}

function ended() {
    apply(player(state.value, { type: 'ended', queue: shown.value.map((row) => row.id) }));
}

function isPlaying(row) {
    return state.value.current === row.id && state.value.playing;
}

onBeforeUnmount(() => audio.value?.pause());
</script>

<template>
    <form class="beat-filters" :aria-label="labels.filters.label" @submit.prevent>
        <fieldset v-if="tags.length" class="beat-filter beat-filter-tags">
            <legend>{{ labels.filters.tags }}</legend>
            <div class="chips">
                <button v-for="tag in tags" :key="tag" type="button" class="chip" :aria-pressed="chosenTags.includes(tag)" @click="toggleTag(tag)"><span>{{ tag }}</span></button>
            </div>
        </fieldset>
        <label class="beat-filter beat-filter-key">
            <span class="beat-filter-label">{{ labels.filters.key }}</span>
            <select v-model="key" name="key">
                <option value="">{{ labels.filters.anyKey }}</option>
                <option v-for="option in keys" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </label>
        <fieldset class="beat-filter beat-filter-bpm">
            <legend>{{ labels.filters.bpm }}</legend>
            <div class="bpm-range">
                <label><span class="visually-hidden">{{ labels.filters.min }}</span><input v-model="min" type="number" name="bpm_min" inputmode="numeric" :min="bounds.min" :max="bounds.max" :placeholder="bounds.min"></label>
                <span class="bpm-dash" aria-hidden="true">–</span>
                <label><span class="visually-hidden">{{ labels.filters.max }}</span><input v-model="max" type="number" name="bpm_max" inputmode="numeric" :min="bounds.min" :max="bounds.max" :placeholder="bounds.max"></label>
            </div>
        </fieldset>
        <div class="beat-filter-actions">
            <button class="beat-reset" type="button" @click="reset">{{ labels.filters.reset }}</button>
        </div>
    </form>
    <p class="beat-count" role="status">{{ count }}</p>
    <table v-if="shown.length" class="beat-table">
        <caption class="visually-hidden">{{ labels.caption }}</caption>
        <thead>
            <tr>
                <th scope="col" class="beat-col-play"><span class="visually-hidden">{{ labels.columns.play }}</span></th>
                <th scope="col" class="beat-col-cover"><span class="visually-hidden">{{ labels.columns.cover }}</span></th>
                <th v-for="column in columns" :key="column.by" scope="col" :class="`beat-col-${column.by}`" :aria-sort="ariaSort(column.by)">
                    <button type="button" class="beat-sort" :aria-label="labels.sort.replace('{column}', column.label)" @click="sort = nextSort(sort, column.by)">{{ column.label }}<span class="beat-sort-arrow" aria-hidden="true"></span></button>
                </th>
                <th scope="col" class="beat-col-key">{{ labels.columns.key }}</th>
                <th scope="col" class="beat-col-tags">{{ labels.columns.tags }}</th>
                <th scope="col" class="beat-col-price">{{ labels.columns.price }}</th>
                <th scope="col" class="beat-col-action"><span class="visually-hidden">{{ labels.columns.action }}</span></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="beat in shown" :id="`beat-${beat.slug}`" :key="beat.id" class="beat-row" :class="{ 'is-current': state.current === beat.id, 'is-playing': isPlaying(beat) }">
                <td class="beat-cell-play">
                    <button type="button" class="beat-play" :aria-pressed="isPlaying(beat)" :aria-label="labels.play.replace('{title}', beat.title)" @click="apply(player(state, { type: 'toggle', id: beat.id }))"><span class="beat-play-icon" aria-hidden="true"></span></button>
                </td>
                <td class="beat-cover">
                    <img v-if="beat.cover" :src="beat.cover" alt="" width="56" height="56" loading="lazy" decoding="async">
                    <span v-else class="cover-blank" aria-hidden="true">{{ beat.title.charAt(0) }}</span>
                </td>
                <th :id="`beat-title-${beat.id}`" scope="row" class="beat-title">
                    {{ beat.title }}
                    <span v-if="state.current === beat.id" class="beat-progress">
                        <progress max="1" :value="played" aria-hidden="true"></progress>
                        <span class="beat-time">{{ formatDuration(time) }} / {{ formatDuration(duration) }}</span>
                    </span>
                </th>
                <td class="beat-bpm">{{ formatBpm(beat.bpm) }}</td>
                <td class="beat-key">{{ beat.keyLabel }}</td>
                <td class="beat-cell-tags">
                    <ul v-if="beat.tags.length" class="beat-tags">
                        <li v-for="tag in beat.tags" :key="tag">{{ tag }}</li>
                    </ul>
                </td>
                <td class="beat-price" :class="{ 'is-request': beat.price === null }">{{ beat.price ?? labels.onRequest }}</td>
                <td class="beat-action"><a class="button" :href="`${contact}&offer=${encodeURIComponent(beat.id)}`" :aria-describedby="`beat-title-${beat.id}`">{{ labels.contact }}</a></td>
            </tr>
        </tbody>
    </table>
    <p v-else class="beat-none">{{ labels.none }}</p>
    <audio ref="audio" preload="none" @play="send({ type: 'play' })" @pause="send({ type: 'pause' })" @error="send({ type: 'pause' })" @ended="ended" @timeupdate="time = $event.target.currentTime" @durationchange="duration = $event.target.duration"></audio>
</template>
