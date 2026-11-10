<script setup>
import { ArrowLeft, Check } from '@lucide/vue';
import { nextTick, reactive, ref } from 'vue';
import { calendarDay, fill, LIMITS, money, parisDate, parisTime, previewPayload, settle, validate, withdrawalPayload } from '../withdrawalForm.js';

const props = defineProps({
    previewEndpoint: { type: String, required: true },
    endpoint: { type: String, required: true },
    locale: { type: String, required: true },
    labels: { type: Object, required: true },
});

const FIELDS = ['reference', 'email', 'name', 'reason'];

const form = reactive({ reference: '', email: '', name: '', reason: '' });
const step = ref('form');
const preview = ref(null);
const receipt = ref(null);
const errors = ref({});
const message = ref('');
const sending = ref(false);
const root = ref(null);

function fieldError(field) {
    const error = errors.value[field];
    return error === undefined ? null : (props.labels.errors[field]?.[error] ?? error);
}

function describedBy(field, hint = false) {
    return [hint ? `withdrawal-${field}-hint` : null, errors.value[field] === undefined ? null : `withdrawal-${field}-error`].filter(Boolean).join(' ') || null;
}

async function focus() {
    await nextTick();
    const field = step.value === 'form' ? FIELDS.find((name) => errors.value[name] !== undefined) : undefined;
    const target = field ? `#withdrawal-${field}` : (message.value ? '.withdrawal-status' : '.withdrawal-step-title');
    root.value?.querySelector(target)?.focus();
}

async function post(url, payload) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Accept-Language': props.locale },
            body: JSON.stringify(payload),
        });
        return [response.status, await response.json().catch(() => null)];
    } catch {
        return [0, null];
    }
}

async function send(url, payload) {
    sending.value = true;
    message.value = '';
    const current = step.value;
    const [status, body] = await post(url, payload);
    const result = settle(current, status, body, props.labels);
    sending.value = false;
    errors.value = result.fields;
    message.value = result.message;
    step.value = result.step;
    if (result.step === 'confirm' && current === 'form') preview.value = result.data;
    if (result.step === 'done') receipt.value = result.data;
    await focus();
}

async function check() {
    errors.value = validate(form);
    message.value = '';
    if (Object.keys(errors.value).length > 0) {
        message.value = props.labels.invalid;
        await focus();
        return;
    }
    await send(props.previewEndpoint, previewPayload(form));
}

async function confirm() {
    await send(props.endpoint, withdrawalPayload(form, props.locale));
}

async function back() {
    step.value = 'form';
    message.value = '';
    await nextTick();
    root.value?.querySelector('#withdrawal-reference')?.focus();
}
</script>

