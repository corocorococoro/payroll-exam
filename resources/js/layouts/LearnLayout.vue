<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    ClipboardCheck,
    RotateCcw,
    Settings,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Stats } from '@/types';

const page = usePage();
const stats = computed(() => page.props.stats as Stats | null);

const tabs = [
    { href: '/dashboard', label: 'ホーム', icon: BarChart3 },
    { href: '/learn', label: 'まなぶ', icon: BookOpen },
    { href: '/review', label: 'ふくしゅう', icon: RotateCcw },
    { href: '/mock-exams', label: 'もし', icon: ClipboardCheck },
    { href: '/settings/profile', label: 'せってい', icon: Settings },
];

const isActive = (href: string) => page.url.startsWith(href);
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-orange-50/60 dark:bg-stone-950">
        <!-- ヘッダー: ストリーク / XP / 試験カウントダウン -->
        <header
            class="sticky top-0 z-20 border-b border-orange-100 bg-white/90 backdrop-blur dark:border-stone-800 dark:bg-stone-900/90"
        >
            <div
                class="mx-auto flex h-14 max-w-3xl items-center justify-between px-4"
            >
                <Link
                    href="/dashboard"
                    class="text-lg font-extrabold text-orange-500"
                    >きゅーよ！</Link
                >

                <div
                    v-if="stats"
                    class="flex items-center gap-3 text-sm font-bold"
                >
                    <span
                        class="flex items-center gap-1"
                        :class="
                            stats.goal_met
                                ? 'text-orange-500'
                                : 'text-stone-400'
                        "
                        :title="`ストリーク ${stats.current_streak}日`"
                    >
                        🔥 {{ stats.current_streak }}
                    </span>
                    <span
                        class="flex items-center gap-1 text-sky-500"
                        :title="`合計 ${stats.total_xp} XP`"
                    >
                        💎 {{ stats.total_xp }}
                    </span>
                    <span
                        v-if="stats.days_to_exam >= 0"
                        class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-500 dark:bg-rose-950"
                        title="試験日まで"
                    >
                        あと{{ stats.days_to_exam }}日
                    </span>
                </div>
            </div>
        </header>

        <!-- 今日のゴール進捗バー -->
        <div
            v-if="stats"
            class="border-b border-orange-100 bg-white dark:border-stone-800 dark:bg-stone-900"
        >
            <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-2">
                <div
                    class="h-3 flex-1 overflow-hidden rounded-full bg-orange-100 dark:bg-stone-800"
                >
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-400 transition-all duration-700"
                        :style="{
                            width: `${Math.min(100, (stats.today_xp / stats.daily_goal) * 100)}%`,
                        }"
                    />
                </div>
                <span
                    class="text-xs font-bold text-stone-500 dark:text-stone-400"
                >
                    {{ stats.today_xp }} / {{ stats.daily_goal }} XP
                </span>
            </div>
        </div>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 pt-4 pb-24">
            <slot />
        </main>

        <!-- ボトムタブナビ（モバイルファースト） -->
        <nav
            class="fixed inset-x-0 bottom-0 z-20 border-t border-orange-100 bg-white/95 backdrop-blur dark:border-stone-800 dark:bg-stone-900/95"
        >
            <div class="mx-auto flex max-w-3xl items-stretch justify-around">
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-bold transition-colors"
                    :class="
                        isActive(tab.href)
                            ? 'text-orange-500'
                            : 'text-stone-400 hover:text-orange-400'
                    "
                >
                    <component :is="tab.icon" class="size-5" />
                    {{ tab.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
