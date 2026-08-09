<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, CheckCircle2, RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';
import ReferenceSheetsModal from '@/components/ReferenceSheetsModal.vue';
import { useSoundEffects } from '@/composables/useSoundEffects';
import { postJson } from '@/lib/api';
import type { AnswerResult, PlayerQuestion, ReferenceSheetData } from '@/types';

type ReviewQuestion = PlayerQuestion & { unit_name: string; box: number };

const props = defineProps<{
    questions: ReviewQuestion[];
    reference_sheets: ReferenceSheetData[];
}>();

const index = ref(0);
const selectedChoice = ref<string | null>(null);
const numericInput = ref('');
const result = ref<AnswerResult | null>(null);
const checking = ref(false);
const sheetsOpen = ref(false);
const finished = ref(false);
const correctCount = ref(0);
const errorMessage = ref<string | null>(null);
const sound = useSoundEffects();

const current = computed(() => props.questions[index.value]);
const progress = computed(() =>
    props.questions.length === 0
        ? 100
        : ((index.value + (result.value ? 1 : 0)) / props.questions.length) *
          100,
);
const canCheck = computed(() =>
    current.value?.type === 'choice'
        ? selectedChoice.value !== null
        : numericInput.value.trim() !== '',
);

async function check() {
    if (!current.value || checking.value || !canCheck.value) {
        return;
    }

    checking.value = true;
    errorMessage.value = null;

    try {
        result.value = await postJson<AnswerResult>('/answers', {
            question_id: current.value.id,
            answer:
                current.value.type === 'choice'
                    ? selectedChoice.value
                    : numericInput.value.trim(),
            context: 'review',
            lesson_id: null,
        });

        if (result.value.correct) {
            correctCount.value++;
        }

        if (result.value.correct) {
            sound.correct();
        } else {
            sound.incorrect();
        }
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : '通信エラーが発生しました';
    } finally {
        checking.value = false;
    }
}

function next() {
    if (index.value >= props.questions.length - 1) {
        finished.value = true;

        return;
    }

    index.value++;
    selectedChoice.value = null;
    numericInput.value = '';
    result.value = null;
}
</script>

