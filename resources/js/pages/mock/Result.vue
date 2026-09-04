<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, RotateCcw, Trophy } from '@lucide/vue';
import { ref } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';

type Section = {
    name: string;
    correct: number;
    total: number;
    earned: number;
    max: number;
    accuracy: number;
};
type Review = {
    position: number;
    question_text: string;
    unit_name: string;
    given_answer: string | null;
    correct: boolean;
    correct_answer: string;
    explanation: string;
    official_sources: { label: string; url: string }[];
    points: number;
};
type Remediation = {
    lesson_id: number;
    lesson_name: string;
    unit_name: string;
    missed_count: number;
    missed_points: number;
    href: string;
};
defineProps<{
    result: {
        id: number;
        exam_name: string;
        score: number;
        passing_score: number;
        passed: boolean;
        section_scores: Record<string, Section>;
        unit_scores: Record<string, Section>;
        knowledge_score: number;
        calculation_score: number;
        weakest_sections: string[];
        finished_at: string;
    };
    remediation: Remediation[];
    review: Review[];
}>();
const open = ref<number[]>([]);
function toggle(position: number) {
    open.value = open.value.includes(position)
        ? open.value.filter((p) => p !== position)
        : [...open.value, position];
}
</script>

<template>
    <Head title="模試結果" />
    <div class="text-center">
        <Kyuchan
            :mood="result.passed ? 'clap' : 'curious'"
            :effect="result.passed ? 'confetti' : 'heart'"
            :size="130"
        />
        <p
            :class="[
                'text-sm font-semibold',
                result.passed ? 'text-emerald-500' : 'text-[#285ac8]',
            ]"
        >
            {{
                result.passed
                    ? '合格基準の70点に到達しました'
                    : '結果を確認して復習しましょう'
            }}
        </p>
        <h1
            class="mt-1 text-5xl font-semibold text-gray-700 dark:text-gray-100"
        >
            {{ result.score }}<span class="text-lg text-gray-400"> / 100</span>
        </h1>
        <p class="mt-1 text-xs text-gray-400">
            合格基準 {{ result.passing_score }}点
        </p>
    </div>
    <section
        class="mt-5 rounded-lg border border-gray-100 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    >
        <h2 class="mb-3 flex items-center gap-2 font-semibold">
            <Trophy class="size-5 text-amber-500" />分野別の結果
        </h2>
        <p class="mb-3 text-xs text-gray-500">
            知識 {{ result.knowledge_score }}/70点・計算
            {{ result.calculation_score }}/30点
        </p>
        <div class="space-y-3">
            <div v-for="(section, slug) in result.unit_scores" :key="slug">
                <div class="mb-1 flex justify-between text-xs">
                    <span class="font-bold">{{ section.name }}</span
                    ><span
                        :class="
                            section.accuracy >= 70
                                ? 'text-emerald-500'
                                : 'text-rose-500'
                        "
                        >{{ section.earned }}/{{ section.max }}点・{{
                            section.accuracy
                        }}%</span
                    >
                </div>
                <div
                    class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                >
                    <div
                        :class="[
                            'h-full rounded-full',
                            section.accuracy >= 70
                                ? 'bg-emerald-400'
                                : 'bg-rose-400',
                        ]"
                        :style="{ width: `${section.accuracy}%` }"
                    />
                </div>
            </div>
        </div>
        <div
            v-if="result.weakest_sections.length"
            class="mt-4 rounded-md bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950 dark:text-blue-300"
        >
            優先して復習する分野：
            <strong>{{ result.weakest_sections.join('・') }}</strong>
        </div>
        <div v-if="remediation.length" class="mt-3 grid gap-2 sm:grid-cols-2">
            <Link
                v-for="item in remediation"
                :key="item.lesson_id"
                :href="item.href"
                class="rounded-md border border-blue-200 bg-white p-3 text-left transition hover:border-[#2864f0] dark:border-blue-900 dark:bg-gray-900"
            >
                <p class="text-[10px] font-bold text-gray-400">
                    {{ item.unit_name }}・失点{{ item.missed_points }}点
                </p>
                <p class="mt-1 text-sm font-semibold text-[#285ac8]">
                    {{ item.lesson_name }}を復習 →
                </p>
                <p class="mt-1 text-[10px] text-gray-400">
                    間違い・未回答 {{ item.missed_count }}問
                </p>
            </Link>
        </div>
    </section>
    <div class="mt-4 grid grid-cols-2 gap-3">
        <Link
            href="/mock-exams"
            class="flex items-center justify-center gap-1 rounded-md border border-blue-200 py-3 text-sm font-semibold text-[#285ac8]"
            ><RotateCcw class="size-4" />模試一覧</Link
        ><Link
            href="/review"
            class="rounded-md bg-[#2864f0] py-3 text-center text-sm font-semibold text-white"
            >模試で間違えた問題を復習</Link
        >
    </div>
    <section class="mt-6">
        <h2 class="mb-3 text-lg font-semibold text-gray-700 dark:text-gray-100">
            問題ごとの見直し
        </h2>
        <div class="space-y-2">
            <article
                v-for="item in review"
                :key="item.position"
                :class="[
                    'rounded-md border bg-white dark:bg-gray-900',
                    item.correct
                        ? 'border-emerald-100 dark:border-emerald-900'
                        : 'border-rose-100 dark:border-rose-900',
                ]"
            >
                <button
                    class="flex w-full items-center gap-3 p-3 text-left"
                    @click="toggle(item.position)"
                >
                    <span
                        :class="[
                            'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white',
                            item.correct ? 'bg-emerald-400' : 'bg-rose-400',
                        ]"
                        >{{ item.position }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-bold text-gray-700 dark:text-gray-200"
                        >
                            {{ item.question_text }}
                        </p>
                        <p class="text-[10px] text-gray-400">
                            {{ item.unit_name }}・{{ item.points }}点
                        </p>
                    </div>
                    <ChevronUp
                        v-if="open.includes(item.position)"
                        class="size-4"
                    /><ChevronDown v-else class="size-4" />
                </button>
                <div
                    v-if="open.includes(item.position)"
                    class="border-t p-4 text-xs leading-relaxed dark:border-gray-800"
                >
                    <p>
                        あなたの解答：
                        <strong>{{ item.given_answer ?? '未回答' }}</strong
                        >　正解：
                        <strong class="text-emerald-600">{{
                            item.correct_answer
                        }}</strong>
                    </p>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">
                        {{ item.explanation }}
                    </p>
                    <div
                        v-if="item.official_sources.length"
                        class="mt-2 border-t border-blue-100 pt-2 dark:border-gray-700"
                    >
                        <p class="mb-1 font-bold text-gray-500">
                            関連する公式資料
                        </p>
                        <a
                            v-for="source in item.official_sources"
                            :key="source.url"
                            :href="source.url"
                            class="mr-3 inline-block font-bold text-[#285ac8] underline"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ source.label }}
                        </a>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
