<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Flame,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    Swords,
    Target,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';
import type { XpProgress } from '@/types';

type Summary = {
    today_xp: number;
    daily_goal: number;
    goal_met: boolean;
    total_xp: number;
    current_streak: number;
    longest_streak: number;
    streak_freezes: number;
    review_due: number;
    days_to_exam: number;
    total_questions: number;
    seen_questions: number;
    mastered_questions: number;
    coverage_percent: number;
    mastery_percent: number;
    core_question_count: number;
    core_seen_questions: number;
    core_mastered_questions: number;
    core_coverage_percent: number;
    core_mastery_percent: number;
    latest_mock_score: number | null;
    readiness_label: string;
    readiness_detail: string;
    qualifying_mock_count: number;
    mock_average: number | null;
    daily_new_target: number;
    daily_new_label: string;
    new_completed_today: number;
    recommended_lesson_id: number | null;
    recommended_lesson_name: string | null;
    next_action_href: string;
    next_action_label: string;
    xp_progress: XpProgress;
};

type UnitAccuracy = {
    slug: string;
    name: string;
    icon: string;
    color: string;
    attempts: number;
    correct: number;
    accuracy: number;
};

type HeatmapDay = { date: string; xp: number; goal_met: boolean };
type Quest = {
    type: string;
    label: string;
    target: number;
    progress: number;
    completed: boolean;
    xp_reward: number;
};
const props = defineProps<{
    summary: Summary;
    accuracy_by_unit: UnitAccuracy[];
    heatmap: HeatmapDay[];
    quests: Quest[];
}>();

const goalPercent = computed(() =>
    Math.min(
        100,
        Math.round(
            (props.summary.today_xp / Math.max(1, props.summary.daily_goal)) *
                100,
        ),
    ),
);
const ringStyle = computed(() => ({
    background: `conic-gradient(#2864f0 ${goalPercent.value * 3.6}deg, #e8efff 0deg)`,
}));

const radarPoints = computed(() => {
    const center = 100;
    const radius = 72;

    return props.accuracy_by_unit
        .map((unit, index) => {
            const angle =
                -Math.PI / 2 +
                (index * Math.PI * 2) / props.accuracy_by_unit.length;
            const distance = radius * (unit.accuracy / 100);

            return `${center + Math.cos(angle) * distance},${center + Math.sin(angle) * distance}`;
        })
        .join(' ');
});

const radarAxes = computed(() =>
    props.accuracy_by_unit.map((unit, index) => {
        const angle =
            -Math.PI / 2 +
            (index * Math.PI * 2) / props.accuracy_by_unit.length;

        return {
            ...unit,
            x: 100 + Math.cos(angle) * 72,
            y: 100 + Math.sin(angle) * 72,
            labelX: 100 + Math.cos(angle) * 92,
            labelY: 100 + Math.sin(angle) * 92,
        };
    }),
);

function heatLevel(day: HeatmapDay): string {
    if (day.xp === 0) {
        return 'bg-gray-100 dark:bg-gray-800';
    }

    if (day.goal_met) {
        return 'bg-emerald-500';
    }

    if (day.xp >= props.summary.daily_goal * 0.6) {
        return 'bg-emerald-300';
    }

    return 'bg-emerald-200 dark:bg-emerald-900';
}
</script>

