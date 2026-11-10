<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { remaining } from '../countdown.js';

const props = defineProps({
    target: { type: String, required: true },
    labels: { type: Object, required: true },
});

const now = ref(Date.now());
const left = computed(() => remaining(now.value, props.target));
const units = computed(() => ['days', 'hours', 'minutes', 'seconds'].map((unit) => ({
    unit,
    value: String(left.value[unit]).padStart(2, '0'),
    label: props.labels[unit],
})));

let timer;
onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
        if (left.value.done) {
            clearInterval(timer);
        }
    }, 1000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <p v-if="left.done" class="countdown-done">{{ labels.done }}</p>
    <p v-else class="countdown-units" role="timer">
        <span v-for="part in units" :key="part.unit" class="countdown-unit">
            <span class="countdown-value">{{ part.value }}</span>
            <span class="countdown-label">{{ part.label }}</span>
        </span>
    </p>
</template>
