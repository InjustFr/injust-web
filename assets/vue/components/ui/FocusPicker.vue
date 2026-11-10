<script setup>
import { computed } from 'vue';
import { keyFocus, objectPosition, pointerFocus } from '../../shared/focusPoint.js';

defineProps({
    src: { type: String, required: true },
    label: { type: String, required: true },
    valueText: { type: String, required: true },
    frames: { type: Array, required: true },
});
const focus = defineModel({ type: Object, required: true });
const position = computed(() => objectPosition(focus.value));

function point(event) {
    focus.value = pointerFocus(event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
}

function press(event) {
    event.currentTarget.setPointerCapture(event.pointerId);
    point(event);
}

function drag(event) {
    if (event.currentTarget.hasPointerCapture(event.pointerId)) point(event);
}

function key(event) {
    const moved = keyFocus(focus.value, event.key, event.shiftKey);
    if (!moved) return;
    event.preventDefault();
    focus.value = moved;
}
</script>

<template>
    <div class="focus">
        <div
            class="focus__image"
            role="slider"
            tabindex="0"
            :aria-label="label"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="focus.x"
            :aria-valuetext="valueText"
            @pointerdown="press"
            @pointermove="drag"
            @keydown="key"
        >
            <img :src="src" alt="" draggable="false" />
            <span class="focus__mark" :style="{ left: `${focus.x}%`, top: `${focus.y}%` }" aria-hidden="true" />
        </div>
        <div class="focus__frames">
            <figure v-for="frame in frames" :key="frame.label" class="focus__frame">
                <img :src="src" alt="" :style="{ aspectRatio: frame.ratio, objectPosition: position }" />
                <figcaption>{{ frame.label }}</figcaption>
            </figure>
        </div>
    </div>
</template>

<style scoped>
.focus { display: flex; flex-wrap: wrap; align-items: flex-start; gap: var(--space-4); }
.focus__image { position: relative; display: inline-block; max-width: 100%; line-height: 0; cursor: crosshair; touch-action: none; user-select: none; border-radius: var(--radius); }
.focus__image:focus-visible { outline: 0.125rem solid var(--color-accent); outline-offset: 0.125rem; }
.focus__image img { max-width: min(100%, 28rem); max-height: 50vh; border-radius: var(--radius); }
.focus__mark { position: absolute; width: 1.5rem; height: 1.5rem; border: 0.125rem solid #fff; border-radius: 50%; box-shadow: 0 0 0 0.125rem rgb(0 0 0 / 0.6); transform: translate(-50%, -50%); pointer-events: none; }
.focus__mark::before, .focus__mark::after { content: ''; position: absolute; background: #fff; box-shadow: 0 0 0 0.0625rem rgb(0 0 0 / 0.6); }
.focus__mark::before { left: 50%; top: -0.5rem; bottom: -0.5rem; width: 0.125rem; transform: translateX(-50%); }
.focus__mark::after { top: 50%; left: -0.5rem; right: -0.5rem; height: 0.125rem; transform: translateY(-50%); }
.focus__frames { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--space-3); }
.focus__frame { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; }
.focus__frame img { height: 9rem; width: auto; object-fit: cover; border: 0.0625rem solid var(--color-border); border-radius: var(--radius); background: var(--color-hover); }
.focus__frame figcaption { color: var(--color-muted); font-size: var(--font-size-xs); }
</style>