<template>
    <Head title="ホーム" />

    <section
        class="relative mb-4 overflow-hidden rounded-xl bg-gradient-to-br from-blue-100 via-amber-50 to-rose-100 p-4 sm:p-5 dark:from-blue-950 dark:via-gray-900 dark:to-rose-950"
    >
        <!-- Mobile: the mascot and next action are the visual focus. -->
        <div class="sm:hidden">
            <div
                class="grid min-h-40 grid-cols-[minmax(0,1fr)_148px] items-end"
            >
                <div class="self-center pb-1">
                    <div
                        class="mb-2 flex items-center gap-1 text-sm font-semibold text-[#285ac8]"
                    >
                        <Flame class="size-4 fill-blue-400" />
                        {{
                            summary.current_streak > 0
                                ? `${summary.current_streak}日連続で目標達成`
                                : '今日の目標達成で連続記録が始まります'
                        }}
                    </div>
                    <p
                        class="text-xl leading-snug font-semibold whitespace-pre-line text-gray-800 dark:text-gray-100"
                    >
                        {{
                            summary.goal_met
                                ? '今日の目標を達成しました'
                                : `今日の目標まであと ${Math.max(0, summary.daily_goal - summary.today_xp)} XP`
                        }}
                    </p>
                    <p class="mt-2 text-xs font-bold text-gray-500">
                        今日 {{ summary.today_xp }} /
                        {{ summary.daily_goal }} XP
                    </p>
                </div>
                <Kyuchan
                    class="-mr-3 -mb-2 justify-self-end"
                    :mood="summary.goal_met ? 'approve' : 'study'"
                    :effect="summary.goal_met ? 'confetti' : 'focus'"
                    :size="148"
                />
            </div>
            <Link
                :href="summary.next_action_href"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-md bg-[#2864f0] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#285ac8]"
            >
                <BookOpen class="size-4" /> {{ summary.next_action_label }}
            </Link>
        </div>

        <!-- Tablet and desktop: preserve the at-a-glance goal ring. -->
        <div class="hidden items-center gap-5 sm:flex">
            <div
                class="relative flex size-28 shrink-0 items-center justify-center rounded-full"
                :style="ringStyle"
            >
                <div
                    class="flex size-20 flex-col items-center justify-center rounded-full bg-white dark:bg-gray-900"
                >
                    <span class="text-2xl font-semibold text-[#285ac8]"
                        >{{ goalPercent }}%</span
                    >
                    <span class="text-[10px] font-bold text-gray-400"
                        >今日の目標</span
                    >
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <div
                    class="flex items-center gap-1 text-sm font-semibold text-[#285ac8]"
                >
                    <Flame class="size-4 fill-blue-400" />
                    {{
                        summary.current_streak > 0
                            ? `${summary.current_streak}日連続で目標達成`
                            : '今日の目標達成で連続記録が始まります'
                    }}
                </div>
                <p
                    class="mt-1 text-lg font-semibold text-gray-700 dark:text-gray-100"
                >
                    {{
                        summary.goal_met
                            ? '今日の目標を達成しました'
                            : `今日の目標まであと ${Math.max(0, summary.daily_goal - summary.today_xp)} XP`
                    }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ summary.today_xp }} / {{ summary.daily_goal }} XP
                </p>
                <Link
                    :href="summary.next_action_href"
                    class="mt-3 inline-flex items-center gap-1 rounded-full bg-[#2864f0] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#285ac8]"
                >
                    <BookOpen class="size-4" /> {{ summary.next_action_label }}
                </Link>
            </div>
            <Kyuchan
                class="-my-3 -mr-2 shrink-0"
                :mood="summary.goal_met ? 'approve' : 'study'"
                :effect="summary.goal_met ? 'confetti' : 'focus'"
                :size="124"
            />
        </div>
    </section>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Link
            href="/league"
            class="rounded-md border border-amber-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        >
            <Sparkles class="mb-1 size-5 text-amber-500" />
            <p class="text-xl font-semibold text-gray-700 dark:text-gray-100">
                Lv.{{ summary.xp_progress.level }}
            </p>
            <p class="truncate text-[11px] font-bold text-gray-400">
                {{ summary.xp_progress.title }}
                <template v-if="summary.xp_progress.xp_to_next !== null">
                    ・あと{{ summary.xp_progress.xp_to_next }} XP
                </template>
            </p>
        </Link>
        <div
            class="rounded-md border border-sky-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        >
            <ShieldCheck class="mb-1 size-5 text-sky-500" />
            <p class="text-xl font-semibold text-gray-700 dark:text-gray-100">
                {{ summary.streak_freezes }}枚
            </p>
            <p class="text-[11px] font-bold text-gray-400">
                連続記録のお休み券
            </p>
            <p class="mt-1 text-[10px] leading-tight text-gray-400">
                休んだ日に自動で使います
            </p>
        </div>
        <Link
            href="/review"
            class="rounded-md border border-blue-100 bg-white p-3 transition dark:border-gray-800 dark:bg-gray-900"
        >
            <RotateCcw class="mb-1 size-5 text-[#285ac8]" />
            <p class="text-xl font-semibold text-gray-700 dark:text-gray-100">
                {{ summary.review_due }}問
            </p>
            <p class="text-[11px] font-bold text-gray-400">今日の復習</p>
        </Link>
        <div
            class="rounded-md border border-rose-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        >
            <Trophy class="mb-1 size-5 text-rose-500" />
            <p class="text-xl font-semibold text-gray-700 dark:text-gray-100">
                あと{{ summary.days_to_exam }}日
            </p>
            <p class="text-[11px] font-bold text-gray-400">試験まで</p>
        </div>
    </div>

    <section
        class="mb-4 rounded-lg border border-amber-100 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="mb-3 flex items-center justify-between">
            <h2
                class="flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-100"
            >
                <Target class="size-5 text-amber-500" />今日のクエスト
            </h2>
            <Link
                href="/league"
                class="flex items-center gap-1 text-xs font-bold text-[#285ac8]"
                ><Swords class="size-4" />成長・ごほうび</Link
            >
        </div>
        <div class="space-y-3">
            <div
                v-for="quest in quests"
                :key="quest.type"
                class="flex items-center gap-3"
            >
                <span
                    :class="[
                        'flex size-8 items-center justify-center rounded-full text-sm',
                        quest.completed ? 'bg-emerald-100' : 'bg-amber-100',
                    ]"
                    >{{ quest.completed ? '✓' : '🎯' }}</span
                >
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex justify-between text-xs">
                        <span
                            class="font-bold text-gray-600 dark:text-gray-300"
                            >{{ quest.label }}</span
                        ><span class="text-gray-400"
                            >{{ quest.progress }}/{{ quest.target }}</span
                        >
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                    >
                        <div
                            :class="[
                                'h-full rounded-full',
                                quest.completed
                                    ? 'bg-emerald-400'
                                    : 'bg-amber-400',
                            ]"
                            :style="{
                                width: `${Math.min(100, (quest.progress / quest.target) * 100)}%`,
                            }"
                        />
                    </div>
                </div>
                <span class="text-[10px] font-bold text-amber-500"
                    >+{{ quest.xp_reward }} XP</span
                >
            </div>
        </div>
    </section>

    <section
        class="mb-4 rounded-lg border border-gray-100 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="mb-3 flex items-end justify-between">
            <div>
                <h2 class="font-semibold text-gray-700 dark:text-gray-100">
                    重要問題の学習状況
                </h2>
                <p class="text-[11px] text-gray-400">
                    {{ summary.readiness_label }}：{{
                        summary.readiness_detail
                    }}
                </p>
            </div>
            <p class="text-3xl font-semibold text-[#285ac8]">
                {{ summary.core_coverage_percent
                }}<span class="text-sm">%</span>
            </p>
        </div>
        <div
            class="relative h-4 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
        >
            <div
                class="h-full rounded-full bg-gradient-to-r from-blue-400 to-emerald-400 transition-all"
                :style="{ width: `${summary.core_coverage_percent}%` }"
            />
        </div>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-md bg-blue-50 p-2 dark:bg-blue-950">
                <p class="text-lg font-semibold text-[#285ac8]">
                    {{ summary.new_completed_today }}/{{
                        summary.daily_new_target
                    }}
                </p>
                <p class="text-[10px] font-bold text-gray-400">
                    {{ summary.daily_new_label }}
                </p>
            </div>
            <div class="rounded-md bg-emerald-50 p-2 dark:bg-emerald-950">
                <p class="text-lg font-semibold text-emerald-600">
                    {{ summary.core_mastered_questions }}問
                </p>
                <p class="text-[10px] font-bold text-gray-400">
                    覚えた重要問題
                </p>
            </div>
            <div class="rounded-md bg-rose-50 p-2 dark:bg-rose-950">
                <p class="text-lg font-semibold text-rose-500">
                    {{
                        summary.latest_mock_score === null
                            ? '—'
                            : `${summary.latest_mock_score}点`
                    }}
                </p>
                <p class="text-[10px] font-bold text-gray-400">最新の模試</p>
            </div>
        </div>
        <p class="mt-3 text-[11px] leading-5 text-gray-400">
            重要問題{{ summary.core_question_count }}問のうち
            {{ summary.core_seen_questions }}問を学習し、{{
                summary.core_mastery_percent
            }}%を習得済み。全{{ summary.total_questions }}問のうち
            {{
                summary.seen_questions
            }}問を学習済みです。合格の目安は、初めて取り組む問題で構成された120分の模試2回と、計算問題・分野別の得点で判定します（現在
            {{ summary.qualifying_mock_count }}/2回<span
                v-if="summary.mock_average !== null"
                >・平均{{ summary.mock_average }}点</span
            >）。
        </p>
    </section>

    <section
        class="mb-4 grid gap-4 rounded-lg border border-gray-100 bg-white p-4 sm:grid-cols-[220px_1fr] dark:border-gray-800 dark:bg-gray-900"
    >
        <div>
            <h2 class="font-semibold text-gray-700 dark:text-gray-100">
                分野別正答率
            </h2>
            <svg
                viewBox="0 0 200 200"
                class="mx-auto w-full max-w-[220px] overflow-visible"
                role="img"
                aria-label="分野別正答率のグラフ"
            >
                <circle
                    cx="100"
                    cy="100"
                    r="72"
                    fill="#f8fafc"
                    stroke="#e7e5e4"
                />
                <circle cx="100" cy="100" r="36" fill="none" stroke="#e7e5e4" />
                <line
                    v-for="axis in radarAxes"
                    :key="axis.slug"
                    x1="100"
                    y1="100"
                    :x2="axis.x"
                    :y2="axis.y"
                    stroke="#d6d3d1"
                />
                <polygon
                    v-if="radarPoints"
                    :points="radarPoints"
                    fill="#38bdf8"
                    fill-opacity="0.28"
                    stroke="#0ea5e9"
                    stroke-width="2"
                />
                <text
                    v-for="axis in radarAxes"
                    :key="`${axis.slug}-label`"
                    :x="axis.labelX"
                    :y="axis.labelY"
                    text-anchor="middle"
                    dominant-baseline="middle"
                    class="fill-gray-500 text-[10px] font-bold"
                >
                    {{ axis.icon }}
                </text>
            </svg>
        </div>
        <div class="flex flex-col justify-center gap-2">
            <div
                v-for="unit in accuracy_by_unit"
                :key="unit.slug"
                class="grid grid-cols-[1fr_3rem] items-center gap-2 text-xs"
            >
                <div>
                    <div class="mb-1 flex justify-between">
                        <span class="font-bold text-gray-600 dark:text-gray-300"
                            >{{ unit.icon }} {{ unit.name }}</span
                        ><span class="text-gray-400"
                            >{{ unit.attempts }}問</span
                        >
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                    >
                        <div
                            class="h-full rounded-full bg-sky-400"
                            :style="{ width: `${unit.accuracy}%` }"
                        />
                    </div>
                </div>
                <span class="text-right font-semibold text-sky-500"
                    >{{ unit.accuracy }}%</span
                >
            </div>
        </div>
    </section>

    <section
        class="mb-4 rounded-lg border border-gray-100 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-700 dark:text-gray-100">
                    12週間の学習記録
                </h2>
                <p class="text-[11px] text-gray-400">
                    色が濃いほど獲得XPが多くなります
                </p>
            </div>
            <span class="text-xs font-bold text-gray-400"
                >最長連続記録 {{ summary.longest_streak }}日 🔥</span
            >
        </div>
        <div class="grid grid-flow-col grid-rows-7 gap-1 overflow-x-auto pb-1">
            <div
                v-for="day in heatmap"
                :key="day.date"
                :class="['size-3 rounded-[3px]', heatLevel(day)]"
                :title="`${day.date}: ${day.xp} XP`"
            />
        </div>
    </section>
</template>
