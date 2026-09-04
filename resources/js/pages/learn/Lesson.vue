<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    CircleAlert,
    CheckCircle2,
    RotateCcw,
    Target,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';
import ReferenceSheetsModal from '@/components/ReferenceSheetsModal.vue';
import { useSoundEffects } from '@/composables/useSoundEffects';
import { useXpProgress } from '@/composables/useXpProgress';
import { postJson } from '@/lib/api';
import { formatReviewDate } from '@/lib/date';
import type {
    AnswerResult,
    LessonComplete,
    PlayerQuestion,
    ReferenceSheetData,
    XpLevelReward,
} from '@/types';

const props = defineProps<{
    lesson: {
        id: number;
        name: string;
        unit_name: string;
        unit_color: string;
        description: string;
        focus_label: string;
        study_guide: {
            why: string;
            goal: string;
            key_points: string[];
            common_traps: string[];
        };
    };
    questions: PlayerQuestion[];
    reference_sheets: ReferenceSheetData[];
}>();

const index = ref(0);
const started = ref(false);
const selectedChoice = ref<string | null>(null);
const numericInput = ref('');
const result = ref<AnswerResult | null>(null);
const checking = ref(false);
const correctCount = ref(0);
const earnedXp = ref(0);
const sheetsOpen = ref(false);
const completion = ref<LessonComplete | null>(null);
const finishing = ref(false);
const errorMessage = ref<string | null>(null);
const sound = useSoundEffects();
const { sync: syncXp } = useXpProgress();
const levelUps = ref<XpLevelReward[]>([]);

const current = computed(() => props.questions[index.value]);
const isLast = computed(() => index.value >= props.questions.length - 1);
const progress = computed(() => (index.value / props.questions.length) * 100);

const canCheck = computed(() =>
    current.value?.type === 'choice'
        ? selectedChoice.value !== null
        : numericInput.value.trim() !== '',
);

async function check() {
    if (!current.value || checking.value) {
        return;
    }

    checking.value = true;
    errorMessage.value = null;

    try {
        const answer =
            current.value.type === 'choice'
                ? selectedChoice.value!
                : numericInput.value.trim();
        const res = await postJson<AnswerResult>('/answers', {
            question_id: current.value.id,
            answer,
            context: 'lesson',
            lesson_id: props.lesson.id,
        });

        result.value = res;
        syncXp(res.xp_progress);
        earnedXp.value += res.xp_total_earned;

        for (const level of res.level_ups) {
            if (!levelUps.value.some((item) => item.level === level.level)) {
                levelUps.value.push(level);
            }
        }

        if (res.correct) {
            sound.correct();
        } else {
            sound.incorrect();
        }

        if (res.correct) {
            correctCount.value++;
        }
    } catch (e) {
        errorMessage.value =
            e instanceof Error
                ? e.message
                : '通信できませんでした。時間をおいてもう一度お試しください。';
    } finally {
        checking.value = false;
    }
}

async function next() {
    if (isLast.value) {
        await finish();

        return;
    }

    index.value++;
    selectedChoice.value = null;
    numericInput.value = '';
    result.value = null;
}

async function finish() {
    if (finishing.value) {
        return;
    }

    finishing.value = true;
    errorMessage.value = null;

    try {
        completion.value = await postJson<LessonComplete>(
            `/lessons/${props.lesson.id}/complete`,
            {},
        );
        syncXp(completion.value.xp_progress);

        for (const level of completion.value.level_ups) {
            if (!levelUps.value.some((item) => item.level === level.level)) {
                levelUps.value.push(level);
            }
        }

        sound.complete();
    } catch (e) {
        errorMessage.value =
            e instanceof Error
                ? e.message
                : '通信できませんでした。時間をおいてもう一度お試しください。';
    } finally {
        finishing.value = false;
    }
}

function backToTree() {
    router.visit('/learn');
}

const accuracy = computed(() =>
    props.questions.length > 0
        ? Math.round((correctCount.value / props.questions.length) * 100)
        : 0,
);
</script>

