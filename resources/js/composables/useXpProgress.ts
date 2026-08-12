import { shallowRef } from 'vue';
import type { XpProgress } from '@/types';

const progress = shallowRef<XpProgress | null>(null);

export function useXpProgress() {
    function sync(next: XpProgress | null | undefined) {
        if (next) {
            progress.value = next;
        }
    }

    return { progress, sync };
}
