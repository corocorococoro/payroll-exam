<script setup lang="ts">
import { Flame } from '@lucide/vue';

defineProps<{ combo: number }>();
</script>

<template>
    <div
        class="flex min-h-7 items-center justify-center gap-2 text-xs font-bold"
        :class="
            combo >= 3
                ? 'text-orange-600 dark:text-orange-300'
                : 'text-gray-500 dark:text-gray-400'
        "
    >
        <Flame
            :key="combo"
            class="size-4"
            :class="{ 'combo-pop fill-orange-200': combo >= 3 }"
            aria-hidden="true"
        />
        <span v-if="combo > 0"
            >{{ combo }}連続正解<span v-if="combo >= 5"> · 絶好調！</span></span
        >
        <span v-else>ひとつずつ、身につけよう</span>
        <span
            v-for="dot in 5"
            :key="dot"
            class="size-1.5 rounded-full transition-colors"
            :class="
                dot <= Math.min(combo, 5)
                    ? 'bg-orange-400'
                    : 'bg-gray-200 dark:bg-gray-700'
            "
            aria-hidden="true"
        />
    </div>
</template>

<style scoped>
.combo-pop {
    animation: combo-pop 0.4s ease-out;
}
@keyframes combo-pop {
    40% {
        transform: scale(1.45) rotate(-12deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .combo-pop {
        animation: none;
    }
}
</style>
