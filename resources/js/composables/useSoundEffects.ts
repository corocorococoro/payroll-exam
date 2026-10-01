import { usePage } from '@inertiajs/vue3';
import { onScopeDispose } from 'vue';

type Note = [frequency: number, duration: number, delay?: number];

export function useSoundEffects() {
    const page = usePage();
    let context: AudioContext | null = null;

    function unlock() {
        if (
            page.props.auth?.user?.sound_enabled === false ||
            typeof AudioContext === 'undefined'
        ) {
            return;
        }

        try {
            context ??= new AudioContext();

            if (context.state === 'suspended') {
                void context.resume().catch(() => undefined);
            }
        } catch {
            // 効果音の利用可否は採点や学習を止めない。
        }
    }

    function play(notes: Note[]) {
        unlock();

        if (
            !context ||
            context.state !== 'running' ||
            page.props.auth?.user?.sound_enabled === false
        ) {
            return;
        }

        try {
            const start = context.currentTime;

            for (const [frequency, duration, delay = 0] of notes) {
                const oscillator = context.createOscillator();
                const gain = context.createGain();
                oscillator.frequency.value = frequency;
                oscillator.type = 'sine';
                gain.gain.setValueAtTime(0, start + delay);
                gain.gain.linearRampToValueAtTime(0.09, start + delay + 0.012);
                gain.gain.exponentialRampToValueAtTime(
                    0.001,
                    start + delay + duration,
                );
                oscillator.connect(gain).connect(context.destination);
                oscillator.onended = () => {
                    oscillator.disconnect();
                    gain.disconnect();
                };
                oscillator.start(start + delay);
                oscillator.stop(start + delay + duration);
            }
        } catch {
            // 音声デバイスが切り替わった場合も学習を続ける。
        }
    }

    onScopeDispose(() => {
        void context?.close().catch(() => undefined);
        context = null;
    });

    return {
        unlock,
        correct: (combo = 1) => {
            const pitch = 2 ** (Math.min(Math.max(0, combo - 1), 7) / 12);
            const notes: Note[] = [
                [660 * pitch, 0.12],
                [880 * pitch, 0.2, 0.08],
            ];

            if (combo === 3 || combo === 5 || combo % 10 === 0) {
                notes.push(
                    [1047 * pitch, 0.25, 0.16],
                    [1319 * pitch, 0.3, 0.24],
                );
            }

            play(notes);
        },
        incorrect: () => play([[260, 0.2]]),
        celebrate: (kind: 'level' | 'goal') =>
            play(
                kind === 'level'
                    ? [
                          [523, 0.12],
                          [659, 0.12, 0.08],
                          [784, 0.12, 0.16],
                          [1047, 0.35, 0.25],
                          [1319, 0.35, 0.25],
                      ]
                    : [
                          [659, 0.13],
                          [784, 0.13, 0.1],
                          [1047, 0.3, 0.2],
                      ],
            ),
        complete: (perfect = false) =>
            play(
                perfect
                    ? [
                          [523, 0.12],
                          [659, 0.12, 0.1],
                          [784, 0.12, 0.2],
                          [1047, 0.18, 0.3],
                          [1319, 0.4, 0.43],
                          [1568, 0.4, 0.43],
                      ]
                    : [
                          [523, 0.15],
                          [659, 0.15, 0.12],
                          [784, 0.3, 0.24],
                          [1047, 0.3, 0.24],
                      ],
            ),
    };
}
