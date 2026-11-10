import { expect, test } from 'vitest';
import {
    checkoutBlocked,
    generalForm,
    generalPayload,
    identityForm,
    identityPayload,
    imageUrl,
    identityErrors,
    imageUpload,
    formatDay,
    isSirenValid,
    legalDraft,
    legalDraftChanged,
    legalPublicPath,
    LEGAL_KINDS,
} from './settingsForm.js';

const settings = {
    contactEmail: 'a@b.c',
    bookingUrl: null,
    vatMentions: { consumer: 'c', euBusiness: 'e', nonEuBusiness: 'n' },
    seoTitle: { fr: 'T', en: '' },
    seoDescription: { fr: '', en: 'D' },
    searchIndexing: true,
    legal: { name: 'N', siren: '732829320', complete: false },
};

test('a SIREN is valid with 9 digits and a Luhn checksum, spaces ignored', () => {
    expect(isSirenValid('732 829 320')).toBe(true);
    expect(isSirenValid('900000001')).toBe(true);
    expect(isSirenValid('732829321')).toBe(false);
    expect(isSirenValid('12345678')).toBe(false);
    expect(isSirenValid('12345678a')).toBe(false);
    expect(isSirenValid('')).toBe(false);
});

test('identity form copies the legal identity and payload trims it', () => {
    const form = identityForm({ ...settings.legal, complete: true, ignored: 1 });
    expect(form).not.toHaveProperty('complete');
    expect(form).not.toHaveProperty('ignored');
    expect(form.name).toBe('N');
    expect(identityPayload({ name: '  Louis ', siren: ' 732829320 ' })).toEqual({ name: 'Louis', siren: '732829320' });
});

test('identity form defaults missing fields to empty strings', () => {
    expect(identityForm({}).hostName).toBe('');
    expect(identityForm({ hostPhone: '+33 1' }).hostPhone).toBe('+33 1');
    expect(identityForm({})).toHaveProperty('hostPhone', '');
    expect(identityForm({}).businessForm).toBe('EI');
});

test('general form and payload map a blank booking url to null', () => {
    const form = generalForm(settings);
    expect(form.bookingUrl).toBe('');
    expect(form.seoTitle).toEqual({ fr: 'T', en: '' });
    expect(generalPayload(form).bookingUrl).toBeNull();
    expect(generalPayload({ ...form, bookingUrl: ' https://x.y ' }).bookingUrl).toBe('https://x.y');
    expect(generalPayload(form).searchIndexing).toBe(true);
});

test('general form does not share objects with the settings', () => {
    const form = generalForm(settings);
    form.seoTitle.fr = 'changed';
    form.vatMentions.consumer = 'changed';
    expect(settings.seoTitle.fr).toBe('T');
    expect(settings.vatMentions.consumer).toBe('c');
});

test('checkout is blocked while the identity is incomplete, unless already on', () => {
    expect(checkoutBlocked({ onlineCheckout: false, legal: { complete: false } })).toBe(true);
    expect(checkoutBlocked({ onlineCheckout: false, legal: { complete: true } })).toBe(false);
    expect(checkoutBlocked({ onlineCheckout: true, legal: { complete: false } })).toBe(false);
});

test('image url and public legal paths per locale', () => {
    expect(imageUrl(null)).toBeNull();
    expect(imageUrl('01ABC')).toBe('/media/images/01ABC.webp');
    expect(legalPublicPath('mentions', 'en')).toBe('/legal/mentions');
    expect(legalPublicPath('cgv-lessons', 'fr')).toBe('/fr/legal/cgv-lessons');
    expect(LEGAL_KINDS).toEqual(['mentions', 'privacy', 'cgv-lessons', 'cgv-production', 'cgv-beats', 'licence']);
});

test('legal draft copies the body and detects changes', () => {
    const document = { kind: 'mentions', body: { fr: 'a', en: 'b' }, version: 1 };
    const draft = legalDraft(document);
    expect(draft).toEqual({ fr: 'a', en: 'b' });
    expect(legalDraftChanged(document, draft)).toBe(false);
    expect(legalDraftChanged(document, { fr: 'a', en: 'c' })).toBe(true);
    expect(legalDraftChanged(undefined, { fr: '', en: '' })).toBe(false);
    expect(legalDraft(undefined)).toEqual({ fr: '', en: '' });
});

test('the identity form flags a SIREN that fails the checksum, and accepts an empty one', () => {
    expect(identityErrors({ siren: ' 732829321 ' })).toEqual({ siren: 'admin.settings.identity.sirenInvalid' });
    expect(identityErrors({ siren: '732 829 320' })).toEqual({});
    expect(identityErrors({ siren: '  ' })).toEqual({});
});

test('an image upload sends the first picked file as multipart field file', () => {
    const file = new Blob(['png']);
    const body = imageUpload([file]);
    expect(body.get('file')).toBeInstanceOf(Blob);
    expect(body.getAll('file')).toHaveLength(1);
    expect(imageUpload([])).toBeNull();
});

test('a day is formatted for the given locale', () => {
    expect(formatDay('2026-11-10T08:00:00+00:00', 'en-GB')).toBe('10 Nov 2026');
    expect(formatDay('2026-11-10T08:00:00+00:00', 'fr-FR')).toBe('10 nov. 2026');
});

test('google verification token is kept, trimmed and always sent', () => {
    const form = generalForm({ ...settings, googleSiteVerification: 'abcDEF_123-xyz' });
    expect(form.googleSiteVerification).toBe('abcDEF_123-xyz');
    expect(generalForm(settings).googleSiteVerification).toBe('');
    expect(generalPayload({ ...form, googleSiteVerification: '  tok_en-1234567  ' }).googleSiteVerification).toBe('tok_en-1234567');
});

test('the seller VAT number is part of the identity form, empty by default, and trimmed in the payload', () => {
    expect(identityForm({}).vatNumber).toBe('');
    expect(identityForm({ vatNumber: 'FR12443061841' }).vatNumber).toBe('FR12443061841');
    expect(identityPayload({ ...identityForm({}), vatNumber: ' FR12443061841 ' }).vatNumber).toBe('FR12443061841');
});

test('the VAT number is checked loosely, spaces ignored, empty allowed', () => {
    expect(identityErrors({ siren: '', vatNumber: '' })).toEqual({});
    expect(identityErrors({ siren: '', vatNumber: 'fr 12 443061841' })).toEqual({});
    expect(identityErrors({ siren: '', vatNumber: '12' })).toEqual({ vatNumber: 'admin.settings.identity.vatNumberInvalid' });
    expect(identityErrors({ siren: '', vatNumber: 'FR1' })).toEqual({ vatNumber: 'admin.settings.identity.vatNumberInvalid' });
});
