<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useXpProgress } from '@/composables/useXpProgress';
import type { MascotStyleSlug, Stats } from '@/types';

/**
 * マスコット「きゅーちゃん」— 給与学習を応援する3頭身キャラクター。
 * mood でイラストが変わる: normal / happy / sad / cheer
 */
const props = withDefaults(
    defineProps<{
        mood?: 'normal' | 'happy' | 'sad' | 'cheer';
        size?: number;
        outfit?: MascotStyleSlug;
    }>(),
    { mood: 'normal', size: 96 },
);

const page = usePage();
const { progress } = useXpProgress();
const failed = ref(false);
const pageStyle = computed(
    () => (page.props.stats as Stats | null)?.xp_progress?.mascot_style,
);
const selectedStyle = computed(
    () =>
        props.outfit ??
        progress.value?.mascot_style ??
        pageStyle.value ??
        'default',
);
const imageSource = computed(() =>
    failed.value || selectedStyle.value === 'default'
        ? `/images/kyuchan/${props.mood}.webp`
        : `/images/kyuchan/styles/${selectedStyle.value}/${props.mood}.webp`,
);

watch([selectedStyle, () => props.mood], () => {
    failed.value = false;
});
</script>

<template>
    <span
        :class="['kyuchan', `kyuchan--${mood}`]"
        :style="{ width: `${size}px`, height: `${size}px` }"
        aria-hidden="true"
    >
        <img
            :src="imageSource"
            alt=""
            width="627"
            height="627"
            draggable="false"
            @error="failed = true"
        />
    </span>
</template>

<style scoped>
.kyuchan {
    display: inline-flex;
    flex: none;
    align-items: center;
    justify-content: center;
    transform-origin: bottom center;
    user-select: none;
}

.kyuchan img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    pointer-events: none;
}

.kyuchan--happy,
.kyuchan--cheer {
    animation: kyuchan-bounce 0.6s ease-in-out;
}

.kyuchan--sad {
    animation: kyuchan-shake 0.5s ease-in-out;
}

@keyframes kyuchan-bounce {
    0%,
    100% {
        transform: translateY(0);
    }
    30% {
        transform: translateY(-10px) rotate(-3deg);
    }
    60% {
        transform: translateY(0) rotate(2deg);
    }
}

@keyframes kyuchan-shake {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-4px);
    }
    75% {
        transform: translateX(4px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .kyuchan--happy,
    .kyuchan--cheer,
    .kyuchan--sad {
        animation: none;
    }
}
</style>
