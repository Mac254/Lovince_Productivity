import { createMotion, type MotionContext } from '@lovince/motion';
import { createTimeline } from '@lovince/motion/gsap';
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
 */
export function useStaggerIn(container: Ref<HTMLElement | null>, { selector, when }: StaggerOptions): void {
    let context: MotionContext | null = null;
    let played = false;

    const stop = watch(
        [when, container],
        ([ready, el]) => {
            if (played || !ready || !el || prefersReducedMotion()) return;
            played = true;
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
        stop();
        context?.destroy();
    });
}
