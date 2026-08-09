<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Check,
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
    estimated_score: number;
    score_evidence: number;
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
type SeasonPhase = {
    key: string;
    label: string;
    period: string;
    focus: string;
    active: boolean;
};

const props = defineProps<{
    summary: Summary;
    accuracy_by_unit: UnitAccuracy[];
    heatmap: HeatmapDay[];
    quests: Quest[];
    season: { current: string; phases: SeasonPhase[] };
}>();

const goalPercent = computed(() =>
    Math.min(
        100,
        Math.round((props.summary.today_xp / props.summary.daily_goal) * 100),
    ),
);
const ringStyle = computed(() => ({
    background: `conic-gradient(#fb923c ${goalPercent.value * 3.6}deg, #ffedd5 0deg)`,
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
        return 'bg-stone-100 dark:bg-stone-800';
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
        class="mb-4 overflow-hidden rounded-3xl bg-gradient-to-br from-orange-100 via-amber-50 to-rose-100 p-5 dark:from-orange-950 dark:via-stone-900 dark:to-rose-950"
    >
        <div class="flex items-center gap-4">
            <div
                class="relative flex size-28 shrink-0 items-center justify-center rounded-full"
                :style="ringStyle"
            >
                <div
                    class="flex size-20 flex-col items-center justify-center rounded-full bg-white dark:bg-stone-900"
                >
                    <span class="text-2xl font-extrabold text-orange-500"
                        >{{ goalPercent }}%</span
                    >
                    <span class="text-[10px] font-bold text-stone-400"
                        >今日のゴール</span
                    >
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <div
                    class="flex items-center gap-1 text-sm font-extrabold text-orange-500"
                >
                    <Flame class="size-4 fill-orange-400" />
                    {{ summary.current_streak }}日ストリーク
                </div>
                <p
                    class="mt-1 text-lg font-extrabold text-stone-700 dark:text-stone-100"
                >
                    {{
                        summary.goal_met
                            ? '今日も達成！すごい！'
                            : `あと ${Math.max(0, summary.daily_goal - summary.today_xp)} XP！`
                    }}
                </p>
                <p class="text-xs text-stone-500">
                    {{ summary.today_xp }} / {{ summary.daily_goal }} XP
                </p>
                <Link
                    href="/learn"
                    class="mt-3 inline-flex items-center gap-1 rounded-full bg-orange-400 px-4 py-2 text-xs font-extrabold text-white shadow-sm hover:bg-orange-500"
                >
                    <BookOpen class="size-4" /> 学習をはじめる
                </Link>
            </div>
            <Kyuchan
                class="hidden sm:block"
                :mood="summary.goal_met ? 'cheer' : 'normal'"
                :size="86"
            />
        </div>
    </section>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div
            class="rounded-2xl border-2 border-amber-100 bg-white p-3 dark:border-stone-800 dark:bg-stone-900"
        >
            <Sparkles class="mb-1 size-5 text-amber-500" />
            <p
                class="text-xl font-extrabold text-stone-700 dark:text-stone-100"
            >
                {{ summary.total_xp }}
            </p>
            <p class="text-[11px] font-bold text-stone-400">合計 XP</p>
        </div>
        <div
            class="rounded-2xl border-2 border-sky-100 bg-white p-3 dark:border-stone-800 dark:bg-stone-900"
        >
            <ShieldCheck class="mb-1 size-5 text-sky-500" />
            <p
                class="text-xl font-extrabold text-stone-700 dark:text-stone-100"
            >
                {{ summary.streak_freezes }}
            </p>
            <p class="text-[11px] font-bold text-stone-400">フリーズ</p>
        </div>
        <Link
            href="/review"
            class="rounded-2xl border-2 border-violet-100 bg-white p-3 transition hover:-translate-y-0.5 dark:border-stone-800 dark:bg-stone-900"
        >
            <RotateCcw class="mb-1 size-5 text-violet-500" />
            <p
                class="text-xl font-extrabold text-stone-700 dark:text-stone-100"
            >
                {{ summary.review_due }}
            </p>
            <p class="text-[11px] font-bold text-stone-400">今日の復習</p>
        </Link>
        <div
            class="rounded-2xl border-2 border-rose-100 bg-white p-3 dark:border-stone-800 dark:bg-stone-900"
        >
            <Trophy class="mb-1 size-5 text-rose-500" />
            <p
                class="text-xl font-extrabold text-stone-700 dark:text-stone-100"
            >
                あと{{ summary.days_to_exam }}日
            </p>
            <p class="text-[11px] font-bold text-stone-400">試験まで</p>
        </div>
    </div>

    <section
        class="mb-4 rounded-3xl border-2 border-amber-100 bg-white p-4 dark:border-stone-800 dark:bg-stone-900"
    >
        <div class="mb-3 flex items-center justify-between">
            <h2
                class="flex items-center gap-2 font-extrabold text-stone-700 dark:text-stone-100"
            >
                <Target class="size-5 text-amber-500" />今日のクエスト
            </h2>
            <Link
                href="/league"
                class="flex items-center gap-1 text-xs font-bold text-violet-500"
                ><Swords class="size-4" />リーグ・バッジ</Link
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
                            class="font-bold text-stone-600 dark:text-stone-300"
                            >{{ quest.label }}</span
                        ><span class="text-stone-400"
                            >{{ quest.progress }}/{{ quest.target }}</span
                        >
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800"
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
        class="mb-4 rounded-3xl border-2 border-stone-100 bg-white p-4 dark:border-stone-800 dark:bg-stone-900"
    >
        <div class="mb-3 flex items-end justify-between">
            <div>
                <h2 class="font-extrabold text-stone-700 dark:text-stone-100">
                    学習スコア目安
                </h2>
                <p class="text-[11px] text-stone-400">
                    問題ごとの最新結果から算出（参考値）
                </p>
            </div>
            <p
                :class="[
                    'text-3xl font-extrabold',
                    summary.estimated_score >= 70
                        ? 'text-emerald-500'
                        : 'text-orange-500',
                ]"
            >
                {{ summary.estimated_score }}<span class="text-sm"> / 100</span>
            </p>
        </div>
        <div
            class="relative h-4 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800"
        >
            <div
                class="h-full rounded-full bg-gradient-to-r from-orange-400 to-emerald-400 transition-all"
                :style="{ width: `${summary.estimated_score}%` }"
            />
            <div class="absolute inset-y-0 left-[70%] w-0.5 bg-stone-600" />
        </div>
        <p class="mt-1 text-right text-[10px] font-bold text-stone-400">
            ▲ 合格ライン 70点
        </p>
        <p class="mt-2 text-[11px] leading-5 text-stone-400">
            根拠:
            {{
                summary.score_evidence
            }}種類の問題。出題範囲の網羅度や本番の得点を保証する値ではありません。
        </p>
    </section>

    <section
        class="mb-4 grid gap-4 rounded-3xl border-2 border-stone-100 bg-white p-4 sm:grid-cols-[220px_1fr] dark:border-stone-800 dark:bg-stone-900"
    >
        <div>
            <h2 class="font-extrabold text-stone-700 dark:text-stone-100">
                分野別正答率
            </h2>
            <svg
                viewBox="0 0 200 200"
                class="mx-auto w-full max-w-[220px] overflow-visible"
                role="img"
                aria-label="分野別正答率レーダーチャート"
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
                    class="fill-stone-500 text-[10px] font-bold"
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
                        <span
                            class="font-bold text-stone-600 dark:text-stone-300"
                            >{{ unit.icon }} {{ unit.name }}</span
                        ><span class="text-stone-400"
                            >{{ unit.attempts }}問</span
                        >
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-stone-100 dark:bg-stone-800"
                    >
                        <div
                            class="h-full rounded-full bg-sky-400"
                            :style="{ width: `${unit.accuracy}%` }"
                        />
                    </div>
                </div>
                <span class="text-right font-extrabold text-sky-500"
                    >{{ unit.accuracy }}%</span
                >
            </div>
        </div>
    </section>

    <section
        class="mb-4 rounded-3xl border-2 border-stone-100 bg-white p-4 dark:border-stone-800 dark:bg-stone-900"
    >
        <div class="mb-3 flex items-center justify-between">
            <div>
                <h2 class="font-extrabold text-stone-700 dark:text-stone-100">
                    学習ヒートマップ
                </h2>
                <p class="text-[11px] text-stone-400">直近12週間</p>
            </div>
            <span class="text-xs font-bold text-stone-400"
                >最長 {{ summary.longest_streak }}日 🔥</span
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

    <section
        class="rounded-3xl border-2 border-stone-100 bg-white p-4 dark:border-stone-800 dark:bg-stone-900"
    >
        <h2 class="mb-3 font-extrabold text-stone-700 dark:text-stone-100">
            合格までのシーズンマップ
        </h2>
        <div class="flex flex-col gap-2">
            <div
                v-for="(phase, index) in season.phases"
                :key="phase.key"
                :class="[
                    'flex items-center gap-3 rounded-2xl p-3',
                    phase.active
                        ? 'bg-orange-100 dark:bg-orange-950'
                        : 'bg-stone-50 dark:bg-stone-800/60',
                ]"
            >
                <div
                    :class="[
                        'flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-extrabold',
                        phase.active
                            ? 'bg-orange-400 text-white'
                            : 'bg-stone-200 text-stone-500 dark:bg-stone-700',
                    ]"
                >
                    <Check
                        v-if="
                            index <
                            season.phases.findIndex((item) => item.active)
                        "
                        class="size-4"
                    /><span v-else>{{ index + 1 }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p
                            class="text-sm font-extrabold text-stone-700 dark:text-stone-200"
                        >
                            {{ phase.label }}
                        </p>
                        <span class="text-[10px] text-stone-400">{{
                            phase.period
                        }}</span>
                    </div>
                    <p class="truncate text-xs text-stone-500">
                        {{ phase.focus }}
                    </p>
                </div>
                <span
                    v-if="phase.active"
                    class="rounded-full bg-orange-400 px-2 py-1 text-[10px] font-extrabold text-white"
                    >いまここ</span
                >
            </div>
        </div>
    </section>
</template>
