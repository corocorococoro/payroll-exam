<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Clock3,
    FileCheck2,
    Gauge,
    Play,
    RotateCcw,
    Trophy,
} from '@lucide/vue';
import { ref } from 'vue';

type Exam = {
    id: number;
    name: string;
    description: string | null;
    time_limit_minutes: number;
    passing_score: number;
    question_count: number;
    active_attempt_id: number | null;
    best_score: number | null;
    attempt_count: number;
    scores: number[];
};

defineProps<{ exams: Exam[] }>();
const starting = ref<number | null>(null);

function start(exam: Exam, mode: 'standard' | 'compressed') {
    starting.value = exam.id;
    router.post(
        `/mock-exams/${exam.id}/attempts`,
        { mode },
        {
            onFinish: () => {
                starting.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="模擬試験" />
    <div
        class="mb-5 rounded-3xl bg-gradient-to-br from-violet-100 to-sky-100 p-5 dark:from-violet-950 dark:to-sky-950"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex size-12 items-center justify-center rounded-2xl bg-white text-violet-500 shadow-sm dark:bg-stone-900"
            >
                <FileCheck2 class="size-7" />
            </div>
            <div>
                <h1
                    class="text-xl font-extrabold text-stone-700 dark:text-stone-100"
                >
                    本番形式 模擬試験
                </h1>
                <p class="text-xs text-stone-500">40問・100点満点・70点合格</p>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <section
            v-for="exam in exams"
            :key="exam.id"
            class="rounded-3xl border-2 border-violet-100 bg-white p-5 dark:border-stone-800 dark:bg-stone-900"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2
                        class="font-extrabold text-stone-700 dark:text-stone-100"
                    >
                        {{ exam.name }}
                    </h2>
                    <p class="mt-1 text-xs leading-relaxed text-stone-400">
                        {{ exam.description }}
                    </p>
                </div>
                <div
                    v-if="exam.best_score !== null"
                    :class="[
                        'shrink-0 rounded-2xl px-3 py-2 text-center',
                        exam.best_score >= exam.passing_score
                            ? 'bg-emerald-100 text-emerald-600'
                            : 'bg-orange-100 text-orange-600',
                    ]"
                >
                    <p class="text-[10px] font-bold">BEST</p>
                    <p class="text-xl font-extrabold">{{ exam.best_score }}</p>
                </div>
            </div>
            <div class="my-4 grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-800">
                    <Clock3 class="mx-auto mb-1 size-4 text-violet-400" />{{
                        exam.time_limit_minutes
                    }}分
                </div>
                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-800">
                    <FileCheck2 class="mx-auto mb-1 size-4 text-sky-400" />{{
                        exam.question_count
                    }}問
                </div>
                <div class="rounded-xl bg-stone-50 p-2 dark:bg-stone-800">
                    <Trophy class="mx-auto mb-1 size-4 text-amber-400" />{{
                        exam.passing_score
                    }}点
                </div>
            </div>
            <div
                v-if="exam.scores.length > 1"
                class="mb-4 flex h-12 items-end gap-1 rounded-xl bg-stone-50 px-2 pt-2 dark:bg-stone-800"
            >
                <div
                    v-for="(score, i) in exam.scores"
                    :key="i"
                    class="flex-1 rounded-t bg-violet-300"
                    :style="{ height: `${Math.max(4, score)}%` }"
                    :title="`${score}点`"
                />
            </div>
            <Link
                v-if="exam.active_attempt_id"
                :href="`/mock-attempts/${exam.active_attempt_id}`"
                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-violet-500 py-3 font-extrabold text-white shadow-[0_4px_0] shadow-violet-600"
                ><RotateCcw class="size-4" />途中から再開</Link
            >
            <div v-else class="grid grid-cols-2 gap-3">
                <button
                    :disabled="starting === exam.id"
                    class="flex items-center justify-center gap-1 rounded-2xl bg-violet-500 py-3 text-sm font-extrabold text-white shadow-[0_4px_0] shadow-violet-600 active:translate-y-1 active:shadow-none"
                    @click="start(exam, 'standard')"
                >
                    <Play class="size-4" />120分</button
                ><button
                    :disabled="starting === exam.id"
                    class="flex items-center justify-center gap-1 rounded-2xl bg-sky-400 py-3 text-sm font-extrabold text-white shadow-[0_4px_0] shadow-sky-500 active:translate-y-1 active:shadow-none"
                    @click="start(exam, 'compressed')"
                >
                    <Gauge class="size-4" />90分圧縮
                </button>
            </div>
        </section>
    </div>
</template>
