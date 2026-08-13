<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import KyuchanEffectLayer from '@/components/KyuchanEffect.vue';
import { useXpProgress } from '@/composables/useXpProgress';
import type {
    KyuchanEffect,
    KyuchanMood,
    MascotStyleSlug,
    Stats,
} from '@/types';

/**
 * マスコット「きゅーちゃん」— 給与学習を応援する3頭身キャラクター。
 * mood で表情・学習ポーズが変わる。
 */
const props = withDefaults(
    defineProps<{
        mood?: KyuchanMood;
        size?: number;
        outfit?: MascotStyleSlug;
        effect?: KyuchanEffect;
        loading?: 'eager' | 'lazy';
    }>(),
    { mood: 'normal', size: 96, loading: 'eager' },
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
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            fontSize: `${size}px`,
        }"
        aria-hidden="true"
    >
        <img
            :src="imageSource"
            alt=""
            width="627"
            height="627"
            :loading="loading"
            draggable="false"
            @error="failed = true"
        />
        <KyuchanEffectLayer v-if="effect" :effect="effect" />
    </span>
</template>

<style scoped>
.kyuchan {
    display: inline-flex;
    flex: none;
    align-items: center;
    justify-content: center;
    position: relative;
    isolation: isolate;
    transform-origin: bottom center;
    user-select: none;
}

.kyuchan img {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    object-fit: contain;
    pointer-events: none;
}

.kyuchan--happy,
.kyuchan--cheer,
.kyuchan--wave,
.kyuchan--point,
.kyuchan--approve,
.kyuchan--clap,
.kyuchan--confident {
    animation: kyuchan-bounce 0.6s ease-in-out;
}

.kyuchan--sad,
.kyuchan--curious {
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
    .kyuchan--wave,
    .kyuchan--point,
    .kyuchan--approve,
    .kyuchan--clap,
    .kyuchan--confident,
    .kyuchan--curious,
    .kyuchan--sad {
        animation: none;
    }
}
</style>
