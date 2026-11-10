let session;

export function useSession() {
    if (session === undefined) {
        const element = document.getElementById('app-session');
        session = element ? JSON.parse(element.textContent) : null;
    }
    return session;
}
