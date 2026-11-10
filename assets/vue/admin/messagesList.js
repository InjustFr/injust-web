export const visibleMessages = (messages, showHandled) => showHandled ? messages : messages.filter((message) => !message.handled);

export function groupByDate(messages) {
    const groups = [];
    for (const message of messages) {
        const date = message.receivedAt.slice(0, 10);
        const last = groups.at(-1);
        if (last?.date === date) last.items.push(message);
        else groups.push({ date, items: [message] });
    }
    return groups;
}

export const replyHref = (email) => `mailto:${encodeURIComponent(email)}`;
