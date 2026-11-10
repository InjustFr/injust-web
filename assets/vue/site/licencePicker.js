export function licenceOptions(beat, tiers) {
    return tiers.map((tier) => ({ ...tier, url: beat.licences[tier.id] ?? null }));
}

export function capLabel(cap, locale, unlimited) {
    return cap === null ? unlimited : new Intl.NumberFormat(locale).format(cap);
}
