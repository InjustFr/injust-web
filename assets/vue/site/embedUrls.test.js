import { describe, expect, it } from 'vitest';
import { spotifyEmbedUrl, youtubeEmbedUrl } from './embedUrls.js';

describe('spotifyEmbedUrl', () => {
    it('points at the Spotify embed player for the kind and id', () => {
        expect(spotifyEmbedUrl({ kind: 'album', id: '4aawyAB9vmqN3uQ7FjRGTy' })).toBe('https://open.spotify.com/embed/album/4aawyAB9vmqN3uQ7FjRGTy?theme=0');
        expect(spotifyEmbedUrl({ kind: 'track', id: '11dFghVXANMlKmJXsNCbNl' })).toBe('https://open.spotify.com/embed/track/11dFghVXANMlKmJXsNCbNl?theme=0');
    });

    it('escapes what it is given', () => {
        expect(spotifyEmbedUrl({ kind: 'album/../x', id: 'a?b' })).toBe('https://open.spotify.com/embed/album%2F..%2Fx/a%3Fb?theme=0');
    });
});

describe('youtubeEmbedUrl', () => {
    it('plays the video on youtube-nocookie right away', () => {
        expect(youtubeEmbedUrl('dQw4w9WgXcQ')).toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0');
    });

    it('escapes the id', () => {
        expect(youtubeEmbedUrl('a/b?c')).toBe('https://www.youtube-nocookie.com/embed/a%2Fb%3Fc?autoplay=1&rel=0');
    });
});
