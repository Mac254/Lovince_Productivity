import type { MotionConfig } from '@lovince/motion';
import { distance, duration, easing } from '@focus/tokens';

/** GSAP-ready values derived from the motion tokens (seconds, GSAP ease names). */
export const motion = {
    enter: { duration: duration.slow / 1000, ease: easing.out.gsap, distance: distance.sm },
    count: { duration: duration.slower / 1000, ease: easing.out.gsap },
    stagger: 0.05,
} as const;

/** Focus's timing for Lovince motion contexts, so its timelines move like the rest of the app. */
export const motionConfig: MotionConfig = {
    duration: motion.enter.duration,
    ease: motion.enter.ease,
    stagger: motion.stagger,
    respectReducedMotion: true,
};

/** True when the device asks for less motion, or the app's own setting (`data-motion="reduce"`) does. */
export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined') return false;
    return (
        document.documentElement.dataset.motion === 'reduce' ||
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
    );
}
