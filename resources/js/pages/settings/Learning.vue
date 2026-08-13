<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, CalendarDays, Flame, Volume2 } from '@lucide/vue';
import { ref } from 'vue';
import KyuchanMoment from '@/components/KyuchanMoment.vue';

const props = defineProps<{
    learning: {
        daily_goal: number;
        reminder_enabled: boolean;
        reminder_time: string;
        exam_date: string;
        sound_enabled: boolean;
    };
}>();

const dailyGoal = ref(props.learning.daily_goal);
const reminderEnabled = ref(props.learning.reminder_enabled);
const reminderTime = ref(props.learning.reminder_time);
const examDate = ref(props.learning.exam_date);
const soundEnabled = ref(props.learning.sound_enabled);
const processing = ref(false);
const errors = ref<Record<string, string>>({});
const saved = ref(false);

function submit() {
    processing.value = true;
    saved.value = false;
    router.patch(
        '/settings/learning',
        {
            daily_goal: dailyGoal.value,
            reminder_enabled: reminderEnabled.value,
            reminder_time: reminderEnabled.value ? reminderTime.value : null,
            exam_date: examDate.value,
            sound_enabled: soundEnabled.value,
        },
        {
            onError: (value) => {
                errors.value = value;
            },
            onSuccess: () => {
                errors.value = {};
                saved.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="学習設定" />
    <div>
        <h1 class="text-xl font-semibold">学習設定</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            毎日の目標とお知らせを調整します
        </p>
        <KyuchanMoment
            v-if="saved"
            mood="approve"
            effect="sparkle"
            message="設定したよ。無理なく続けよう！"
            :size="76"
            compact
            class="mt-4 max-w-sm"
        />
        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <section>
                <label class="mb-2 flex items-center gap-2 text-sm font-bold"
                    ><Flame class="size-4 text-[#285ac8]" />1日の XP
                    ゴール</label
                >
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="goal in [10, 20, 30, 50]"
                        :key="goal"
                        type="button"
                        :class="[
                            'rounded-md border py-3 text-sm font-bold',
                            dailyGoal === goal
                                ? 'border-[#2864f0] bg-blue-50 text-[#285ac8] dark:bg-blue-950'
                                : 'border-border',
                        ]"
                        @click="dailyGoal = goal"
                    >
                        {{ goal }}
                    </button>
                </div>
            </section>
            <section class="space-y-3">
                <label
                    class="flex items-center justify-between rounded-md border p-4"
                    ><span class="flex items-center gap-2 text-sm font-bold"
                        ><Bell
                            class="size-4 text-sky-500"
                        />リマインダーメール</span
                    ><input
                        v-model="reminderEnabled"
                        type="checkbox"
                        class="size-5 accent-sky-500"
                /></label>
                <div v-if="reminderEnabled">
                    <label for="learning-reminder" class="mb-1 block text-sm"
                        >お知らせ時刻</label
                    ><input
                        id="learning-reminder"
                        v-model="reminderTime"
                        type="time"
                        class="w-full rounded-md border bg-background px-3 py-2"
                    />
                    <p
                        v-if="errors.reminder_time"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ errors.reminder_time }}
                    </p>
                </div>
            </section>
            <section>
                <label
                    for="exam-date"
                    class="mb-2 flex items-center gap-2 text-sm font-bold"
                    ><CalendarDays class="size-4 text-rose-500" />試験日</label
                ><input
                    id="exam-date"
                    v-model="examDate"
                    type="date"
                    class="w-full rounded-md border bg-background px-3 py-2"
                />
                <p
                    v-if="errors.exam_date"
                    class="mt-1 text-xs text-destructive"
                >
                    {{ errors.exam_date }}
                </p>
            </section>
            <section>
                <label
                    class="flex items-center justify-between rounded-md border p-4"
                    ><span class="flex items-center gap-2 text-sm font-bold"
                        ><Volume2 class="size-4 text-[#285ac8]" />効果音</span
                    ><input
                        v-model="soundEnabled"
                        type="checkbox"
                        class="size-5 accent-blue-500"
                /></label>
            </section>
            <button
                type="submit"
                :disabled="processing"
                class="vibes-button-primary w-full sm:w-auto"
            >
                {{ processing ? '保存中…' : '保存する' }}
            </button>
        </form>
    </div>
</template>
