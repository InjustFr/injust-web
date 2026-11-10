export const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const length = (text) => [...text].length;

export function failure(status, problem, labels) {
    const violations = problem?.violations ?? [];
    if (violations.length > 0) {
        return { fields: Object.fromEntries(violations.map((violation) => [violation.propertyPath, violation.title])), message: labels.invalid };
    }
    const fallback = { 422: labels.invalid, 429: labels.tooMany }[status] ?? labels.error;

    return { fields: {}, message: problem?.detail ?? fallback };
}
