export function spotifyEmbedUrl({ kind, id }) {
    return `https://open.spotify.com/embed/${encodeURIComponent(kind)}/${encodeURIComponent(id)}?theme=0`;
}

export function youtubeEmbedUrl(id) {
    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1&rel=0`;
}
