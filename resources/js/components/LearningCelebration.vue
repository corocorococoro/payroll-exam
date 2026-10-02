<script setup lang="ts">
import { usePreferredReducedMotion } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { CSSProperties } from 'vue';
import type { LearningReward } from '@/types/rewards';

const props = defineProps<{ reward: LearningReward | null }>();
const reducedMotion = usePreferredReducedMotion();
const visible = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
const big = computed(() => props.reward?.kind !== 'correct');
const colors = ['#fbbf24', '#38bdf8', '#34d399', '#fb7185', '#a78bfa'];
const particles = computed(() =>
    Array.from({ length: big.value ? 36 : 16 }, (_, i) => {
        const angle = (i / (big.value ? 36 : 16)) * Math.PI * 2;
        const distance = (big.value ? 110 : 70) + (i % 4) * 14;

        return {
            '--x': `${Math.cos(angle) * distance}px`,
            '--y': `${Math.sin(angle) * distance}px`,
            '--spin': `${i % 2 ? 230 : -180}deg`,
            '--delay': `${(i % 3) * 30}ms`,
            backgroundColor: colors[i % colors.length],
        } as CSSProperties;
    }),
);

watch(
    () => props.reward,
    (reward) => {
        clearTimeout(timer);
        visible.value = reward !== null;

        if (reward) {
            timer = setTimeout(
                () => {
                    visible.value = false;
                },
                reward.kind === 'correct' ? 1150 : 2100,
            );
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Teleport to="body">
        <div
            class="learning-celebration"
            aria-live="polite"
            aria-atomic="true"
            role="status"
        >
            <div
                v-if="reward && visible"
                :key="reward.id"
                class="reward-burst"
                :class="{
                    'reward-burst--big': big,
                    'reward-burst--reduced': reducedMotion === 'reduce',
                }"
            >
                <div class="reward-aura" aria-hidden="true" />
                <div
                    v-if="reducedMotion !== 'reduce'"
                    class="reward-particles"
                    aria-hidden="true"
                >
                    <i
                        v-for="(style, i) in particles"
                        :key="i"
                        :style="style"
                    />
                </div>
                <div class="reward-card">
                    <span class="reward-symbol" aria-hidden="true">{{
                        reward.kind === 'level'
                            ? '💎'
                            : reward.kind === 'goal' || reward.kind === 'combo'
                              ? '🔥'
                              : reward.kind === 'perfect' ||
                                  reward.kind === 'complete'
                                ? '🏆'
                                : '✦'
                    }}</span>
                    <div>
                        <p class="reward-title">{{ reward.title }}</p>
                        <p class="reward-detail">{{ reward.detail }}</p>
                    </div>
                    <span v-if="reward.xp > 0" class="reward-xp"
                        >+{{ reward.xp }}<small>XP</small></span
                    >
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.learning-celebration {
    position: fixed;
    top: max(104px, calc(env(safe-area-inset-top) + 80px));
    left: 0;
    right: 0;
    z-index: 40;
    display: flex;
    justify-content: center;
    pointer-events: none;
    padding: 0 16px;
}
.reward-burst {
    position: relative;
    width: max-content;
    max-width: 100%;
    animation: reward-arrive 1.15s both;
}
.reward-burst--big {
    animation-duration: 2.1s;
}
.reward-aura {
    position: absolute;
    inset: -26px;
    background: radial-gradient(
        ellipse,
        rgb(52 211 153 / 28%),
        transparent 70%
    );
}
.reward-burst--big .reward-aura {
    background: radial-gradient(
        ellipse,
        rgb(251 191 36 / 40%),
        transparent 70%
    );
}
.reward-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 2px solid #a7f3d0;
    border-radius: 22px;
    padding: 12px 18px;
    background: #fff;
    box-shadow:
        0 8px 0 rgb(5 150 105 / 14%),
        0 16px 40px rgb(5 150 105 / 18%);
    color: #065f46;
}
.reward-burst--big .reward-card {
    border-color: #fde68a;
    color: #92400e;
    box-shadow:
        0 8px 0 rgb(217 119 6 / 14%),
        0 16px 40px rgb(217 119 6 / 20%);
}
.reward-symbol {
    font-size: 28px;
    line-height: 1;
}
.reward-title {
    margin: 0;
    font-size: clamp(14px, 4vw, 19px);
    font-weight: 900;
    line-height: 1.4;
}
.reward-detail {
    margin: 2px 0 0;
    font-size: 10px;
    opacity: 0.8;
}
.reward-xp {
    display: grid;
    flex-shrink: 0;
    font-size: 26px;
    font-weight: 900;
    line-height: 1;
    text-align: center;
    color: #059669;
    animation: xp-punch 0.55s 0.1s both;
}
.reward-xp small {
    font-size: 10px;
    margin-top: 5px;
    letter-spacing: 0.12em;
}
.reward-burst--big .reward-xp {
    color: #d97706;
}
.reward-particles {
    position: absolute;
    inset: 50%;
}
.reward-particles i {
    position: absolute;
    width: 6px;
    height: 10px;
    border-radius: 2px;
    animation: reward-particle 0.95s var(--delay) ease-out both;
}
@keyframes reward-arrive {
    0% {
        opacity: 0;
        transform: translateY(18px) scale(0.65);
    }
    14% {
        opacity: 1;
        transform: translateY(-3px) scale(1.06);
    }
    24%,
    78% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateY(-15px) scale(0.96);
    }
}
@keyframes xp-punch {
    0% {
        transform: scale(0.4);
    }
    55% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
    }
}
@keyframes reward-particle {
    0% {
        opacity: 0;
        transform: translate(0, 0) scale(0);
    }
    15% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translate(var(--x), var(--y)) rotate(var(--spin)) scale(0.6);
    }
}
.reward-burst--reduced,
.reward-burst--reduced * {
    animation: none !important;
}
@media (prefers-reduced-motion: reduce) {
    .reward-burst,
    .reward-burst * {
        animation: none !important;
    }
    .reward-particles {
        display: none;
    }
}
</style>
