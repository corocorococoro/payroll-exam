<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Lock } from '@lucide/vue';
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
        .find(
            (lesson) =>
                lesson.unlocked &&
                (lesson.due_count > 0 ||
                    lesson.seen_count < lesson.question_count),
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
        全{{
            bankQuestionCount
        }}問を10問ずつ。初見・復習・定着を分けて進めます。
    </p>

    <Link
        v-if="nextLesson"
        :href="`/lessons/${nextLesson.id}`"
        class="mb-5 block rounded-lg bg-gradient-to-r from-blue-50 to-amber-50 p-3 transition hover:ring-2 hover:ring-blue-100 dark:from-blue-950 dark:to-gray-900 dark:hover:ring-blue-900"
    >
        <KyuchanMoment
            mood="point"
            effect="sparkle"
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
                            <CheckCircle2
                                v-else-if="lesson.coverage_percent === 100"
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
                                1回{{ lesson.session_question_count }}問 · 全{{
                                    lesson.question_count
                                }}問中 {{ lesson.seen_count }}問に着手
                            </p>
                            <div
                                class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                            >
                                <div
                                    class="h-full rounded-full bg-[#2864f0]"
                                    :style="{
                                        width: `${lesson.coverage_percent}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="shrink-0 text-right text-[11px] font-bold text-gray-400"
                    >
                        <p>{{ lesson.coverage_percent }}%</p>
                        <p v-if="lesson.due_count > 0" class="text-rose-500">
                            復習 {{ lesson.due_count }}
                        </p>
                    </div>
                </component>
            </div>
        </section>
    </div>
</template>
