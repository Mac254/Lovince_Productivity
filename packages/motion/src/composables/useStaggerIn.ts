import { gsap } from 'gsap';
import { onBeforeUnmount, watch, type Ref, type WatchSource } from 'vue';
import { motion, prefersReducedMotion } from '../presets/motion';

interface StaggerOptions {
    /** Children to animate, relative to the container. */
    selector: string;
    /** Runs the first time this becomes truthy — never again on later data refreshes. */
    when: WatchSource<unknown>;
}

/**
 * Fades and lifts a container's children in once, using opacity and transform only.
 * Never blocks interaction; skipped entirely under reduced motion.
 */
export function useStaggerIn(container: Ref<HTMLElement | null>, { selector, when }: StaggerOptions): void {
    let ctx: gsap.Context | null = null;
    let played = false;

    const stop = watch(
        [when, container],
        ([ready, el]) => {
            if (played || !ready || !el || prefersReducedMotion()) return;
            played = true;
            ctx = gsap.context(() => {
                gsap.from(selector, {
                    autoAlpha: 0,
                    y: motion.enter.distance,
                    duration: motion.enter.duration,
                    ease: motion.enter.ease,
                    stagger: motion.stagger,
                    clearProps: 'all',
                });
            }, el);
        },
        { flush: 'post', immediate: true },
    );

    onBeforeUnmount(() => {
        stop();
        ctx?.revert();
    });
}
