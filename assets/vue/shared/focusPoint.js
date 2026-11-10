const STEP = 5;
const BIG_STEP = 25;

export const CENTER = Object.freeze({ x: 50, y: 50 });

const ARROWS = new Map([
    ['ArrowLeft', ['x', -1]],
    ['ArrowRight', ['x', 1]],
    ['ArrowUp', ['y', -1]],
    ['ArrowDown', ['y', 1]],
]);

const EDGES = new Map([
    ['Home', ['x', 0]],
    ['End', ['x', 100]],
    ['PageUp', ['y', 0]],
    ['PageDown', ['y', 100]],
]);

export function snap(value) {
    return Math.min(100, Math.max(0, Math.round(value / STEP) * STEP));
}

export function pointerFocus(clientX, clientY, rect) {
    return { x: snap(((clientX - rect.left) / rect.width) * 100), y: snap(((clientY - rect.top) / rect.height) * 100) };
}

export function keyFocus(focus, key, shift) {
    if (EDGES.has(key)) {
        const [edge, value] = EDGES.get(key);
        return { ...focus, [edge]: value };
    }
    if (!ARROWS.has(key)) return null;
    const [axis, direction] = ARROWS.get(key);
    return { ...focus, [axis]: snap(focus[axis] + direction * (shift ? BIG_STEP : STEP)) };
}

export function objectPosition(focus) {
    return `${focus.x}% ${focus.y}%`;
}
