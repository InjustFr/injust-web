import { createI18n } from 'vue-i18n';
import { currentLanguage } from './locale.js';

const files = import.meta.glob('./*/*.json', { eager: true, import: 'default' });

const messages = {};
for (const [path, content] of Object.entries(files)) {
    const [, language, namespace] = path.match(/^\.\/([a-z]{2})\/([a-zA-Z-]+)\.json$/);
    messages[language] = { ...messages[language], [namespace]: content };
}

const twoFormsCountingZeroAsOne = (choice, choicesLength) => {
    if (choicesLength === 3) return Math.min(choice, 2);
    return choice <= 1 ? 0 : 1;
};

export const i18n = createI18n({
    legacy: false,
    locale: currentLanguage(),
    fallbackLocale: 'en',
    messages,
    pluralRules: { fr: twoFormsCountingZeroAsOne },
});

export const t = (...args) => i18n.global.t(...args);
