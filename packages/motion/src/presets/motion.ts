import { distance, duration, easing } from '@focus/tokens';

/** GSAP-ready values derived from the motion tokens (seconds, GSAP ease names). */
export const motion = {
    enter: { duration: duration.slow / 1000, ease: easing.out.gsap, distance: distance.sm },
    count: { duration: duration.slower / 1000, ease: easing.out.gsap },
    stagger: 0.05,
} as const;

/** True when the device asks for less motion, or the app's own setting (`data-motion="reduce"`) does. */
export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined') return false;
    return (
        document.documentElement.dataset.motion === 'reduce' ||
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
    );
}
