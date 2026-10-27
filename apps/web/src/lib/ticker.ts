/**
 * A soft mechanical tick on every wall-clock second, synthesised with Web Audio (filtered noise,
 * alternating tick and tock). Ticks are scheduled on the audio clock, so they stay even when the
 * page is busy. Silently does nothing where audio is unavailable or blocked.
 */
export function createTicker(volume = 0.5) {
    let ctx: AudioContext | null = null;
    let noise: AudioBuffer | null = null;
    let timer: number | undefined;
    let nextSecond = 0;

    const resume = () => void ctx?.resume();

    function click(at: number, tock: boolean): void {
        if (!ctx || !noise) return;
        const source = ctx.createBufferSource();
        source.buffer = noise;
        const filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.value = tock ? 2600 : 3400;
        filter.Q.value = 6;
        const gain = ctx.createGain();
        gain.gain.setValueAtTime(volume, at);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.035);
        source.connect(filter).connect(gain).connect(ctx.destination);
        source.start(at);
        source.stop(at + 0.04);
    }

    function schedule(): void {
        if (!ctx || ctx.state !== 'running' || document.hidden) {
            nextSecond = 0;
            return;
        }
        const now = Date.now();
        const toAudio = (ms: number) => ctx!.currentTime + (ms - now) / 1000;
        if (!nextSecond || nextSecond < now) nextSecond = Math.ceil(now / 1000) * 1000;
        while (nextSecond < now + 600) {
            click(toAudio(nextSecond), (nextSecond / 1000) % 2 === 1);
            nextSecond += 1000;
        }
    }

    function start(): void {
        if (ctx) return;
        try {
            const AudioCtx =
                window.AudioContext ??
                (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
            if (!AudioCtx) return;
            ctx = new AudioCtx();
            noise = ctx.createBuffer(1, Math.floor(ctx.sampleRate * 0.04), ctx.sampleRate);
            const samples = noise.getChannelData(0);
            for (let i = 0; i < samples.length; i++) samples[i] = Math.random() * 2 - 1;
            // Browsers keep audio suspended until the page is interacted with.
            document.addEventListener('pointerdown', resume);
            document.addEventListener('keydown', resume);
            resume();
            timer = window.setInterval(schedule, 250);
        } catch {
            stop();
        }
    }

    function stop(): void {
        window.clearInterval(timer);
        document.removeEventListener('pointerdown', resume);
        document.removeEventListener('keydown', resume);
        void ctx?.close().catch(() => undefined);
        ctx = null;
        noise = null;
        nextSecond = 0;
    }

    return { start, stop };
}
