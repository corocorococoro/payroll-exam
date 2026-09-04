<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Calculator,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Grid3X3,
    Save,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import ReferenceSheetsModal from '@/components/ReferenceSheetsModal.vue';
import { patchJson } from '@/lib/api';
import type { Choice, ReferenceSheetData } from '@/types';

type ExamQuestion = {
    id: number;
    position: number;
    points: number;
    type: 'choice' | 'numeric';
    question_text: string;
    choices: Choice[] | null;
    is_calculation: boolean;
    reference_sheet_slugs: string[];
    unit_name: string;
};
const props = defineProps<{
    attempt: {
        id: number;
        name: string;
        time_limit_minutes: number;
        remaining_seconds: number;
        answers: Record<string, string>;
    };
    questions: ExamQuestion[];
    reference_sheets: ReferenceSheetData[];
}>();

const index = ref(0);
const answers = reactive<Record<string, string>>({ ...props.attempt.answers });
const remaining = ref(props.attempt.remaining_seconds);
const savedAt = ref<string | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);
const sheetsOpen = ref(false);
const sheetOpen = ref(false);
const calculatorOpen = ref(false);
const calcExpression = ref('');
const calcResult = ref<string | null>(null);
const finishing = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;
let autosave: ReturnType<typeof setInterval> | null = null;
let saveRequested = false;
let activeSave: Promise<void> | null = null;

const current = computed(() => props.questions[index.value]);
const answeredCount = computed(
    () => Object.values(answers).filter((value) => value?.trim()).length,
);
const formattedTime = computed(
    () =>
        `${String(Math.floor(remaining.value / 3600)).padStart(2, '0')}:${String(Math.floor((remaining.value % 3600) / 60)).padStart(2, '0')}:${String(remaining.value % 60).padStart(2, '0')}`,
);

function save(): Promise<void> {
    saveRequested = true;

    if (activeSave) {
        return activeSave;
    }

    activeSave = (async () => {
        saving.value = true;
        saveError.value = null;

        while (saveRequested) {
            saveRequested = false;
            const response = await patchJson<{
                saved: boolean;
                saved_at: string;
            }>(`/mock-attempts/${props.attempt.id}`, {
                answers: { ...answers },
            });
            savedAt.value = response.saved_at;
        }
    })()
        .catch((error: unknown) => {
            saveError.value =
                error instanceof Error
                    ? error.message
                    : '解答を保存できませんでした';

            throw error;
        })
        .finally(() => {
            saving.value = false;
            activeSave = null;
        });

    return activeSave;
}

function queueSave() {
    void save().catch(() => undefined);
}

function selectAnswer(value: string) {
    answers[String(current.value.id)] = value;
    queueSave();
}
function go(position: number) {
    index.value = Math.max(0, Math.min(props.questions.length - 1, position));
    sheetOpen.value = false;
}

function calculate() {
    const match = calcExpression.value
        .replace(/\s/g, '')
        .match(/^(-?\d+(?:\.\d+)?)([+\-*/])(-?\d+(?:\.\d+)?)$/);

    if (!match) {
        calcResult.value = '式を確認してください';

        return;
    }

    const left = Number(match[1]);
    const right = Number(match[3]);
    const op = match[2];
    const value =
        op === '+'
            ? left + right
            : op === '-'
              ? left - right
              : op === '*'
                ? left * right
                : right === 0
                  ? NaN
                  : left / right;
    calcResult.value = Number.isFinite(value)
        ? value.toLocaleString('ja-JP', { maximumFractionDigits: 4 })
        : '計算できません';
}

async function finish() {
    if (finishing.value) {
        return;
    }

    finishing.value = true;
    saveError.value = null;

    try {
        await save();
        router.post(`/mock-attempts/${props.attempt.id}/finish`);
    } catch {
        finishing.value = false;
    }
}

onMounted(() => {
    timer = setInterval(() => {
        remaining.value = Math.max(0, remaining.value - 1);

        if (remaining.value === 0) {
            void finish();
        }
    }, 1000);
    autosave = setInterval(queueSave, 15000);
});
onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }

    if (autosave) {
        clearInterval(autosave);
    }
});
</script>

