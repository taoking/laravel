import Hls from 'hls.js';

document.querySelectorAll('[data-hls-player]').forEach((video) => {
    const hlsUrl = video.dataset.hlsUrl;
    const fallbackUrl = video.dataset.fallbackUrl;

    const useFallback = () => {
        if (fallbackUrl && video.currentSrc !== fallbackUrl) {
            video.src = fallbackUrl;
        }
    };

    if (! hlsUrl) {
        useFallback();
        return;
    }

    if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = hlsUrl;
        return;
    }

    if (Hls.isSupported()) {
        const hls = new Hls();

        hls.loadSource(hlsUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                hls.destroy();
                useFallback();
            }
        });

        return;
    }

    useFallback();
});
