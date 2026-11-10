<script setup>
import { ExternalLink, Lock } from '@lucide/vue';
import { computed, nextTick, reactive, ref } from 'vue';
import { checkoutPayload, failure, ready, validEmail } from '../checkoutForm.js';

const props = defineProps({
    endpoint: { type: String, required: true },
    product: { type: Object, required: true },
    consents: { type: Array, required: true },
    terms: { type: Object, required: true },
    labels: { type: Object, required: true },
});

const form = reactive({ email: '', consents: [] });
const emailError = ref('');
const message = ref('');
const sending = ref(false);
const status = ref(null);
const email = ref(null);

const complete = computed(() => ready(form, props.product.productType));

function checkEmail() {
    emailError.value = form.email.trim() === '' || validEmail(form.email) ? '' : props.labels.emailInvalid;
}

async function submit() {
    if (!complete.value || sending.value) {
        return;
    }
    sending.value = true;
    emailError.value = '';
    message.value = '';
    try {
        const response = await fetch(props.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Accept-Language': props.product.locale },
            body: JSON.stringify(checkoutPayload(form, props.product)),
        });
        const body = await response.json().catch(() => null);
        if (response.ok && typeof body?.url === 'string') {
            window.location.assign(body.url);
            return;
        }
        const result = failure(response.ok ? 0 : response.status, body, props.labels);
        emailError.value = result.fields.email ?? '';
        message.value = result.message;
    } catch {
        message.value = failure(0, null, props.labels).message;
    }
    sending.value = false;
    await nextTick();
    (emailError.value ? email.value : status.value)?.focus();
}
</script>

<template>
    <form class="checkout-fields" novalidate :aria-busy="sending" @submit.prevent="submit">
        <div class="checkout-field">
            <label for="checkout-email">{{ labels.email }} <span class="checkout-required">{{ labels.required }}</span></label>
            <input id="checkout-email" ref="email" v-model="form.email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="180" :aria-invalid="emailError !== ''" :aria-describedby="emailError ? 'checkout-email-error checkout-email-hint' : 'checkout-email-hint'" @blur="checkEmail">
            <p id="checkout-email-hint" class="checkout-hint">{{ labels.emailHint }}</p>
            <p v-if="emailError" id="checkout-email-error" class="checkout-error">{{ emailError }}</p>
        </div>
        <fieldset class="checkout-consents">
            <legend>{{ labels.consents }}</legend>
            <div v-for="consent in consents" :key="consent.kind" class="checkout-consent">
                <input :id="`consent-${consent.kind}`" v-model="form.consents" type="checkbox" name="consents[]" :value="consent.kind" required>
                <label :for="`consent-${consent.kind}`">{{ consent.text }}</label>
                <a v-if="consent.kind === 'terms'" class="checkout-terms" :href="terms.href" target="_blank" rel="noopener">{{ terms.label }}<ExternalLink :size="14" aria-hidden="true" /></a>
            </div>
        </fieldset>
        <div class="checkout-actions">
            <button type="submit" class="button checkout-submit" :disabled="!complete || sending" :aria-describedby="complete ? null : 'checkout-incomplete'">
                <Lock :size="18" aria-hidden="true" />
                {{ sending ? labels.submitting : labels.submit }}
            </button>
            <p v-if="!complete" id="checkout-incomplete" class="checkout-hint">{{ labels.incomplete }}</p>
            <p ref="status" class="checkout-status" role="alert" tabindex="-1">{{ message }}</p>
        </div>
    </form>
</template>
