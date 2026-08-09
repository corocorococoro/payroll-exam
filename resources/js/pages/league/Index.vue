<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Award, Medal, Swords } from '@lucide/vue';

type Row = {
    rank: number;
    name: string;
    avatar: string | null;
    xp: number;
    is_me: boolean;
};
type Badge = {
    id: number;
    name: string;
    description: string;
    icon: string;
    earned: boolean;
};
defineProps<{ leaderboard: Row[]; badges: Badge[]; week_label: string }>();
</script>

<template>
    <Head title="週間リーグとバッジ" />
    <section
        class="rounded-3xl bg-gradient-to-br from-violet-100 to-amber-100 p-5 dark:from-violet-950 dark:to-amber-950"
    >
        <div class="flex items-center gap-3">
            <div
                class="flex size-12 items-center justify-center rounded-2xl bg-white text-violet-500 dark:bg-stone-900"
            >
                <Swords class="size-7" />
            </div>
            <div>
                <h1
                    class="text-xl font-extrabold text-stone-700 dark:text-stone-100"
                >
                    週間リーグ
                </h1>
                <p class="text-xs text-stone-500">
                    {{ week_label }} の獲得XPランキング
                </p>
            </div>
        </div>
    </section>
    <section
        class="mt-4 overflow-hidden rounded-3xl border-2 border-violet-100 bg-white dark:border-stone-800 dark:bg-stone-900"
    >
        <div
            v-if="leaderboard.length === 0"
            class="p-8 text-center text-sm text-stone-400"
        >
            今週の学習を始めるとランキングに参加できます。
        </div>
        <div
            v-for="row in leaderboard"
            :key="`${row.rank}-${row.name}`"
            :class="[
                'flex items-center gap-3 border-b p-3 last:border-0 dark:border-stone-800',
                row.is_me ? 'bg-violet-50 dark:bg-violet-950' : '',
            ]"
        >
            <span
                :class="[
                    'flex size-8 items-center justify-center rounded-full font-extrabold',
                    row.rank === 1
                        ? 'bg-amber-300 text-white'
                        : row.rank === 2
                          ? 'bg-stone-300 text-white'
                          : row.rank === 3
                            ? 'bg-orange-300 text-white'
                            : 'bg-stone-100 text-stone-500 dark:bg-stone-800',
                ]"
                >{{ row.rank }}</span
            ><img
                v-if="row.avatar"
                :src="row.avatar"
                alt=""
                class="size-9 rounded-full"
            />
            <div
                v-else
                class="flex size-9 items-center justify-center rounded-full bg-violet-100 font-bold text-violet-500"
            >
                {{ row.name.slice(0, 1) }}
            </div>
            <p class="min-w-0 flex-1 truncate text-sm font-bold">
                {{ row.name
                }}<span
                    v-if="row.is_me"
                    class="ml-1 text-[10px] text-violet-500"
                    >あなた</span
                >
            </p>
            <span class="font-extrabold text-violet-500">{{ row.xp }} XP</span>
        </div>
    </section>
    <section class="mt-6">
        <h2
            class="mb-3 flex items-center gap-2 text-lg font-extrabold text-stone-700 dark:text-stone-100"
        >
            <Award class="size-5 text-amber-500" />バッジコレクション
        </h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <article
                v-for="badge in badges"
                :key="badge.id"
                :class="[
                    'rounded-2xl border-2 p-4 text-center',
                    badge.earned
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                        : 'border-stone-100 bg-stone-50 grayscale dark:border-stone-800 dark:bg-stone-900',
                ]"
            >
                <span class="text-3xl">{{
                    badge.earned ? badge.icon : '🔒'
                }}</span>
                <h3 class="mt-2 text-sm font-extrabold">{{ badge.name }}</h3>
                <p class="mt-1 text-[10px] leading-relaxed text-stone-400">
                    {{ badge.description }}
                </p>
                <Medal
                    v-if="badge.earned"
                    class="mx-auto mt-2 size-4 text-amber-500"
                />
            </article>
        </div>
    </section>
</template>
