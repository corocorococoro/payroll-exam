import { usePage } from '@inertiajs/vue3';

export function useSoundEffects() {
    function tone(frequency: number, duration: number, delay = 0) {
        const user = usePage().props.auth?.user as
            { sound_enabled?: boolean } | undefined;

        if (
            user?.sound_enabled === false ||
            typeof AudioContext === 'undefined'
        ) {
            return;
        }

        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.frequency.value = frequency;
        oscillator.type = 'sine';
        gain.gain.setValueAtTime(0.12, context.currentTime + delay);
        gain.gain.exponentialRampToValueAtTime(
            0.001,
            context.currentTime + delay + duration,
        );
        oscillator.connect(gain).connect(context.destination);
        oscillator.start(context.currentTime + delay);
        oscillator.stop(context.currentTime + delay + duration);
    }

    return {
        correct: () => {
            tone(660, 0.15);
            tone(880, 0.18, 0.12);
        },
        incorrect: () => {
            tone(260, 0.25);
        },
        complete: () => {
            tone(523, 0.15);
            tone(659, 0.15, 0.12);
            tone(784, 0.3, 0.24);
        },
    };
}
