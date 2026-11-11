import type { MotionContext } from '@lovince/motion';
import { onBeforeUnmount, watch, type Ref, type WatchSource } from 'vue';
import { motion, motionConfig, prefersReducedMotion } from '../presets/motion';

interface StaggerOptions {
    /** Children to animate, relative to the container. */
    selector: string;
    /** Runs the first time this becomes truthy — never again on later data refreshes. */
    when: WatchSource<unknown>;
}

/**
 * Fades and lifts a container's children in once, using opacity and transform only.
 * A Lovince motion context owns the timeline, so unmounting mid-entrance reverts it cleanly.
 * Never blocks interaction; skipped entirely under reduced motion (the device's or the app's).
 * GSAP loads on first use, so it stays off the app's first download.
 */
export function useStaggerIn(container: Ref<HTMLElement | null>, { selector, when }: StaggerOptions): void {
    let context: MotionContext | null = null;
    let played = false;
    let unmounted = false;

    const stop = watch(
        [when, container],
        async ([ready, el]) => {
            if (played || !ready || !el || prefersReducedMotion()) return;
            played = true;
            const [{ createMotion }, { createTimeline }] = await Promise.all([
                import('@lovince/motion'),
                import('@lovince/motion/gsap'),
            ]);
            if (unmounted) return;
            context = createMotion({ root: el, config: motionConfig });
            createTimeline(
                (timeline, scope) => {
                    // From, not fromTo, and props cleared after: no transform lingers to trap menus in a stacking context.
                    timeline.from(scope.select(selector), {
                        autoAlpha: 0,
                        y: motion.enter.distance,
                        stagger: motion.stagger,
                        clearProps: 'all',
                    });
                },
                { context },
            );
        },
        { flush: 'post', immediate: true },
    );

    onBeforeUnmount(() => {
        unmounted = true;
        stop();
        context?.destroy();
    });
}
