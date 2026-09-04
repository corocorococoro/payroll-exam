<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';
import KyuchanMoment from '@/components/KyuchanMoment.vue';
import type { SkillTreeUnit } from '@/types';

const props = defineProps<{
    course: { name: string };
    units: SkillTreeUnit[];
}>();

const allLessons = computed(() => props.units.flatMap((unit) => unit.lessons));

const nextLesson = computed(
    () =>
        allLessons.value.find((lesson) => lesson.due_count > 0) ??
        allLessons.value.find(
            (lesson) => lesson.core_seen_count < lesson.core_question_count,
        ) ??
        allLessons.value.find(
            (lesson) => lesson.seen_count < lesson.question_count,
        ),
);

const bankQuestionCount = computed(() =>
    props.units.reduce(
        (total, unit) =>
            total +
            unit.lessons.reduce(
                (lessonTotal, lesson) => lessonTotal + lesson.question_count,
                0,
            ),
        0,
    ),
);

const coreQuestionCount = computed(() =>
    props.units.reduce(
        (total, unit) =>
            total +
            unit.lessons.reduce(
                (lessonTotal, lesson) =>
                    lessonTotal + lesson.core_question_count,
                0,
            ),
        0,
    ),
);

const unitClasses = {
    bg: 'bg-white dark:bg-gray-900',
    border: 'border-gray-200 dark:border-gray-800',
    text: 'text-gray-800 dark:text-gray-100',
    chip: 'bg-blue-50 text-[#285ac8] dark:bg-blue-950',
};
</script>

<template>
    <Head title="学習" />

    <h1 class="mb-1 text-xl font-semibold text-gray-700 dark:text-gray-200">
        {{ course.name }}
    </h1>
    <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
        まず重要問題{{
            coreQuestionCount
        }}問に取り組み、その後に追加問題へ進みます（全{{
            bankQuestionCount
        }}問）。
    </p>

    <Link
        v-if="nextLesson"
        :href="`/lessons/${nextLesson.id}`"
        class="mb-5 block rounded-lg bg-gradient-to-r from-blue-50 to-amber-50 p-3 transition hover:ring-2 hover:ring-blue-100 dark:from-blue-950 dark:to-gray-900 dark:hover:ring-blue-900"
    >
        <KyuchanMoment
            mood="point"
            effect="sparkle"
            :message="`次は「${nextLesson.name}」がおすすめです`"
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
                <Link
                    v-for="lesson in unit.lessons"
                    :key="lesson.id"
                    :href="`/lessons/${lesson.id}`"
                    :class="[
                        'flex items-center justify-between rounded-sm border bg-white p-3 transition-colors dark:bg-gray-900',
                        unitClasses.border,
                        'cursor-pointer hover:border-[#2864f0] hover:bg-blue-50/30 dark:hover:bg-blue-950/20',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <div
                            :class="[
                                'flex size-10 items-center justify-center rounded-full text-lg font-semibold',
                                unitClasses.chip,
                            ]"
                        >
                            <CheckCircle2
                                v-if="lesson.core_coverage_percent === 100"
                                class="size-5"
                            />
                            <span v-else>▶</span>
                        </div>
                        <div>
                            <p
                                class="text-sm font-bold text-gray-700 dark:text-gray-200"
                            >
                                {{ lesson.name }}
                            </p>
                            <p class="text-xs text-gray-400">
                                重要問題 {{ lesson.core_seen_count }}/{{
                                    lesson.core_question_count
                                }}問 · 1回{{ lesson.session_question_count }}問
                            </p>
                            <div
                                class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                            >
                                <div
                                    class="h-full rounded-full bg-[#2864f0]"
                                    :style="{
                                        width: `${lesson.core_coverage_percent}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="shrink-0 text-right text-[11px] font-bold text-gray-400"
                    >
                        <p>重要問題 {{ lesson.core_coverage_percent }}%</p>
                        <p v-if="lesson.due_count > 0" class="text-rose-500">
                            復習 {{ lesson.due_count }}問
                        </p>
                        <p v-else class="font-normal text-gray-300">
                            全体 {{ lesson.seen_count }}/{{
                                lesson.question_count
                            }}問
                        </p>
                    </div>
                </Link>
            </div>
        </section>
    </div>
</template>
