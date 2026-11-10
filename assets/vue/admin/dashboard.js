const filled = (text) => `${text.fr}${text.en}`.trim() !== '';

const step = (key, done, name) => ({ key, done, to: { name } });

export function launchChecklist({ settings, music }) {
    const { releases, socialLinks, text } = music;

    return [
        step('heroImage', settings.heroImage !== null, 'settings'),
        step('featuredRelease', releases.some((release) => release.featured), 'music'),
        step('covers', releases.length > 0 && releases.every((release) => release.cover !== null), 'music'),
        step('bio', filled(text.bio) || filled(text.manifesto), 'music'),
        step('socialLinks', socialLinks.length > 0, 'music'),
        step('contactEmail', settings.contactEmail.trim() !== '', 'settings'),
        step('legalIdentity', settings.legal.complete, 'settings'),
        step('searchIndexing', settings.searchIndexing, 'settings'),
    ];
}

export function sectionRows({ settings, music, lessons, production }) {
    const { sections } = settings;

    return [
        { key: 'music', enabled: true, count: music.releases.length + music.videos.length, to: { name: 'music' } },
        { key: 'lessons', enabled: sections.lessons, count: lessons.offers.length, to: { name: 'lessons' } },
        { key: 'production', enabled: sections.production, count: production.services.length + production.portfolio.length, to: { name: 'production' } },
        { key: 'beats', enabled: sections.beats, count: null, to: null },
        { key: 'checkout', enabled: settings.onlineCheckout, count: null, to: { name: 'settings' } },
    ];
}

export const latestMessages = (messages) => messages
    .filter((message) => !message.handled)
    .toSorted((a, b) => b.receivedAt.localeCompare(a.receivedAt))
    .slice(0, 3);
