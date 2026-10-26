import { gsap } from 'gsap';
import { onBeforeUnmount, ref, watch, type Ref, type WatchSource } from 'vue';
import { motion, prefersReducedMotion } from '../presets/motion';

/** Tweens a displayed integer toward its source value; jumps straight there under reduced motion. */
export function useAnimatedNumber(source: WatchSource<number>): Ref<number> {
    const display = ref(0);
    const state = { value: 0 };
    let tween: gsap.core.Tween | null = null;

    watch(
        source,
        (target) => {
            tween?.kill();
            if (prefersReducedMotion()) {
                state.value = display.value = target;
                return;
            }
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

    onBeforeUnmount(() => tween?.kill());

    return display;
}
