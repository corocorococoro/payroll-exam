<script setup lang="ts">
/**
 * マスコット「きゅーちゃん」— 給与袋モチーフの SVG キャラクター。
 * mood で表情が変わる: normal / happy / sad / cheer
 */
withDefaults(
    defineProps<{
        mood?: 'normal' | 'happy' | 'sad' | 'cheer';
        size?: number;
    }>(),
    { mood: 'normal', size: 96 },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 120 120"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        :class="['kyuchan', `kyuchan--${mood}`]"
        aria-hidden="true"
    >
        <!-- 袋のからだ -->
        <path
            d="M25 45 C25 38 35 32 60 32 C85 32 95 38 95 45 L98 88 C98 102 84 110 60 110 C36 110 22 102 22 88 Z"
            fill="#FFD9A0"
            stroke="#E8A75D"
            stroke-width="3"
        />
        <!-- 袋の口（結び目） -->
        <path
            d="M45 33 C45 24 50 16 60 16 C70 16 75 24 75 33"
            fill="none"
            stroke="#E8A75D"
            stroke-width="6"
            stroke-linecap="round"
        />
        <ellipse
            cx="60"
            cy="33"
            rx="18"
            ry="6"
            fill="#F7C983"
            stroke="#E8A75D"
            stroke-width="2.5"
        />

        <!-- ￥マーク -->
        <text
            x="60"
            y="96"
            text-anchor="middle"
            font-size="20"
            font-weight="800"
            fill="#E8935D"
        >
            ￥
        </text>

        <!-- ほっぺ -->
        <circle cx="38" cy="66" r="6" fill="#FFB3C1" opacity="0.8" />
        <circle cx="82" cy="66" r="6" fill="#FFB3C1" opacity="0.8" />

        <!-- 目と口: mood 別 -->
        <template v-if="mood === 'happy' || mood === 'cheer'">
            <path
                d="M40 56 Q45 50 50 56"
                stroke="#5C4633"
                stroke-width="3.5"
                stroke-linecap="round"
                fill="none"
            />
            <path
                d="M70 56 Q75 50 80 56"
                stroke="#5C4633"
                stroke-width="3.5"
                stroke-linecap="round"
                fill="none"
            />
            <path
                d="M52 68 Q60 78 68 68"
                stroke="#5C4633"
                stroke-width="3.5"
                stroke-linecap="round"
                fill="none"
            />
        </template>
        <template v-else-if="mood === 'sad'">
            <circle cx="45" cy="57" r="3.5" fill="#5C4633" />
            <circle cx="75" cy="57" r="3.5" fill="#5C4633" />
            <path
                d="M53 72 Q60 66 67 72"
                stroke="#5C4633"
                stroke-width="3.5"
                stroke-linecap="round"
                fill="none"
            />
            <path
                d="M83 47 Q87 51 84 55"
                stroke="#9CC3E4"
                stroke-width="4"
                stroke-linecap="round"
                fill="none"
            />
        </template>
        <template v-else>
            <circle cx="45" cy="57" r="3.5" fill="#5C4633" />
            <circle cx="75" cy="57" r="3.5" fill="#5C4633" />
            <path
                d="M54 69 Q60 74 66 69"
                stroke="#5C4633"
                stroke-width="3.5"
                stroke-linecap="round"
                fill="none"
            />
        </template>

        <!-- cheer: キラキラ -->
        <template v-if="mood === 'cheer'">
            <path
                d="M14 30 l3 6 6 3 -6 3 -3 6 -3 -6 -6 -3 6 -3 Z"
                fill="#FFD34E"
            />
            <path
                d="M102 22 l2.5 5 5 2.5 -5 2.5 -2.5 5 -2.5 -5 -5 -2.5 5 -2.5 Z"
                fill="#FFD34E"
            />
        </template>
    </svg>
</template>

<style scoped>
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
</style>
