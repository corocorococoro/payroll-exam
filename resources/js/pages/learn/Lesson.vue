<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Crown, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';
import ReferenceSheetsModal from '@/components/ReferenceSheetsModal.vue';
import { useSoundEffects } from '@/composables/useSoundEffects';
import { postJson } from '@/lib/api';
import type {
    AnswerResult,
    LessonComplete,
    PlayerQuestion,
    ReferenceSheetData,
} from '@/types';

const props = defineProps<{
    lesson: { id: number; name: string; unit_name: string; unit_color: string };
    questions: PlayerQuestion[];
    reference_sheets: ReferenceSheetData[];
}>();

const index = ref(0);
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

        if (res.correct) {
            sound.correct();
        } else {
            sound.incorrect();
        }

        if (res.correct) {
            correctCount.value++;
            earnedXp.value += res.xp_earned;
        }
    } catch (e) {
        errorMessage.value =
            e instanceof Error ? e.message : '通信エラーが発生しました';
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
        sound.complete();
    } catch (e) {
        errorMessage.value =
            e instanceof Error ? e.message : '通信エラーが発生しました';
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
            <Kyuchan mood="cheer" :size="140" />
            <h1 class="text-2xl font-semibold text-gray-700 dark:text-gray-100">
                レッスンクリア！🎉
            </h1>

            <div class="flex gap-1">
                <Crown
                    v-for="i in 5"
                    :key="i"
                    :class="[
                        'size-8',
                        i <= completion.crown_level
                            ? 'fill-amber-400 text-amber-400'
                            : 'text-gray-200 dark:text-gray-700',
                    ]"
                />
            </div>

            <div class="grid w-full max-w-sm grid-cols-2 gap-3">
                <div
                    class="rounded-md border border-amber-200 bg-white p-4 dark:border-amber-900 dark:bg-gray-900"
                >
                    <p class="text-xs font-bold text-gray-400">かくとくXP</p>
                    <p class="text-2xl font-semibold text-amber-500">
                        +{{ earnedXp + completion.bonus_xp }}
                    </p>
                    <p class="text-[10px] text-gray-400">
                        クリアボーナス +{{ completion.bonus_xp }} 込み
                    </p>
                </div>
                <div
                    class="rounded-md border border-emerald-200 bg-white p-4 dark:border-emerald-900 dark:bg-gray-900"
                >
                    <p class="text-xs font-bold text-gray-400">せいかいりつ</p>
                    <p class="text-2xl font-semibold text-emerald-500">
                        {{ accuracy }}%
                    </p>
                    <p class="text-[10px] text-gray-400">
                        {{ correctCount }} / {{ questions.length }} 問
                    </p>
                </div>
            </div>

            <div
                v-if="completion.goal_met"
                class="rounded-md bg-blue-100 px-4 py-2 text-sm font-bold text-[#285ac8] dark:bg-blue-950"
            >
                🔥 今日のゴール達成！ストリーク
                {{ completion.current_streak }} 日目
            </div>
            <p v-else class="text-sm text-gray-500">
                今日はあと
                {{ Math.max(0, completion.daily_goal - completion.today_xp) }}
                XP でゴール達成！
            </p>

            <button
                class="w-full max-w-sm rounded-md bg-[#2864f0] py-3 font-semibold text-white shadow-sm transition hover:bg-[#285ac8] active:shadow-none"
                @click="backToTree"
            >
                スキルツリーへもどる
            </button>
        </div>

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
                        aria-label="やめる"
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
                            <BookOpen class="size-3.5" /> 資料集をひらく
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
                            >こたえ（円）</label
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
                                :mood="result.correct ? 'happy' : 'sad'"
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
                                    {{
                                        result.correct
                                            ? 'せいかい！'
                                            : 'ざんねん…'
                                    }}
                                    <span
                                        v-if="
                                            result.correct && result.xp_earned
                                        "
                                        class="ml-1 text-sm"
                                        >+{{ result.xp_earned }} XP</span
                                    >
                                </p>
                                <p
                                    v-if="!result.correct"
                                    class="text-sm font-bold text-gray-600 dark:text-gray-300"
                                >
                                    こたえ: {{ result.correct_answer }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="max-h-36 overflow-y-auto rounded-xl bg-white/70 p-3 text-xs leading-relaxed text-gray-600 dark:bg-gray-900/70 dark:text-gray-300"
                        >
                            <p
                                v-if="result.selected_feedback"
                                class="mb-2 rounded-md bg-rose-100 px-3 py-2 font-bold text-rose-700 dark:bg-rose-900/50 dark:text-rose-200"
                            >
                                この選択肢が違う理由:
                                {{ result.selected_feedback }}
                            </p>
                            <p>{{ result.explanation }}</p>
                            <p
                                v-if="result.common_mistake"
                                class="mt-1.5 font-bold text-amber-600 dark:text-amber-400"
                            >
                                ⚠️ よくあるミス: {{ result.common_mistake }}
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
                            {{ isLast ? 'けっかをみる' : 'つぎへ' }}
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
                            {{ checking ? 'チェック中…' : 'チェック！' }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <ReferenceSheetsModal
            :sheets="reference_sheets"
            :open="sheetsOpen"
            @close="sheetsOpen = false"
        />
    </div>
</template>
