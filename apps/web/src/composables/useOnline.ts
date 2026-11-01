export function useOnline(onReconnect?: () => void) {
    const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);

    function update(): void {
        const was = online.value;
        online.value = navigator.onLine;
        if (!was && online.value) onReconnect?.();
    }

    onMounted(() => {
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
    });
    onUnmounted(() => {
        window.removeEventListener('online', update);
        window.removeEventListener('offline', update);
    });

    return online;
}
