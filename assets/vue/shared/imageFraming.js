const TOLERANCE = 0.02;

export const SLOTS = Object.freeze({
    hero: { always: true, frames: [{ key: 'phone', ratio: 1 }, { key: 'desktop', ratio: 16 / 9 }] },
    cover: { always: false, frames: [{ key: 'square', ratio: 1 }] },
    portrait: { always: false, frames: [{ key: 'portrait', ratio: 4 / 5 }] },
});

export function ratioMismatch(imageRatio, slotRatio) {
    return Math.abs(imageRatio - slotRatio) / slotRatio > TOLERANCE;
}

export function needsFraming(slot, imageRatio) {
    return slot.always || (imageRatio !== null && slot.frames.some((frame) => ratioMismatch(imageRatio, frame.ratio)));
}