<template>
    <Head title="ふくしゅう" />

    <div
        v-if="questions.length === 0"
        class="flex min-h-[65vh] flex-col items-center justify-center text-center"
    >
        <Kyuchan mood="happy" :size="130" />
        <h1 class="mt-3 text-xl font-semibold text-gray-700 dark:text-gray-100">
            今日の復習は完了！
        </h1>
        <p class="mt-1 text-sm text-gray-400">
            忘れたころに、またきゅーちゃんがお知らせします。
        </p>
        <Link
            href="/learn"
            class="mt-5 rounded-md bg-[#2864f0] px-6 py-3 font-semibold text-white shadow-sm"
            >新しいレッスンへ</Link
        >
    </div>

    <div
        v-else-if="finished"
        class="flex min-h-[65vh] flex-col items-center justify-center text-center"
    >
        <Kyuchan mood="cheer" :size="140" />
        <CheckCircle2 class="mt-2 size-9 text-emerald-500" />
        <h1
            class="mt-2 text-2xl font-semibold text-gray-700 dark:text-gray-100"
        >
            復習おつかれさま！
        </h1>
        <p class="mt-1 text-sm font-bold text-gray-500">
            {{ correctCount }} / {{ questions.length }} 問正解
        </p>
        <Link
            href="/dashboard"
            class="mt-5 rounded-md bg-emerald-400 px-8 py-3 font-semibold text-white shadow-sm shadow-emerald-500"
            >ホームへ</Link
        >
    </div>

    <template v-else>
        <div class="mb-5">
            <div class="mb-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <RotateCcw class="size-5 text-[#285ac8]" />
                    <h1
                        class="text-lg font-semibold text-gray-700 dark:text-gray-100"
                    >
                        今日の復習
                    </h1>
                </div>
                <span class="text-xs font-bold text-gray-400"
                    >{{ index + 1 }} / {{ questions.length }}</span
                >
            </div>
            <div
                class="h-3 overflow-hidden rounded-full bg-blue-100 dark:bg-gray-800"
            >
                <div
                    class="h-full rounded-full bg-[#2864f0] transition-all"
                    :style="{ width: `${progress}%` }"
                />
            </div>
        </div>

        <div v-if="current" class="flex flex-col gap-4">
            <section
                class="rounded-lg border border-blue-100 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs font-bold text-[#285ac8]">{{
                        current.unit_name
                    }}</span
                    ><span
                        class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold text-[#2864f0] dark:bg-blue-950"
                        >復習ボックス {{ current.box }}</span
                    >
                </div>
                <p
                    class="text-[15px] leading-relaxed font-medium whitespace-pre-wrap text-gray-700 dark:text-gray-200"
                >
                    {{ current.question_text }}
                </p>
                <button
                    v-if="
                        current.is_calculation ||
                        current.reference_sheet_slugs.length
                    "
                    class="mt-3 inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1.5 text-xs font-bold text-sky-600 dark:bg-sky-950"
                    @click="sheetsOpen = true"
                >
                    <BookOpen class="size-4" />資料集をひらく
                </button>
            </section>

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
                            ? 'border-[#2864f0] bg-blue-50 dark:bg-blue-950/40'
                            : 'border-gray-200 dark:border-gray-700',
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
                                ? 'bg-[#2864f0] text-white'
                                : 'bg-gray-100 text-gray-500 dark:bg-gray-800',
                        ]"
                        >{{ choice.key }}</span
                    ><span class="pt-0.5 text-gray-700 dark:text-gray-200">{{
                        choice.text
                    }}</span>
                </button>
            </div>
            <div
                v-else
                class="rounded-md border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
            >
                <label
                    for="review-answer"
                    class="mb-2 block text-xs font-bold text-gray-400"
                    >こたえ（円）</label
                ><input
                    id="review-answer"
                    v-model="numericInput"
                    :disabled="result !== null"
                    inputmode="numeric"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-lg font-bold focus:border-[#2864f0] focus:outline-none dark:border-gray-700 dark:bg-gray-800"
                    @keydown.enter="canCheck && !result && check()"
                />
            </div>

            <div
                v-if="result"
                :class="[
                    'rounded-lg border p-4',
                    result.correct
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950'
                        : 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950',
                ]"
            >
                <div class="mb-2 flex items-center gap-2">
                    <Kyuchan
                        :mood="result.correct ? 'happy' : 'sad'"
                        :size="54"
                    />
                    <div>
                        <p
                            :class="[
                                'font-semibold',
                                result.correct
                                    ? 'text-emerald-600'
                                    : 'text-rose-500',
                            ]"
                        >
                            {{
                                result.correct
                                    ? `せいかい！ +${result.xp_earned} XP`
                                    : 'もう一度覚えよう！'
                            }}
                        </p>
                        <p
                            v-if="!result.correct"
                            class="text-xs font-bold text-gray-600 dark:text-gray-300"
                        >
                            こたえ: {{ result.correct_answer }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="result.selected_feedback"
                    class="mb-2 rounded-md bg-rose-100 px-3 py-2 text-xs font-bold text-rose-700 dark:bg-rose-900/50 dark:text-rose-200"
                >
                    この選択肢が違う理由: {{ result.selected_feedback }}
                </p>
                <p
                    class="text-xs leading-relaxed text-gray-600 dark:text-gray-300"
                >
                    {{ result.explanation }}
                </p>
                <p
                    v-if="result.common_mistake"
                    class="mt-2 text-xs font-bold text-amber-600"
                >
                    ⚠️ {{ result.common_mistake }}
                </p>
                <button
                    class="mt-4 w-full rounded-md bg-[#2864f0] py-3 font-semibold text-white shadow-sm active:shadow-none"
                    @click="next"
                >
                    {{
                        index === questions.length - 1 ? '結果を見る' : 'つぎへ'
                    }}
                </button>
            </div>
            <button
                v-else
                class="rounded-md bg-[#2864f0] py-3 font-semibold text-white shadow-sm active:shadow-none disabled:opacity-40 disabled:shadow-none"
                :disabled="!canCheck || checking"
                @click="check"
            >
                {{ checking ? 'チェック中…' : 'チェック！' }}
            </button>
            <p
                v-if="errorMessage"
                class="text-center text-sm font-bold text-rose-500"
            >
                {{ errorMessage }}
            </p>
        </div>
    </template>

    <ReferenceSheetsModal
        :sheets="reference_sheets"
        :open="sheetsOpen"
        @close="sheetsOpen = false"
    />
</template>
