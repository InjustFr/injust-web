<script setup>
import { X } from '@lucide/vue';
import { computed, nextTick, reactive, ref } from 'vue';
import { contactPayload, failure, validate } from '../contactForm.js';

const props = defineProps({
    endpoint: { type: String, required: true },
    locale: { type: String, required: true },
    topic: { type: String, required: true },
    offer: { type: Object, default: null },
    topics: { type: Array, required: true },
    labels: { type: Object, required: true },
});

const form = reactive({ name: '', email: '', topic: props.topic, offer: props.offer?.id ?? null, body: '', website: '' });
const offer = ref(props.offer);
const errors = ref({});
const message = ref('');
const sending = ref(false);
const sent = ref(false);
const root = ref(null);
const status = ref(null);
const done = ref(null);

const showOffer = computed(() => offer.value !== null && form.topic === 'lessons');

function fieldError(field) {
    const error = errors.value[field];
    return error === undefined ? null : (props.labels.errors[field]?.[error] ?? error);
}

function describedBy(field) {
    return errors.value[field] === undefined ? null : `contact-${field}-error`;
}

function removeOffer() {
    offer.value = null;
    form.offer = null;
    root.value.querySelector('#contact-body').focus();
}

async function focusFirstError() {
    await nextTick();
    const field = ['name', 'email', 'topic', 'body'].find((name) => errors.value[name] !== undefined);
    (field ? root.value.querySelector(`#contact-${field}`) : status.value)?.focus();
}

async function submit() {
    const payload = contactPayload(form);
    errors.value = validate(payload);
    message.value = '';
    if (Object.keys(errors.value).length > 0) {
        message.value = props.labels.invalid;
        await focusFirstError();
        return;
    }
    sending.value = true;
    try {
        const response = await fetch(props.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Accept-Language': props.locale },
            body: JSON.stringify(payload),
        });
        if (response.ok) {
            sent.value = true;
            await nextTick();
            done.value?.focus();
            return;
        }
        const problem = await response.json().catch(() => null);
        ({ fields: errors.value, message: message.value } = failure(response.status, problem, props.labels));
    } catch {
        ({ fields: errors.value, message: message.value } = failure(0, null, props.labels));
    } finally {
        sending.value = false;
    }
    await focusFirstError();
}
</script>

<template>
    <div ref="root">
        <div v-if="sent" ref="done" class="contact-done" tabindex="-1">
            <p class="contact-done-title">{{ labels.sentTitle }}</p>
            <p class="contact-done-body">{{ labels.sentBody }}</p>
        </div>
        <form v-else class="contact-fields" novalidate :aria-busy="sending" @submit.prevent="submit">
            <div class="contact-field">
                <label for="contact-name">{{ labels.name }} <span class="contact-required">{{ labels.required }}</span></label>
                <input id="contact-name" v-model="form.name" name="name" type="text" autocomplete="name" required :aria-invalid="errors.name !== undefined" :aria-describedby="describedBy('name')">
                <p v-if="errors.name" id="contact-name-error" class="contact-error">{{ fieldError('name') }}</p>
            </div>
            <div class="contact-field">
                <label for="contact-email">{{ labels.email }} <span class="contact-required">{{ labels.required }}</span></label>
                <input id="contact-email" v-model="form.email" name="email" type="email" autocomplete="email" inputmode="email" required :aria-invalid="errors.email !== undefined" :aria-describedby="describedBy('email')">
                <p v-if="errors.email" id="contact-email-error" class="contact-error">{{ fieldError('email') }}</p>
            </div>
            <div class="contact-field">
                <label for="contact-topic">{{ labels.topic }}</label>
                <select id="contact-topic" v-model="form.topic" name="topic" :aria-invalid="errors.topic !== undefined" :aria-describedby="describedBy('topic')">
                    <option v-for="item in topics" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <p v-if="errors.topic" id="contact-topic-error" class="contact-error">{{ fieldError('topic') }}</p>
            </div>
            <div v-if="showOffer" class="contact-offer">
                <span class="contact-offer-label">{{ labels.offer }}</span>
                <span class="contact-offer-name">{{ offer.label }}</span>
                <button type="button" class="contact-offer-remove" :aria-label="`${labels.removeOffer}: ${offer.label}`" @click="removeOffer"><X :size="18" aria-hidden="true" /></button>
            </div>
            <p v-if="errors.offerRef" class="contact-error">{{ fieldError('offerRef') }}</p>
            <div class="contact-field">
                <label for="contact-body">{{ labels.body }} <span class="contact-required">{{ labels.required }}</span></label>
                <textarea id="contact-body" v-model="form.body" name="body" rows="8" required :aria-invalid="errors.body !== undefined" :aria-describedby="describedBy('body')"></textarea>
                <p v-if="errors.body" id="contact-body-error" class="contact-error">{{ fieldError('body') }}</p>
            </div>
            <div class="contact-trap" aria-hidden="true">
                <label for="contact-website">{{ labels.website }}</label>
                <input id="contact-website" v-model="form.website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="contact-actions">
                <button type="submit" class="button contact-send" :disabled="sending">{{ sending ? labels.sending : labels.send }}</button>
                <p ref="status" class="contact-status" role="alert" tabindex="-1">{{ message }}</p>
            </div>
        </form>
    </div>
</template>
