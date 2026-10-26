/**
 * A short two-note chime made with Web Audio — no audio files to load.
 * Silently does nothing where audio is unavailable or blocked.
 */
export function playChime(): void {
    try {
        const AudioCtx =
            window.AudioContext ??
            (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const notes = [
            { frequency: 880, at: 0 },
            { frequency: 1318.5, at: 0.18 },
        ];
        for (const { frequency, at } of notes) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime + at);
            gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.6);
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + at);
            osc.stop(ctx.currentTime + at + 0.65);
        }
        window.setTimeout(() => void ctx.close(), 1200);
    } catch {
        // Audio is a nicety; the visual state change still happens.
    }
}