<template>
    <Head :title="lesson.name" />

    <div class="flex min-h-dvh flex-col bg-blue-50/60 dark:bg-gray-950">
        <!-- 結果画面 -->
        <div
            v-if="completion"
            class="flex flex-1 flex-col items-center justify-center gap-5 p-6 text-center"
        >
            <Kyuchan mood="clap" effect="confetti" :size="140" />
            <h1 class="text-2xl font-semibold text-gray-700 dark:text-gray-100">
                レッスン完了！🎉
            </h1>

            <div class="text-center">
                <p class="mb-2 text-xs font-bold text-gray-500">
                    完了ボーナス {{ completion.crown_level }}/5回獲得
                </p>
                <div class="flex gap-1" aria-hidden="true">
                    <CheckCircle2
                        v-for="i in 5"
                        :key="i"
                        :class="[
                            'size-8',
                            i <= completion.crown_level
                                ? 'fill-amber-100 text-amber-500'
                                : 'text-gray-200 dark:text-gray-700',
                        ]"
                    />
                </div>
            </div>

            <div class="grid w-full max-w-sm grid-cols-2 gap-3">
                <div
                    class="rounded-md border border-amber-200 bg-white p-4 dark:border-amber-900 dark:bg-gray-900"
                >
                    <p class="text-xs font-bold text-gray-400">獲得XP</p>
                    <p class="text-2xl font-semibold text-amber-500">
                        +{{ earnedXp + completion.xp_total_earned }}
                    </p>
                    <p class="text-[10px] text-gray-400">
                        <template v-if="completion.bonus_xp > 0">
                            今回の完了ボーナス +{{ completion.bonus_xp }}
                        </template>
                        <template v-else
                            >完了ボーナスは5回分すべて獲得済み</template
                        >
                    </p>
                </div>
                <div
                    class="rounded-md border border-emerald-200 bg-white p-4 dark:border-emerald-900 dark:bg-gray-900"
                >
                    <p class="text-xs font-bold text-gray-400">正解率</p>
                    <p class="text-2xl font-semibold text-emerald-500">
                        {{ accuracy }}%
                    </p>
                    <p class="text-[10px] text-gray-400">
                        {{ correctCount }} / {{ questions.length }} 問
                    </p>
                </div>
            </div>

            <div
                v-if="levelUps.length"
                class="w-full max-w-sm rounded-lg border border-amber-200 bg-amber-50 p-4 text-left dark:border-amber-900 dark:bg-amber-950"
            >
                <p class="text-xs font-bold text-amber-600">レベルアップ！</p>
                <p class="mt-1 font-semibold text-gray-800 dark:text-gray-100">
                    Lv.{{ levelUps[levelUps.length - 1].level }}
                    {{ levelUps[levelUps.length - 1].title }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ levelUps[levelUps.length - 1].message }}
                </p>
                <Link
                    href="/league"
                    class="mt-3 inline-flex text-xs font-bold text-[#285ac8]"
                >
                    ごほうびを見る →
                </Link>
            </div>

            <div
                v-if="completion.goal_met"
                class="rounded-md bg-blue-100 px-4 py-2 text-sm font-bold text-[#285ac8] dark:bg-blue-950"
            >
                🔥 今日の目標を達成しました。{{
                    completion.current_streak
                }}日連続です
            </div>
            <p v-else class="text-sm text-gray-500">
                今日の目標まであと
                {{ Math.max(0, completion.daily_goal - completion.today_xp) }}
                XPです
            </p>

            <Link
                v-if="correctCount < questions.length"
                href="/review"
                class="flex w-full max-w-sm items-center justify-center gap-2 rounded-md bg-rose-400 py-3 font-semibold text-white shadow-sm shadow-rose-500 transition hover:bg-rose-500 active:shadow-none"
            >
                <RotateCcw class="size-4" />
                間違えた{{ questions.length - correctCount }}問を今すぐ復習
            </Link>

            <button
                class="w-full max-w-sm rounded-md bg-[#2864f0] py-3 font-semibold text-white shadow-sm transition hover:bg-[#285ac8] active:shadow-none"
                @click="backToTree"
            >
                学習一覧へ戻る
            </button>
        </div>

        <!-- 導入: 問題を解く前に、得点につながるポイントを確認する。 -->
        <main
            v-else-if="!started"
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col px-4 py-5"
        >
            <div class="mb-3 flex items-center justify-between">
                <Link
                    href="/learn"
                    class="rounded-full p-2 text-gray-400 hover:bg-white dark:hover:bg-gray-900"
                    aria-label="学習一覧へ戻る"
                >
                    <X class="size-5" />
                </Link>
                <span
                    class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-[#285ac8] dark:bg-blue-950"
                >
                    {{ lesson.focus_label }}・{{ questions.length }}問
                </span>
            </div>

            <section
                class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex items-start gap-3">
                    <Kyuchan mood="study" effect="focus" :size="88" />
                    <div>
                        <p class="text-xs font-bold text-gray-400">
                            {{ lesson.unit_name }}
                        </p>
                        <h1
                            class="mt-1 text-xl font-semibold text-gray-800 dark:text-gray-100"
                        >
                            {{ lesson.name }}
                        </h1>
                        <p
                            class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300"
                        >
                            {{ lesson.study_guide.why }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded-lg bg-blue-50 p-4 dark:bg-blue-950/50">
                    <p
                        class="flex items-center gap-2 text-xs font-bold text-[#285ac8]"
                    >
                        <Target class="size-4" /> このレッスンで学ぶこと
                    </p>
                    <p
                        class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200"
                    >
                        {{ lesson.study_guide.goal }}
                    </p>
                </div>

                <div class="mt-5">
                    <h2
                        class="text-sm font-semibold text-gray-700 dark:text-gray-200"
                    >
                        先に覚える3点
                    </h2>
                    <ol class="mt-2 space-y-2">
                        <li
                            v-for="(point, pointIndex) in lesson.study_guide
                                .key_points"
                            :key="point"
                            class="flex gap-3 text-sm leading-6 text-gray-600 dark:text-gray-300"
                        >
                            <span
                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950"
                            >
                                {{ pointIndex + 1 }}
                            </span>
                            {{ point }}
                        </li>
                    </ol>
                </div>

                <div
                    class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40"
                >
                    <p
                        class="flex items-center gap-2 text-xs font-bold text-amber-700 dark:text-amber-300"
                    >
                        <CircleAlert class="size-4" /> 間違えやすいところ
                    </p>
                    <ul
                        class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-gray-600 dark:text-gray-300"
                    >
                        <li
                            v-for="trap in lesson.study_guide.common_traps"
                            :key="trap"
                        >
                            {{ trap }}
                        </li>
                    </ul>
                </div>

                <button
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-md bg-[#2864f0] py-3 font-semibold text-white shadow-sm hover:bg-[#285ac8]"
                    @click="started = true"
                >
                    このポイントを使って解く <ArrowRight class="size-4" />
                </button>
            </section>
        </main>

        <!-- プレイヤー画面 -->
        <template v-else>
            <header
                class="sticky top-0 z-10 bg-white/90 backdrop-blur dark:bg-gray-900/90"
            >
                <div
                    class="mx-auto flex max-w-2xl items-center gap-3 px-4 py-3"
                >
                    <Link
                        href="/learn"
                        class="rounded-full p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-label="レッスンを終了する"
                    >
                        <X class="size-5" />
                    </Link>
                    <div
                        class="h-4 flex-1 overflow-hidden rounded-full bg-blue-100 dark:bg-gray-800"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-400 transition-all duration-500"
                            :style="{ width: `${progress}%` }"
                        />
                    </div>
                    <span class="text-xs font-bold text-gray-400"
                        >{{ index + 1 }}/{{ questions.length }}</span
                    >
                </div>
            </header>

            <main
                class="mx-auto w-full max-w-2xl flex-1 px-4 py-5"
                :class="{ 'pb-56': result }"
            >
                <p class="mb-1 text-xs font-bold text-gray-400">
                    {{ lesson.unit_name }} / {{ lesson.name }}
                </p>

                <div v-if="current" class="flex flex-col gap-4">
                    <div
                        class="rounded-lg border border-blue-100 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <p
                            class="text-[15px] leading-relaxed font-medium whitespace-pre-wrap text-gray-700 dark:text-gray-200"
                        >
                            {{ current.question_text }}
                        </p>

                        <button
                            v-if="
                                current.is_calculation ||
                                current.reference_sheet_slugs.length > 0
                            "
                            class="mt-3 flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1.5 text-xs font-bold text-sky-600 hover:bg-sky-200 dark:bg-sky-950 dark:text-sky-300"
                            @click="sheetsOpen = true"
                        >
                            <BookOpen class="size-3.5" /> 資料集を開く
                        </button>
                    </div>

                    <!-- 選択肢 -->
                    <div
                        v-if="current.type === 'choice' && current.choices"
                        class="flex flex-col gap-2"
                    >
                        <button
                            v-for="choice in current.choices"
                            :key="choice.key"
                            :disabled="result !== null"
                            :class="[
                                'flex items-start gap-3 rounded-md border bg-white p-3.5 text-left text-sm font-medium transition dark:bg-gray-900',
                                selectedChoice === choice.key
                                    ? 'border-sky-400 bg-sky-50 dark:bg-sky-950/40'
                                    : 'border-gray-200 hover:border-sky-200 dark:border-gray-700',
                                result &&
                                    result.correct_answer === choice.key &&
                                    'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/40',
                                result &&
                                    !result.correct &&
                                    selectedChoice === choice.key &&
                                    result.correct_answer !== choice.key &&
                                    'border-rose-400 bg-rose-50 dark:bg-rose-950/40',
                            ]"
                            @click="selectedChoice = choice.key"
                        >
                            <span
                                :class="[
                                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    selectedChoice === choice.key
                                        ? 'bg-sky-400 text-white'
                                        : 'bg-gray-100 text-gray-500 dark:bg-gray-800',
                                ]"
                            >
                                {{ choice.key }}
                            </span>
                            <span
                                class="pt-0.5 text-gray-700 dark:text-gray-200"
                                >{{ choice.text }}</span
                            >
                        </button>
                    </div>

                    <!-- 数値入力 -->
                    <div
                        v-else
                        class="rounded-md border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <label
                            class="mb-2 block text-xs font-bold text-gray-400"
                            for="numeric-answer"
                            >答え（円）</label
                        >
                        <input
                            id="numeric-answer"
                            v-model="numericInput"
                            type="text"
                            inputmode="numeric"
                            :disabled="result !== null"
                            placeholder="例: 45000"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-lg font-bold text-gray-700 focus:border-sky-400 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            @keydown.enter="canCheck && !result && check()"
                        />
                    </div>

                    <p
                        v-if="errorMessage"
                        class="text-sm font-bold text-rose-500"
                    >
                        {{ errorMessage }}
                    </p>
                </div>
            </main>

            <!-- 下部バー: チェック / フィードバック -->
            <div class="fixed inset-x-0 bottom-0 z-10">
                <div
                    v-if="result"
                    :class="[
                        'border-t-4',
                        result.correct
                            ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-950'
                            : 'border-rose-300 bg-rose-50 dark:bg-rose-950',
                    ]"
                >
                    <div class="mx-auto flex max-w-2xl flex-col gap-2 p-4">
                        <div class="flex items-center gap-3">
                            <Kyuchan
                                :mood="result.correct ? 'approve' : 'curious'"
                                :effect="
                                    result.correct ? 'sparkle' : 'question'
                                "
                                :size="56"
                            />
                            <div class="flex-1">
                                <p
                                    :class="[
                                        'text-lg font-semibold',
                                        result.correct
                                            ? 'text-emerald-600'
                                            : 'text-rose-500',
                                    ]"
                                >
                                    {{ result.correct ? '正解！' : '不正解' }}
                                    <span
                                        v-if="result.correct"
                                        class="ml-1 text-sm"
                                    >
                                        <template v-if="result.xp_earned > 0">
                                            +{{ result.xp_earned }} XP
                                        </template>
                                        <template v-else>XP獲得済み</template>
                                    </span>
                                </p>
                                <p
                                    v-if="!result.correct"
                                    class="text-sm font-bold text-gray-600 dark:text-gray-300"
                                >
                                    答え：{{ result.correct_answer }}
                                </p>
                                <p
                                    v-if="!result.correct"
                                    class="mt-0.5 text-xs font-bold text-rose-500 dark:text-rose-300"
                                >
                                    今日の復習に追加しました
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="result.xp_bonus_earned > 0"
                            class="rounded-md bg-amber-100 px-3 py-2 text-xs font-bold text-amber-700 dark:bg-amber-900/50 dark:text-amber-200"
                        >
                            🎯 クエスト達成 +{{ result.xp_bonus_earned }} XP
                        </div>

                        <div
                            v-if="result.level_ups.length"
                            class="rounded-md bg-blue-100 px-3 py-2 text-xs text-[#285ac8] dark:bg-blue-950"
                        >
                            <strong>
                                Lv.{{
                                    result.level_ups[
                                        result.level_ups.length - 1
                                    ].level
                                }}
                                {{
                                    result.level_ups[
                                        result.level_ups.length - 1
                                    ].title
                                }}
                            </strong>
                            <span class="ml-1">になりました！</span>
                        </div>

                        <div
                            class="max-h-36 overflow-y-auto rounded-xl bg-white/70 p-3 text-xs leading-relaxed text-gray-600 dark:bg-gray-900/70 dark:text-gray-300"
                        >
                            <p
                                v-if="result.selected_feedback"
                                class="mb-2 rounded-md bg-rose-100 px-3 py-2 font-bold text-rose-700 dark:bg-rose-900/50 dark:text-rose-200"
                            >
                                この選択肢が違う理由：
                                {{ result.selected_feedback }}
                            </p>
                            <p>{{ result.explanation }}</p>
                            <div
                                v-if="result.official_sources.length"
                                class="mt-2 border-t border-blue-100 pt-2 dark:border-gray-700"
                            >
                                <p class="mb-1 font-bold text-gray-500">
                                    関連する公式資料
                                </p>
                                <a
                                    v-for="source in result.official_sources"
                                    :key="source.url"
                                    :href="source.url"
                                    class="mr-3 inline-block font-bold text-[#285ac8] underline"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ source.label }}
                                </a>
                            </div>
                            <p class="mt-2 text-xs font-bold text-[#285ac8]">
                                次回の復習：
                                {{ formatReviewDate(result.next_review_at) }}
                            </p>
                            <p
                                v-if="result.common_mistake"
                                class="mt-1.5 font-bold text-amber-600 dark:text-amber-400"
                            >
                                ⚠️ よくあるミス：{{ result.common_mistake }}
                            </p>
                        </div>

                        <button
                            :class="[
                                'w-full rounded-md py-3 font-semibold text-white transition active:shadow-none',
                                result.correct
                                    ? 'bg-emerald-400 shadow-sm shadow-emerald-500 hover:bg-emerald-500'
                                    : 'bg-rose-400 shadow-sm shadow-rose-500 hover:bg-rose-500',
                            ]"
                            :disabled="finishing"
                            @click="next"
                        >
                            {{ isLast ? '結果を見る' : '次へ' }}
                        </button>
                    </div>
                </div>

                <div
                    v-else
                    class="border-t border-blue-100 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95"
                >
                    <div class="mx-auto max-w-2xl p-4">
                        <button
                            class="w-full rounded-md bg-sky-400 py-3 font-semibold text-white shadow-sm shadow-sky-500 transition hover:bg-sky-500 active:shadow-none disabled:opacity-40 disabled:shadow-none"
                            :disabled="!canCheck || checking"
                            @click="check"
                        >
                            {{ checking ? '確認中…' : '答え合わせ' }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <ReferenceSheetsModal
            :sheets="reference_sheets"
            :open="sheetsOpen"
            show-mascot
            @close="sheetsOpen = false"
        />
    </div>
</template>
