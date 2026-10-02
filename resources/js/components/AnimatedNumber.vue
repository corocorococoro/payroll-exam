<script setup lang="ts">
import { usePreferredReducedMotion } from '@vueuse/core';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{ value: number; duration?: number }>(),
    { duration: 900 },
);
const reducedMotion = usePreferredReducedMotion();
const displayed = ref(props.value);
let frame = 0;
let mounted = false;

function animate(from: number) {
    cancelAnimationFrame(frame);

    if (reducedMotion.value === 'reduce' || props.duration <= 0) {
        displayed.value = props.value;

        return;
    }

    const target = props.value;
    const start = performance.now();
    const tick = (now: number) => {
        const progress = Math.min(1, (now - start) / props.duration);
        displayed.value = Math.round(
            from + (target - from) * (1 - (1 - progress) ** 3),
        );

        if (progress < 1) {
            frame = requestAnimationFrame(tick);
        }
    };
    frame = requestAnimationFrame(tick);
}

onMounted(() => {
    mounted = true;
    animate(0);
});
watch([() => props.value, reducedMotion], () => {
    if (mounted) {
        animate(displayed.value);
    }
});
onBeforeUnmount(() => cancelAnimationFrame(frame));
</script>

<template>
    <span class="tabular-nums"
        ><span aria-hidden="true">{{ displayed }}</span
        ><span class="sr-only">{{ value }}</span></span
    >
</template>
