<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Flame,
} from '@lucide/vue';
import { ref } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';

const props = defineProps<{
    defaults: {
        daily_goal: number;
        reminder_time: string;
        reminder_enabled: boolean;
        exam_date: string;
    };
}>();

const step = ref(1);
const dailyGoal = ref(props.defaults.daily_goal);
const reminderEnabled = ref(props.defaults.reminder_enabled);
const reminderTime = ref(props.defaults.reminder_time);
const examDate = ref(props.defaults.exam_date);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const goals = [
    { value: 10, label: 'ゆるっと', note: '約3分' },
    { value: 20, label: 'コツコツ', note: '約5分' },
    { value: 30, label: 'しっかり', note: '約10分' },
    { value: 50, label: '本気！', note: '約15分' },
];

function submit() {
    processing.value = true;
    router.post(
        '/onboarding',
        {
            daily_goal: dailyGoal.value,
            reminder_enabled: reminderEnabled.value,
            reminder_time: reminderEnabled.value ? reminderTime.value : null,
            exam_date: examDate.value,
        },
        {
            onError: (value) => {
                errors.value = value;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="はじめの設定" />
    <main
        class="flex min-h-dvh items-center justify-center bg-gradient-to-br from-blue-50 via-amber-50 to-sky-50 p-4 dark:from-gray-950 dark:via-gray-900 dark:to-sky-950"
    >
        <div
            class="w-full max-w-md rounded-lg border border-white bg-white/90 p-6 shadow-xl/60 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90 dark:shadow-none"
        >
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-[#2864f0]">
                        きゅーよ！をはじめよう
                    </p>
                    <h1
                        class="text-xl font-semibold text-gray-700 dark:text-gray-100"
                    >
                        あなたの学習プラン
                    </h1>
                </div>
                <Kyuchan :mood="step === 3 ? 'happy' : 'normal'" :size="72" />
            </div>
            <div class="mb-6 flex gap-2">
                <div
                    v-for="i in 3"
                    :key="i"
                    :class="[
                        'h-2 flex-1 rounded-full transition',
                        i <= step
                            ? 'bg-[#2864f0]'
                            : 'bg-gray-100 dark:bg-gray-800',
                    ]"
                />
            </div>

            <section v-if="step === 1">
                <div class="mb-4 flex items-center gap-2">
                    <Flame class="size-6 text-[#285ac8]" />
                    <div>
                        <h2
                            class="font-semibold text-gray-700 dark:text-gray-100"
                        >
                            1日の目標を選ぼう
                        </h2>
                        <p class="text-xs text-gray-400">
                            無理なく続けられる量がおすすめ
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        v-for="goal in goals"
                        :key="goal.value"
                        :class="[
                            'rounded-md border p-4 text-left transition',
                            dailyGoal === goal.value
                                ? 'border-[#2864f0] bg-blue-50 dark:bg-blue-950'
                                : 'border-gray-100 hover:border-blue-200 dark:border-gray-800',
                        ]"
                        @click="dailyGoal = goal.value"
                    >
                        <p
                            class="font-semibold text-gray-700 dark:text-gray-100"
                        >
                            {{ goal.label }}
                        </p>
                        <p class="text-xl font-semibold text-[#285ac8]">
                            {{ goal.value }} XP
                        </p>
                        <p class="text-[11px] text-gray-400">
                            {{ goal.note }}
                        </p>
                    </button>
                </div>
            </section>

            <section v-else-if="step === 2">
                <div class="mb-4 flex items-center gap-2">
                    <Bell class="size-6 text-sky-500" />
                    <div>
                        <h2
                            class="font-semibold text-gray-700 dark:text-gray-100"
                        >
                            リマインダー
                        </h2>
                        <p class="text-xs text-gray-400">
                            学習を忘れない時間を決めよう
                        </p>
                    </div>
                </div>
                <label
                    class="flex cursor-pointer items-center justify-between rounded-md border border-sky-100 bg-sky-50 p-4 dark:border-sky-900 dark:bg-sky-950"
                    ><span class="font-bold text-gray-700 dark:text-gray-200"
                        >毎日お知らせする</span
                    ><input
                        v-model="reminderEnabled"
                        type="checkbox"
                        class="size-5 accent-sky-500"
                /></label>
                <div v-if="reminderEnabled" class="mt-4">
                    <label
                        for="reminder"
                        class="mb-1 block text-xs font-bold text-gray-500"
                        >お知らせ時刻</label
                    ><input
                        id="reminder"
                        v-model="reminderTime"
                        type="time"
                        class="w-full rounded-md border border-gray-200 bg-white px-4 py-3 font-bold dark:border-gray-700 dark:bg-gray-800"
                    />
                </div>
                <p
                    v-if="errors.reminder_time"
                    class="mt-2 text-xs font-bold text-rose-500"
                >
                    {{ errors.reminder_time }}
                </p>
            </section>

            <section v-else>
                <div class="mb-4 flex items-center gap-2">
                    <CalendarDays class="size-6 text-rose-500" />
                    <div>
                        <h2
                            class="font-semibold text-gray-700 dark:text-gray-100"
                        >
                            試験日を確認
                        </h2>
                        <p class="text-xs text-gray-400">
                            カウントダウンを表示します
                        </p>
                    </div>
                </div>
                <input
                    v-model="examDate"
                    type="date"
                    class="w-full rounded-md border border-rose-100 bg-rose-50 px-4 py-4 text-lg font-semibold text-gray-700 dark:border-rose-900 dark:bg-rose-950 dark:text-gray-100"
                />
                <p
                    class="mt-3 rounded-md bg-amber-50 p-3 text-xs leading-relaxed text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                >
                    2026年11月22日の2級試験を初期値にしています。別日程の場合は変更できます。
                </p>
                <p
                    v-if="errors.exam_date"
                    class="mt-2 text-xs font-bold text-rose-500"
                >
                    {{ errors.exam_date }}
                </p>
            </section>

            <div class="mt-7 flex gap-3">
                <button
                    v-if="step > 1"
                    class="flex items-center rounded-md border border-gray-200 px-4 py-3 font-bold text-gray-500 dark:border-gray-700"
                    @click="step--"
                >
                    <ChevronLeft class="size-4" />戻る
                </button>
                <button
                    v-if="step < 3"
                    class="flex flex-1 items-center justify-center rounded-md bg-[#2864f0] py-3 font-semibold text-white shadow-sm active:shadow-none"
                    @click="step++"
                >
                    つぎへ<ChevronRight class="size-4" />
                </button>
                <button
                    v-else
                    class="flex-1 rounded-md bg-emerald-400 py-3 font-semibold text-white shadow-sm shadow-emerald-500 active:shadow-none disabled:opacity-50"
                    :disabled="processing"
                    @click="submit"
                >
                    {{ processing ? '保存中…' : '学習をはじめる！' }}
                </button>
            </div>
        </div>
    </main>
</template>
