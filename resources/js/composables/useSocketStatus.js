import { ref, computed, onUnmounted } from 'vue'
import { echo } from '@laravel/echo-vue'

/**
 * Maps Echo's four raw connection states into three user-facing buckets.
 */
function toBucket(status) {
    switch (status) {
        case 'connected':
            return { label: 'Live', tone: 'green' }
        case 'connecting':
            return { label: 'Connecting…', tone: 'amber' }
        default: // 'failed', 'disconnected', or anything unexpected
            return { label: 'Offline', tone: 'red' }
    }
}

/**
 * Reactive Reverb/WebSocket connection status for the navbar indicator.
 *
 * Wraps echo().connector.onConnectionChange() — the same mechanism app.js
 * uses — rather than @laravel/echo-vue's useConnectionStatus(), which is
 * Vue-lifecycle-bound in a way the app deliberately moved away from.
 */
export function useSocketStatus() {
    const status = ref('connecting')

    let dispose = () => {}

    try {
        const connection = echo()
        status.value = connection.connectionStatus()
        dispose = connection.connector.onConnectionChange((next) => {
            status.value = next
        })
    } catch {
        // Echo unconfigured/unavailable (e.g. missing VITE_REVERB_APP_KEY):
        // degrade to Offline instead of throwing — broadcasting is an
        // enhancement, never a hard dependency of the page.
        status.value = 'disconnected'
    }

    onUnmounted(() => dispose())

    const bucket = computed(() => toBucket(status.value))

    return { status, bucket }
}
