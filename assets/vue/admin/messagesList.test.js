import { describe, expect, it } from 'vitest';
import { groupByDate, replyHref, visibleMessages } from './messagesList.js';

const messages = [
    { id: '1', receivedAt: '2026-11-10T09:00:00+01:00', handled: false },
    { id: '2', receivedAt: '2026-11-10T08:00:00+01:00', handled: true },
    { id: '3', receivedAt: '2026-11-09T23:00:00+01:00', handled: false },
];

describe('visibleMessages', () => {
    it('hides handled messages by default', () => {
        expect(visibleMessages(messages, false).map((m) => m.id)).toEqual(['1', '3']);
    });

    it('keeps everything when asked', () => {
        expect(visibleMessages(messages, true).map((m) => m.id)).toEqual(['1', '2', '3']);
    });
});

describe('groupByDate', () => {
    it('groups consecutive messages by calendar day, keeping order', () => {
        expect(groupByDate(messages)).toEqual([
            { date: '2026-11-10', items: [messages[0], messages[1]] },
            { date: '2026-11-09', items: [messages[2]] },
        ]);
    });

    it('returns nothing for nothing', () => {
        expect(groupByDate([])).toEqual([]);
    });
});

describe('replyHref', () => {
    it('encodes the address so it cannot add headers to the reply', () => {
        expect(replyHref('a?cc=x@evil.test&body=hi@example.com')).toBe('mailto:a%3Fcc%3Dx%40evil.test%26body%3Dhi%40example.com');
    });
});
