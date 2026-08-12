<script setup lang="ts">
import Kyuchan from '@/components/Kyuchan.vue';
import type { KyuchanMood, MascotStyleSlug } from '@/types';

withDefaults(
    defineProps<{
        mood: KyuchanMood;
        message: string;
        size?: number;
        outfit?: MascotStyleSlug;
        compact?: boolean;
        loading?: 'eager' | 'lazy';
    }>(),
    { size: 96, compact: false, loading: 'lazy' },
);
</script>

<template>
    <div :class="['flex min-w-0 items-center', compact ? 'gap-2' : 'gap-3']">
        <Kyuchan
            :mood="mood"
            :size="size"
            :outfit="outfit"
            :loading="loading"
        />
        <p
            :class="[
                'relative min-w-0 rounded-xl border border-blue-100 bg-white/90 text-gray-600 shadow-xs dark:border-gray-700 dark:bg-gray-900/90 dark:text-gray-200',
                compact
                    ? 'px-3 py-2 text-xs leading-relaxed'
                    : 'px-4 py-3 text-sm leading-relaxed font-medium',
            ]"
        >
            <span
                class="absolute top-1/2 -left-1.5 size-3 -translate-y-1/2 rotate-45 border-b border-l border-blue-100 bg-white dark:border-gray-700 dark:bg-gray-900"
                aria-hidden="true"
            />
            <span class="relative">{{ message }}</span>
        </p>
    </div>
</template>
