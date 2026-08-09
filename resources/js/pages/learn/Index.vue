<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Crown, Lock } from '@lucide/vue';
import type { SkillTreeUnit } from '@/types';

defineProps<{
    course: { name: string };
    units: SkillTreeUnit[];
}>();

const colorClasses: Record<
    string,
    { bg: string; border: string; text: string; chip: string }
> = {
    pink: {
        bg: 'bg-pink-50 dark:bg-pink-950/30',
        border: 'border-pink-200 dark:border-pink-900',
        text: 'text-pink-500',
        chip: 'bg-pink-100 text-pink-600 dark:bg-pink-900/50',
    },
    amber: {
        bg: 'bg-amber-50 dark:bg-amber-950/30',
        border: 'border-amber-200 dark:border-amber-900',
        text: 'text-amber-500',
        chip: 'bg-amber-100 text-amber-600 dark:bg-amber-900/50',
    },
    sky: {
        bg: 'bg-sky-50 dark:bg-sky-950/30',
        border: 'border-sky-200 dark:border-sky-900',
        text: 'text-sky-500',
        chip: 'bg-sky-100 text-sky-600 dark:bg-sky-900/50',
    },
    emerald: {
        bg: 'bg-emerald-50 dark:bg-emerald-950/30',
        border: 'border-emerald-200 dark:border-emerald-900',
        text: 'text-emerald-500',
        chip: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50',
    },
    violet: {
        bg: 'bg-violet-50 dark:bg-violet-950/30',
        border: 'border-violet-200 dark:border-violet-900',
        text: 'text-violet-500',
        chip: 'bg-violet-100 text-violet-600 dark:bg-violet-900/50',
    },
    rose: {
        bg: 'bg-rose-50 dark:bg-rose-950/30',
        border: 'border-rose-200 dark:border-rose-900',
        text: 'text-rose-500',
        chip: 'bg-rose-100 text-rose-600 dark:bg-rose-900/50',
    },
};

const colors = (c: string) => colorClasses[c] ?? colorClasses.pink;
</script>

<template>
    <Head title="まなぶ" />

    <h1 class="mb-1 text-xl font-extrabold text-stone-700 dark:text-stone-200">
        {{ course.name }}
    </h1>
    <p class="mb-5 text-sm text-stone-500 dark:text-stone-400">
        毎日コツコツ、レッスンを進めよう！
    </p>

    <div class="flex flex-col gap-6">
        <section
            v-for="unit in units"
            :key="unit.id"
            :class="[
                'rounded-3xl border-2 p-4',
                colors(unit.color).bg,
                colors(unit.color).border,
            ]"
        >
            <div class="mb-3 flex items-center gap-2">
                <span class="text-2xl">{{ unit.icon }}</span>
                <div>
                    <h2
                        :class="[
                            'text-base font-extrabold',
                            colors(unit.color).text,
                        ]"
                    >
                        {{ unit.name }}
                        <span
                            v-if="unit.is_advanced"
                            class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-[10px] text-stone-500 dark:bg-stone-800"
                            >発展</span
                        >
                    </h2>
                    <p class="text-xs text-stone-500 dark:text-stone-400">
                        {{ unit.description }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <component
                    :is="lesson.unlocked ? Link : 'div'"
                    v-for="lesson in unit.lessons"
                    :key="lesson.id"
                    :href="
                        lesson.unlocked ? `/lessons/${lesson.id}` : undefined
                    "
                    :class="[
                        'flex items-center justify-between rounded-2xl border-2 bg-white p-3 transition dark:bg-stone-900',
                        colors(unit.color).border,
                        lesson.unlocked
                            ? 'cursor-pointer shadow-[0_3px_0] shadow-stone-200 hover:-translate-y-0.5 active:translate-y-0 active:shadow-none dark:shadow-stone-800'
                            : 'opacity-50',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <div
                            :class="[
                                'flex size-10 items-center justify-center rounded-full text-lg font-extrabold',
                                colors(unit.color).chip,
                            ]"
                        >
                            <Lock v-if="!lesson.unlocked" class="size-4" />
                            <template v-else
                                >{{
                                    lesson.crown_level > 0
                                        ? lesson.crown_level
                                        : ''
                                }}<Crown
                                    v-if="lesson.crown_level > 0"
                                    class="size-4"
                                /><span v-else>▶</span></template
                            >
                        </div>
                        <div>
                            <p
                                class="text-sm font-bold text-stone-700 dark:text-stone-200"
                            >
                                {{ lesson.name }}
                            </p>
                            <p class="text-xs text-stone-400">
                                1回{{ lesson.question_count }}問 ·
                                {{ lesson.description }}
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-0.5">
                        <Crown
                            v-for="i in 5"
                            :key="i"
                            :class="[
                                'size-4',
                                i <= lesson.crown_level
                                    ? 'fill-amber-400 text-amber-400'
                                    : 'text-stone-200 dark:text-stone-700',
                            ]"
                        />
                    </div>
                </component>
            </div>
        </section>
    </div>
</template>