<template>
    <Head :title="attempt.name" />
    <div class="flex min-h-dvh flex-col bg-gray-50 dark:bg-gray-950">
        <header
            class="sticky top-0 z-20 border-b bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-3">
                <a
                    href="/mock-exams"
                    aria-label="模試一覧へ"
                    class="text-gray-400"
                    ><X class="size-5"
                /></a>
                <p
                    class="hidden flex-1 truncate text-sm font-semibold text-gray-700 sm:block dark:text-gray-100"
                >
                    {{ attempt.name }}
                </p>
                <span class="flex items-center gap-1 text-xs text-gray-400"
                    ><Save class="size-3" />{{
                        saveError
                            ? '保存できませんでした'
                            : saving
                              ? '保存中'
                              : savedAt
                                ? '保存済み'
                                : '自動保存'
                    }}</span
                ><span
                    :class="[
                        'flex items-center gap-1 rounded-full px-3 py-1.5 font-mono text-sm font-bold',
                        remaining < 600
                            ? 'bg-rose-100 text-rose-600'
                            : 'bg-blue-100 text-[#285ac8]',
                    ]"
                    ><Clock3 class="size-4" />{{ formattedTime }}</span
                >
            </div>
        </header>
        <main
            class="mx-auto grid w-full max-w-5xl flex-1 gap-4 p-4 pb-24 md:grid-cols-[1fr_260px]"
        >
            <div>
                <div
                    class="mb-3 flex items-center justify-between text-xs font-bold text-gray-400"
                >
                    <span
                        >問 {{ current.position }} / {{ questions.length }}　{{
                            current.unit_name
                        }}</span
                    ><span>{{ current.points }}点</span>
                </div>
                <section
                    class="rounded-lg border border-blue-100 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <p
                        class="text-[15px] leading-relaxed font-medium whitespace-pre-wrap text-gray-700 dark:text-gray-200"
                    >
                        {{ current.question_text }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <button
                            v-if="
                                current.is_calculation ||
                                current.reference_sheet_slugs.length
                            "
                            class="flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1.5 text-xs font-bold text-sky-600"
                            @click="sheetsOpen = true"
                        >
                            <BookOpen class="size-4" />資料集</button
                        ><button
                            class="flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-600"
                            @click="calculatorOpen = !calculatorOpen"
                        >
                            <Calculator class="size-4" />電卓
                        </button>
                    </div>
                </section>
                <div
                    v-if="calculatorOpen"
                    class="mt-3 rounded-md border border-amber-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex gap-2">
                        <input
                            v-model="calcExpression"
                            placeholder="例: 320000*0.0915"
                            class="min-w-0 flex-1 rounded-xl border px-3 py-2 font-mono"
                            @keydown.enter="calculate"
                        /><button
                            class="rounded-xl bg-amber-400 px-4 font-bold text-white"
                            @click="calculate"
                        >
                            =
                        </button>
                    </div>
                    <p
                        v-if="calcResult"
                        class="mt-2 text-right text-lg font-semibold text-amber-600"
                    >
                        {{ calcResult }}
                    </p>
                </div>
                <div
                    v-if="current.type === 'choice' && current.choices"
                    class="mt-4 space-y-2"
                >
                    <button
                        v-for="choice in current.choices"
                        :key="choice.key"
                        :class="[
                            'flex w-full items-start gap-3 rounded-md border bg-white p-3.5 text-left text-sm transition dark:bg-gray-900',
                            answers[String(current.id)] === choice.key
                                ? 'border-[#2864f0] bg-blue-50 dark:bg-blue-950'
                                : 'border-gray-200 dark:border-gray-700',
                        ]"
                        @click="selectAnswer(choice.key)"
                    >
                        <span
                            :class="[
                                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                answers[String(current.id)] === choice.key
                                    ? 'bg-[#2864f0] text-white'
                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-800',
                            ]"
                            >{{ choice.key }}</span
                        ><span
                            class="pt-0.5 text-gray-700 dark:text-gray-200"
                            >{{ choice.text }}</span
                        >
                    </button>
                </div>
                <div
                    v-else
                    class="mt-4 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                >
                    <label class="mb-2 block text-xs font-bold text-gray-400"
                        >解答（数値）</label
                    ><input
                        :value="answers[String(current.id)] ?? ''"
                        inputmode="numeric"
                        class="w-full rounded-xl border border-gray-200 px-4 py-3 text-lg font-bold focus:border-[#2864f0] focus:outline-none dark:border-gray-700 dark:bg-gray-800"
                        @input="
                            answers[String(current.id)] = (
                                $event.target as HTMLInputElement
                            ).value
                        "
                        @blur="queueSave"
                    />
                </div>
                <p
                    v-if="saveError"
                    class="mt-3 text-center text-xs font-bold text-rose-600"
                >
                    {{ saveError }}。通信を確認して、もう一度解答してください。
                </p>
                <div class="mt-5 flex justify-between">
                    <button
                        :disabled="index === 0"
                        class="flex items-center rounded-xl border px-4 py-2 text-sm font-bold disabled:opacity-30"
                        @click="go(index - 1)"
                    >
                        <ChevronLeft class="size-4" />前へ</button
                    ><button
                        v-if="index < questions.length - 1"
                        class="flex items-center rounded-xl bg-[#2864f0] px-5 py-2 text-sm font-bold text-white"
                        @click="go(index + 1)"
                    >
                        次へ<ChevronRight class="size-4" /></button
                    ><button
                        v-else
                        class="rounded-xl bg-rose-500 px-5 py-2 text-sm font-bold text-white"
                        @click="sheetOpen = true"
                    >
                        提出確認
                    </button>
                </div>
            </div>
            <aside
                class="hidden rounded-lg border border-gray-100 bg-white p-4 md:block dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="mb-3 flex justify-between">
                    <h2 class="font-semibold">解答状況</h2>
                    <span class="text-xs text-gray-400"
                        >{{ answeredCount }}/{{ questions.length }}</span
                    >
                </div>
                <div class="grid grid-cols-5 gap-2">
                    <button
                        v-for="(question, i) in questions"
                        :key="question.id"
                        :class="[
                            'aspect-square rounded-lg text-xs font-bold',
                            i === index ? 'ring-2 ring-[#2864f0]' : '',
                            answers[String(question.id)]
                                ? 'bg-[#2864f0] text-white'
                                : 'bg-gray-100 text-gray-500 dark:bg-gray-800',
                        ]"
                        @click="go(i)"
                    >
                        {{ question.position }}
                    </button>
                </div>
                <button
                    class="mt-5 w-full rounded-xl bg-rose-500 py-2.5 text-sm font-semibold text-white"
                    @click="sheetOpen = true"
                >
                    採点する
                </button>
            </aside>
        </main>
        <nav
            class="fixed inset-x-0 bottom-0 border-t bg-white p-3 md:hidden dark:border-gray-800 dark:bg-gray-900"
        >
            <button
                class="mx-auto flex items-center gap-2 rounded-xl bg-[#2864f0] px-5 py-2.5 text-sm font-semibold text-white"
                @click="sheetOpen = true"
            >
                <Grid3X3 class="size-4" />解答状況 {{ answeredCount }}/{{
                    questions.length
                }}
            </button>
        </nav>
        <div
            v-if="sheetOpen"
            class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center"
        >
            <div
                class="w-full max-w-md rounded-lg bg-white p-5 dark:bg-gray-900"
            >
                <h2 class="text-lg font-semibold">提出しますか？</h2>
                <p class="mt-1 text-sm text-gray-500">
                    解答済み {{ answeredCount }} /
                    {{ questions.length }}問。提出後は変更できません。
                </p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button
                        class="rounded-xl border py-3 font-bold"
                        @click="sheetOpen = false"
                    >
                        戻って確認</button
                    ><button
                        :disabled="finishing"
                        class="rounded-xl bg-rose-500 py-3 font-semibold text-white"
                        @click="finish"
                    >
                        {{ finishing ? '採点中…' : '提出して採点' }}
                    </button>
                </div>
            </div>
        </div>
        <ReferenceSheetsModal
            :sheets="reference_sheets"
            :open="sheetsOpen"
            @close="sheetsOpen = false"
        />
    </div>
</template>
