import { prefersReducedMotion } from '@focus/motion';
import { duration } from '@focus/tokens';
import type { ApexOptions } from 'apexcharts';

/**
 * Chart styling read from the live design tokens, so charts can never drift from the theme.
 * Only semantic roles are read — never primitives.
 */
export function useChartTheme() {
    const css = getComputedStyle(document.documentElement);
    const token = (name: string) => css.getPropertyValue(`--${name}`).trim();

    const colors = {
        series1: token('chart-1'),
        series2: token('chart-2'),
        grid: token('chart-grid'),
        text: token('fg'),
        muted: token('fg-muted'),
        surface: token('surface'),
        heat: [0, 1, 2, 3, 4].map((step) => token(`heat-${step}`)),
    };

    const base = {
        chart: {
            fontFamily: token('font-sans') || 'Inter Variable, sans-serif',
            foreColor: colors.muted,
            toolbar: { show: false },
            zoom: { enabled: false },
            parentHeightOffset: 0,
            animations: { enabled: !prefersReducedMotion(), speed: duration.slower },
        },
        grid: { borderColor: colors.grid, strokeDashArray: 0, padding: { left: 8, right: 8 } },
        dataLabels: { enabled: false },
        legend: {
            position: 'top' as const,
            horizontalAlign: 'left' as const,
            fontWeight: 600,
            labels: { colors: colors.text },
            markers: { size: 6 },
        },
        tooltip: { theme: 'dark' },
        states: { active: { filter: { type: 'none' } } },
    } satisfies ApexOptions;

    return { colors, base };
}
