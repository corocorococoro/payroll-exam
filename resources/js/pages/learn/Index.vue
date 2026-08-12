<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Crown, Lock } from '@lucide/vue';
import { computed } from 'vue';
import KyuchanMoment from '@/components/KyuchanMoment.vue';
import type { SkillTreeUnit } from '@/types';

const props = defineProps<{
    course: { name: string };
    units: SkillTreeUnit[];
}>();

const nextLesson = computed(() =>
    props.units
        .flatMap((unit) => unit.lessons)
        .find((lesson) => lesson.unlocked && lesson.crown_level < 5),
);

const unitClasses = {
    bg: 'bg-white dark:bg-gray-900',
    border: 'border-gray-200 dark:border-gray-800',
    text: 'text-gray-800 dark:text-gray-100',
    chip: 'bg-blue-50 text-[#285ac8] dark:bg-blue-950',
};
</script>

<template>
    <Head title="まなぶ" />

    <h1 class="mb-1 text-xl font-semibold text-gray-700 dark:text-gray-200">
        {{ course.name }}
    </h1>
    <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
        毎日コツコツ、レッスンを進めよう！
    </p>

    <Link
        v-if="nextLesson"
        :href="`/lessons/${nextLesson.id}`"
        class="mb-5 block rounded-lg bg-gradient-to-r from-blue-50 to-amber-50 p-3 transition hover:ring-2 hover:ring-blue-100 dark:from-blue-950 dark:to-gray-900 dark:hover:ring-blue-900"
    >
        <KyuchanMoment
            mood="point"
            :message="`次は「${nextLesson.name}」がおすすめだよ`"
            :size="88"
            compact
        />
    </Link>

    <div class="flex flex-col gap-6">
        <section
            v-for="unit in units"
            :key="unit.id"
            :class="[
                'rounded-md border p-4 shadow-xs',
                unitClasses.bg,
                unitClasses.border,
            ]"
        >
            <div class="mb-3 flex items-center gap-2">
                <span class="text-2xl">{{ unit.icon }}</span>
                <div>
                    <h2 :class="['text-base font-semibold', unitClasses.text]">
                        {{ unit.name }}
                        <span
                            v-if="unit.is_advanced"
                            class="ml-1 rounded-sm bg-amber-50 px-2 py-0.5 text-[10px] text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                            >発展</span
                        >
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
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
                        'flex items-center justify-between rounded-sm border bg-white p-3 transition-colors dark:bg-gray-900',
                        unitClasses.border,
                        lesson.unlocked
                            ? 'cursor-pointer hover:border-[#2864f0] hover:bg-blue-50/30 dark:hover:bg-blue-950/20'
                            : 'opacity-50',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <div
                            :class="[
                                'flex size-10 items-center justify-center rounded-full text-lg font-semibold',
                                unitClasses.chip,
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
                                class="text-sm font-bold text-gray-700 dark:text-gray-200"
                            >
                                {{ lesson.name }}
                            </p>
                            <p class="text-xs text-gray-400">
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
                                    : 'text-gray-200 dark:text-gray-700',
                            ]"
                        />
                    </div>
                </component>
            </div>
        </section>
    </div>
</template>
