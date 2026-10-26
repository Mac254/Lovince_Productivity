import { distance, duration, easing } from '@focus/tokens';

/** GSAP-ready values derived from the motion tokens (seconds, GSAP ease names). */
export const motion = {
    enter: { duration: duration.slow / 1000, ease: easing.out.gsap, distance: distance.md },
    count: { duration: duration.slower / 1000, ease: easing.out.gsap },
    stagger: 0.05,
} as const;

export function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}
