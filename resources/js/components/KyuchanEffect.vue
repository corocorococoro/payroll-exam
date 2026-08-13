<script setup lang="ts">
import type { KyuchanEffect } from '@/types';

defineProps<{ effect: KyuchanEffect }>();
</script>

<template>
    <span :class="['kyuchan-effect', `kyuchan-effect--${effect}`]">
        <template v-if="effect === 'sparkle'">
            <i v-for="index in 3" :key="index" />
        </template>
        <span v-else-if="effect === 'heart'" class="symbol">♥</span>
        <span v-else-if="effect === 'question'" class="bubble">?</span>
        <template v-else-if="effect === 'focus'">
            <i v-for="index in 4" :key="index" />
        </template>
        <template v-else-if="effect === 'confetti'">
            <i v-for="index in 8" :key="index" />
        </template>
        <span v-else-if="effect === 'alert'" class="bubble">!</span>
        <template v-else> <i>Z</i><i>z</i><i>z</i> </template>
    </span>
</template>

<style scoped>
.kyuchan-effect {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
}

.symbol,
.bubble,
.kyuchan-effect i {
    position: absolute;
    display: block;
    font-style: normal;
}

.symbol {
    top: 8%;
    right: 2%;
    color: #fb7185;
    font-size: 0.22em;
    filter: drop-shadow(0 0.02em 0 white);
    animation: effect-pop 0.7s ease-out both;
}

.bubble {
    top: 3%;
    right: 1%;
    display: grid;
    width: 24%;
    aspect-ratio: 1;
    place-items: center;
    border: 2px solid white;
    border-radius: 999px;
    background: #fbbf24;
    color: white;
    font-size: 0.16em;
    font-weight: 900;
    line-height: 1;
    filter: drop-shadow(0 0.02em 0.02em rgb(15 23 42 / 18%));
    animation: effect-pop 0.55s ease-out both;
}

.kyuchan-effect--sparkle i {
    width: 0.09em;
    height: 0.09em;
    rotate: 45deg;
    border-radius: 0.015em;
    background: #fbbf24;
    box-shadow: 0 0 0 0.012em rgb(255 255 255 / 80%);
    animation: effect-twinkle 1.4s ease-in-out infinite alternate;
}

.kyuchan-effect--sparkle i:nth-child(1) {
    top: 5%;
    right: 3%;
}

.kyuchan-effect--sparkle i:nth-child(2) {
    top: 20%;
    right: -2%;
    scale: 0.55;
    animation-delay: 0.2s;
}

.kyuchan-effect--sparkle i:nth-child(3) {
    top: 1%;
    left: 13%;
    scale: 0.4;
    animation-delay: 0.4s;
}

.kyuchan-effect--focus i {
    width: 0.18em;
    height: 0.18em;
    border-color: #38bdf8;
    border-style: solid;
    border-width: 0;
    animation: effect-focus 1.4s ease-in-out infinite alternate;
}

.kyuchan-effect--focus i:nth-child(1) {
    top: 4%;
    left: 4%;
    border-top-width: 0.025em;
    border-left-width: 0.025em;
}

.kyuchan-effect--focus i:nth-child(2) {
    top: 4%;
    right: 4%;
    border-top-width: 0.025em;
    border-right-width: 0.025em;
}

.kyuchan-effect--focus i:nth-child(3) {
    right: 4%;
    bottom: 4%;
    border-right-width: 0.025em;
    border-bottom-width: 0.025em;
}

.kyuchan-effect--focus i:nth-child(4) {
    bottom: 4%;
    left: 4%;
    border-bottom-width: 0.025em;
    border-left-width: 0.025em;
}

.kyuchan-effect--confetti i {
    width: 0.035em;
    height: 0.1em;
    border-radius: 999px;
    background: #fbbf24;
    animation: effect-confetti 1.2s ease-out infinite;
}

.kyuchan-effect--confetti i:nth-child(2n) {
    background: #38bdf8;
}

.kyuchan-effect--confetti i:nth-child(3n) {
    background: #fb7185;
}

.kyuchan-effect--confetti i:nth-child(1) {
    top: 4%;
    left: 4%;
    rotate: -35deg;
}
.kyuchan-effect--confetti i:nth-child(2) {
    top: 18%;
    left: -1%;
    rotate: 30deg;
    animation-delay: 0.15s;
}
.kyuchan-effect--confetti i:nth-child(3) {
    top: 2%;
    left: 27%;
    rotate: 60deg;
    animation-delay: 0.3s;
}
.kyuchan-effect--confetti i:nth-child(4) {
    top: 4%;
    right: 5%;
    rotate: 35deg;
    animation-delay: 0.1s;
}
.kyuchan-effect--confetti i:nth-child(5) {
    top: 22%;
    right: -1%;
    rotate: -25deg;
    animation-delay: 0.25s;
}
.kyuchan-effect--confetti i:nth-child(6) {
    top: 1%;
    right: 29%;
    rotate: -60deg;
    animation-delay: 0.4s;
}
.kyuchan-effect--confetti i:nth-child(7) {
    top: 34%;
    left: 2%;
    rotate: 70deg;
    animation-delay: 0.5s;
}
.kyuchan-effect--confetti i:nth-child(8) {
    top: 37%;
    right: 2%;
    rotate: -70deg;
    animation-delay: 0.35s;
}

.kyuchan-effect--zzz i {
    color: #60a5fa;
    font-weight: 900;
    line-height: 1;
    text-shadow: 0 0.015em white;
    animation: effect-drift 1.8s ease-in-out infinite;
}

.kyuchan-effect--zzz i:nth-child(1) {
    top: 1%;
    right: 1%;
    font-size: 0.18em;
}
.kyuchan-effect--zzz i:nth-child(2) {
    top: 15%;
    right: -1%;
    font-size: 0.13em;
    animation-delay: 0.25s;
}
.kyuchan-effect--zzz i:nth-child(3) {
    top: 26%;
    right: 4%;
    font-size: 0.09em;
    animation-delay: 0.5s;
}

@keyframes effect-pop {
    from {
        scale: 0;
        opacity: 0;
    }
    70% {
        scale: 1.15;
    }
    to {
        scale: 1;
        opacity: 1;
    }
}

@keyframes effect-twinkle {
    from {
        scale: 0.65;
        opacity: 0.55;
    }
    to {
        scale: 1;
        opacity: 1;
    }
}

@keyframes effect-focus {
    from {
        translate: 0 0;
        opacity: 0.55;
    }
    to {
        translate: 0.015em 0.015em;
        opacity: 1;
    }
}

@keyframes effect-confetti {
    from {
        translate: 0 -0.05em;
        opacity: 0;
    }
    25% {
        opacity: 1;
    }
    to {
        translate: 0 0.12em;
        opacity: 0;
    }
}

@keyframes effect-drift {
    0%,
    100% {
        translate: 0 0;
        opacity: 0.5;
    }
    50% {
        translate: 0 -0.04em;
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .kyuchan-effect *,
    .symbol,
    .bubble {
        animation: none !important;
    }
}
</style>
