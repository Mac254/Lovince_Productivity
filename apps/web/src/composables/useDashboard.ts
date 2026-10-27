import { onBeforeUnmount, onMounted } from 'vue';
import { useDashboardStore } from '@/stores/dashboard';

/** For views that show the dashboard snapshot: loads it if needed and keeps it fresh while open. */
export function useDashboard() {
    const dashboard = useDashboardStore();
    let detach: (() => void) | undefined;
    onMounted(() => (detach = dashboard.attach()));
    onBeforeUnmount(() => detach?.());
    return dashboard;
}
