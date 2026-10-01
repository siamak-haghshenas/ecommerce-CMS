import { computed, onScopeDispose, ref } from 'vue';

/** A per-second countdown, e.g. for "resend code in 01:24". */
export function useCountdown() {
    const endsAt = ref(0);
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | undefined;

    const remainingSeconds = computed(() => Math.max(0, Math.ceil((endsAt.value - now.value) / 1000)));
    const isRunning = computed(() => remainingSeconds.value > 0);
    const formatted = computed(() => {
        const minutes = Math.floor(remainingSeconds.value / 60);
        const seconds = remainingSeconds.value % 60;

        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    });

    function stop(): void {
        if (timer !== undefined) {
            clearInterval(timer);
            timer = undefined;
        }
    }

    /** Count down to an absolute timestamp (ms). */
    function startUntil(timestamp: number): void {
        stop();
        endsAt.value = timestamp;
        now.value = Date.now();

        if (!isRunning.value) {
            return;
        }

        timer = setInterval(() => {
            now.value = Date.now();

            if (!isRunning.value) {
                stop();
            }
        }, 1000);
    }

    function start(seconds: number): void {
        startUntil(Date.now() + seconds * 1000);
    }

    onScopeDispose(stop);

    return { remainingSeconds, isRunning, formatted, start, startUntil, stop };
}
