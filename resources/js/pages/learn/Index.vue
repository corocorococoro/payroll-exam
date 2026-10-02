<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';
import KyuchanMoment from '@/components/KyuchanMoment.vue';
import type { LearningCurriculum, SkillTreeUnit } from '@/types';

const props = defineProps<{
    course: { name: string };
    units: SkillTreeUnit[];
    curriculum?: LearningCurriculum;
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

    <template v-if="curriculum">
        <p class="mb-1 text-xs font-bold text-[#285ac8]">
            合格まで、ひとつずつ
        </p>
        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
            {{ course.name }}
        </h1>
        <p class="mt-2 text-sm leading-6 text-gray-500">
            例を見る → 手助けつきで練習 → 自力で確認 →
            日を空けて復習。1回1〜5問で進めます。
        </p>
        <div
            class="my-5 grid grid-cols-3 gap-2 rounded-xl border border-blue-100 bg-white p-4 text-center dark:border-gray-800 dark:bg-gray-900"
        >
            <div>
                <p class="text-xl font-bold text-[#285ac8]">
                    {{ curriculum.passed_count
                    }}<span class="text-xs text-gray-400">
                        / {{ curriculum.module_count }}</span
                    >
                </p>
                <p class="mt-1 text-[11px] text-gray-500">自力で確認</p>
            </div>
            <div>
                <p class="text-xl font-bold text-emerald-600">
                    {{ curriculum.retained_count }}
                </p>
                <p class="mt-1 text-[11px] text-gray-500">後日も解けた</p>
            </div>
            <div>
                <p class="text-xl font-bold text-amber-600">
                    {{ curriculum.due_count }}
                </p>
                <p class="mt-1 text-[11px] text-gray-500">今日の単元復習</p>
            </div>
        </div>
        <p
            v-if="curriculum.unavailable_count || !curriculum.module_count"
            class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            {{
                curriculum.unavailable_count
                    ? `${curriculum.unavailable_count}単元の教材を確認中です。`
                    : '教材を確認中です。'
            }}公開可能になってから再開できます。
        </p>
        <Link
            v-if="curriculum.next"
            :href="curriculum.next.href"
            class="mb-3 block rounded-xl bg-gradient-to-r from-blue-50 to-amber-50 p-4 hover:ring-2 hover:ring-blue-200 dark:from-blue-950 dark:to-gray-900"
        >
            <KyuchanMoment
                mood="point"
                effect="sparkle"
                :message="`次は「${curriculum.next.name}」`"
                :size="80"
                compact
            />
            <p class="mt-1 pl-2 text-xs text-gray-500">
                {{
                    curriculum.next.needs_support
                        ? '例に戻って、もう一度確かめよう'
                        : curriculum.next.phase === 'guided'
                          ? 'まず例を1つ。覚える前に意味をつかもう'
                          : '手助けを閉じて、別の問題に挑戦'
                }}
            </p>
        </Link>
        <Link
            v-if="curriculum.review"
            :href="curriculum.review.href"
            class="mb-5 block rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >↻ 日を空けて確認する：{{ curriculum.review.name }}</Link
        >
        <div class="mt-5 space-y-3">
            <details
                v-for="(section, sectionIndex) in curriculum.sections"
                :key="section.slug"
                :open="
                    section.slug === curriculum.next?.section ||
                    section.slug === curriculum.review?.section ||
                    (!curriculum.next && sectionIndex === 0)
                "
                class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <summary class="cursor-pointer p-4">
                    <span class="mr-2 text-xs font-bold text-[#285ac8]">{{
                        String(sectionIndex + 1).padStart(2, '0')
                    }}</span>
                    <span
                        class="font-semibold text-gray-800 dark:text-gray-100"
                        >{{ section.name }}</span
                    >
                    <p class="mt-1 pl-6 text-xs leading-5 text-gray-500">
                        {{ section.description }}
                    </p>
                </summary>
                <div class="space-y-2 px-3 pb-3">
                    <Link
                        v-for="module in curriculum.modules.filter(
                            (item) => item.section === section.slug,
                        )"
                        :key="module.id"
                        :href="module.available ? module.href : '/learn'"
                        :aria-disabled="!module.available"
                        class="flex items-start gap-3 rounded-lg border border-gray-100 p-3 hover:border-blue-300 hover:bg-blue-50/30 dark:border-gray-800 dark:hover:bg-blue-950/30"
                    >
                        <span
                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-[#285ac8] dark:bg-blue-950"
                            ><CheckCircle2
                                v-if="module.passed"
                                class="size-5"
                            /><span v-else>▶</span></span
                        >
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-sm font-semibold text-gray-800 dark:text-gray-100"
                            >
                                {{ module.name }}
                                <span
                                    v-if="!module.available"
                                    class="text-xs text-amber-700"
                                    >教材の確認中</span
                                >
                            </p>
                            <p class="mt-1 text-[11px] text-gray-500">
                                {{
                                    module.method === 'remember'
                                        ? '覚える条件を整理'
                                        : module.method === 'calculate'
                                          ? '例から計算手順をつかむ'
                                          : '意味から理解する'
                                }}
                                · 今回{{ module.session_question_count }}問
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1 text-[10px]">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-500 dark:bg-gray-800"
                                    :class="
                                        module.phase !== 'guided'
                                            ? 'text-blue-600'
                                            : ''
                                    "
                                    >例で練習</span
                                >
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-500 dark:bg-gray-800"
                                    :class="
                                        module.passed ? 'text-blue-600' : ''
                                    "
                                    >{{
                                        module.passed ? '✓ ' : ''
                                    }}自力確認</span
                                >
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-500 dark:bg-gray-800"
                                    :class="
                                        module.retained
                                            ? 'text-emerald-600'
                                            : ''
                                    "
                                    >{{
                                        module.retained ? '✓ ' : ''
                                    }}後日確認</span
                                >
                                <span
                                    v-if="module.due"
                                    class="font-bold text-amber-600"
                                    >今日の復習</span
                                >
                            </div>
                            <p
                                v-if="
                                    module.prerequisite_names.length &&
                                    !module.passed
                                "
                                class="mt-2 text-[10px] text-gray-400"
                            >
                                先に「{{
                                    module.prerequisite_names[0]
                                }}」がおすすめ
                            </p>
                        </div>
                    </Link>
                </div>
            </details>
        </div>
        <section class="mt-6 rounded-xl bg-blue-50 p-4 dark:bg-blue-950/40">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">
                最後は、初見の模試で確かめる
            </h2>
            <p class="mt-2 text-xs leading-6 text-gray-500">
                単元の自力確認は、合格へ進む途中の目安です。初回の模試2回で平均80点、各回70点以上を目指し、計算と分野別の弱点を復習します。
            </p>
            <Link
                href="/mock-exams"
                class="mt-3 inline-block text-sm font-bold text-[#285ac8]"
                >模試で実力を確認する →</Link
            >
        </section>
    </template>
    <template v-else>
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
                        <h2
                            :class="[
                                'text-base font-semibold',
                                unitClasses.text,
                            ]"
                        >
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
                                    }}問 · 1回{{
                                        lesson.session_question_count
                                    }}問
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
                            <p
                                v-if="lesson.due_count > 0"
                                class="text-rose-500"
                            >
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
</template>