<template>
    <div ref="root" class="withdrawal-island" :aria-busy="sending">
        <form v-if="step === 'form'" class="withdrawal-fields" novalidate @submit.prevent="check">
            <div class="withdrawal-field">
                <label for="withdrawal-reference">{{ labels.reference }} <span class="withdrawal-optional">{{ labels.required }}</span></label>
                <input id="withdrawal-reference" v-model="form.reference" name="reference" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" required :maxlength="LIMITS.reference" :aria-invalid="errors.reference !== undefined" :aria-describedby="describedBy('reference', true)">
                <p id="withdrawal-reference-hint" class="withdrawal-hint">{{ labels.referenceHint }}</p>
                <p v-if="errors.reference" id="withdrawal-reference-error" class="withdrawal-error">{{ fieldError('reference') }}</p>
            </div>
            <div class="withdrawal-field">
                <label for="withdrawal-email">{{ labels.email }} <span class="withdrawal-optional">{{ labels.required }}</span></label>
                <input id="withdrawal-email" v-model="form.email" name="email" type="email" autocomplete="email" inputmode="email" required :maxlength="LIMITS.email" :aria-invalid="errors.email !== undefined" :aria-describedby="describedBy('email', true)">
                <p id="withdrawal-email-hint" class="withdrawal-hint">{{ labels.emailHint }}</p>
                <p v-if="errors.email" id="withdrawal-email-error" class="withdrawal-error">{{ fieldError('email') }}</p>
            </div>
            <div class="withdrawal-field">
                <label for="withdrawal-name">{{ labels.name }} <span class="withdrawal-optional">{{ labels.optional }}</span></label>
                <input id="withdrawal-name" v-model="form.name" name="name" type="text" autocomplete="name" :aria-invalid="errors.name !== undefined" :aria-describedby="describedBy('name')">
                <p v-if="errors.name" id="withdrawal-name-error" class="withdrawal-error">{{ fieldError('name') }}</p>
            </div>
            <div class="withdrawal-field">
                <label for="withdrawal-reason">{{ labels.reason }} <span class="withdrawal-optional">{{ labels.optional }}</span></label>
                <textarea id="withdrawal-reason" v-model="form.reason" name="reason" rows="4" :aria-invalid="errors.reason !== undefined" :aria-describedby="describedBy('reason', true)"></textarea>
                <p id="withdrawal-reason-hint" class="withdrawal-hint">{{ labels.reasonHint }}</p>
                <p v-if="errors.reason" id="withdrawal-reason-error" class="withdrawal-error">{{ fieldError('reason') }}</p>
            </div>
            <div class="withdrawal-actions">
                <button type="submit" class="button withdrawal-submit" :disabled="sending">{{ sending ? labels.checking : labels.next }}</button>
                <p class="withdrawal-status" role="alert" tabindex="-1">{{ message }}</p>
            </div>
        </form>

        <section v-else-if="step === 'confirm'" class="withdrawal-confirm" aria-labelledby="withdrawal-confirm-title">
            <h2 id="withdrawal-confirm-title" class="withdrawal-step-title" tabindex="-1">{{ labels.confirmTitle }}</h2>
            <dl class="withdrawal-summary">
                <div><dt>{{ labels.order }}</dt><dd class="withdrawal-reference">{{ preview.reference }}</dd></div>
                <div><dt>{{ labels.product }}</dt><dd>{{ preview.productLabel }}</dd></div>
                <div><dt>{{ labels.amount }}</dt><dd>{{ money(preview.amountCents, locale) }}</dd></div>
                <div><dt>{{ labels.paidOn }}</dt><dd>{{ parisDate(preview.paidAt, locale) }}</dd></div>
                <div><dt>{{ labels.lastDay }}</dt><dd>{{ calendarDay(preview.lastDay, locale) }}</dd></div>
            </dl>
            <h3 class="withdrawal-label">{{ labels.what }}</h3>
            <ol class="withdrawal-consequences">
                <li v-for="consequence in labels.consequences" :key="consequence">{{ consequence }}</li>
            </ol>
            <div class="withdrawal-actions">
                <button type="button" class="button withdrawal-submit" :disabled="sending" @click="confirm">{{ sending ? labels.confirming : labels.confirm }}</button>
                <button type="button" class="withdrawal-back" :disabled="sending" @click="back"><ArrowLeft :size="16" aria-hidden="true" />{{ labels.back }}</button>
                <p class="withdrawal-status" role="alert" tabindex="-1">{{ message }}</p>
            </div>
        </section>

        <section v-else class="withdrawal-done" aria-labelledby="withdrawal-done-title">
            <p class="withdrawal-done-mark" aria-hidden="true"><Check :size="28" /></p>
            <h2 id="withdrawal-done-title" class="withdrawal-step-title" tabindex="-1">{{ labels.doneTitle }}</h2>
            <p class="withdrawal-reference">{{ fill(labels.doneReference, { reference: receipt.reference }) }}</p>
            <p class="withdrawal-received">{{ fill(labels.received, { date: parisDate(receipt.receivedAt, locale), time: parisTime(receipt.receivedAt, locale) }) }}</p>
            <p>{{ receipt.acknowledged ? labels.acknowledged : labels.pending }}</p>
        </section>
    </div>
</template>
