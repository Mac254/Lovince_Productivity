import { onBeforeUnmount, ref, watch, type Ref, type WatchSource } from 'vue';
import { motion, prefersReducedMotion } from '../presets/motion';

/**
 * Tweens a displayed integer toward its source value; jumps straight there under reduced motion.
 * GSAP loads on first use, so it stays off the app's first download.
 */
export function useAnimatedNumber(source: WatchSource<number>): Ref<number> {
    const display = ref(0);
    const state = { value: 0 };
    let tween: { kill(): unknown } | null = null;
    let latest = 0;
    let unmounted = false;

    watch(
        source,
        async (target) => {
            const current = ++latest;
            tween?.kill();
            if (prefersReducedMotion()) {
                state.value = display.value = target;
                return;
            }
            const { gsap } = await import('gsap');
            if (current !== latest || unmounted) return;
            tween = gsap.to(state, {
                value: target,
                duration: motion.count.duration,
                ease: motion.count.ease,
                onUpdate: () => {
                    display.value = Math.round(state.value);
                },
            });
        },
        { immediate: true },
    );

    onBeforeUnmount(() => {
        unmounted = true;
        tween?.kill();
    });

    return display;
}
