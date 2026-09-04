<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    ClipboardCheck,
    RotateCcw,
    Settings,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import { useXpProgress } from '@/composables/useXpProgress';
import type { Stats } from '@/types';

const page = usePage();
const pageStats = computed(() => page.props.stats as Stats | null);
const { progress: liveXp, sync: syncXp } = useXpProgress();

watch(pageStats, (value) => syncXp(value?.xp_progress), { immediate: true });

const stats = computed<Stats | null>(() => {
    const value = pageStats.value;

    if (!value || !liveXp.value) {
        return value;
    }

    return {
        ...value,
        total_xp: liveXp.value.total_xp,
        today_xp: liveXp.value.today_xp,
        daily_goal: liveXp.value.daily_goal,
        goal_met: liveXp.value.goal_met,
        current_streak: liveXp.value.current_streak,
        xp_progress: liveXp.value,
    };
});

const tabs = [
    {
        href: '/dashboard',
        label: 'ホーム',
        icon: BarChart3,
        matches: ['/dashboard', '/league'],
    },
    { href: '/learn', label: '学習', icon: BookOpen, matches: ['/learn'] },
    {
        href: '/review',
        label: '復習',
        icon: RotateCcw,
        matches: ['/review'],
    },
    {
        href: '/mock-exams',
        label: '模試',
        icon: ClipboardCheck,
        matches: ['/mock-exams', '/mock-attempts'],
    },
    {
        href: '/settings/profile',
        label: '設定',
        icon: Settings,
        matches: ['/settings'],
    },
];

const isActive = (matches: string[]) =>
    matches.some((path) => page.url.startsWith(path));
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-[#f7f8fb] dark:bg-gray-950">
        <!-- ヘッダー: 連続記録 / XP / 試験までの日数 -->
        <header
            class="sticky top-0 z-20 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mx-auto flex min-h-16 max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6"
            >
                <Link
                    href="/dashboard"
                    class="flex items-center gap-2 text-lg font-semibold text-[#2864f0]"
                    ><span
                        class="flex size-8 items-center justify-center rounded-sm bg-[#2864f0] text-sm font-bold text-white"
                        >給</span
                    ><span>きゅーよ！</span></Link
                >

                <div
                    v-if="stats"
                    class="hidden items-center gap-3 text-sm font-bold md:flex"
                >
                    <span
                        class="flex items-center gap-1"
                        :class="
                            stats.goal_met ? 'text-[#285ac8]' : 'text-gray-400'
                        "
                        :title="
                            stats.current_streak > 0
                                ? `${stats.current_streak}日連続で目標達成`
                                : '今日の目標を達成すると連続記録が始まります'
                        "
                    >
                        🔥 連続記録 {{ stats.current_streak }}日
                    </span>
                    <span
                        class="flex items-center gap-1 text-[#285ac8]"
                        :title="`合計 ${stats.total_xp} XP`"
                    >
                        💎 {{ stats.total_xp }}
                    </span>
                    <span
                        v-if="stats.days_to_exam >= 0"
                        class="rounded-sm bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        title="試験日まで"
                    >
                        あと{{ stats.days_to_exam }}日
                    </span>
                </div>

                <span
                    v-if="stats && stats.days_to_exam >= 0"
                    class="shrink-0 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600 md:hidden dark:bg-rose-950 dark:text-rose-300"
                    title="試験日まで"
                >
                    試験まで {{ stats.days_to_exam }}日
                </span>
            </div>
            <nav
                class="mx-auto hidden max-w-6xl items-end gap-1 px-4 sm:px-6 md:flex"
                aria-label="メインナビゲーション"
            >
                <Link
                    v-for="tab in tabs"
                    :key="`desktop-${tab.href}`"
                    :href="tab.href"
                    class="flex items-center gap-2 border-b-4 px-5 py-3 text-sm font-semibold transition-colors"
                    :class="
                        isActive(tab.matches)
                            ? 'border-[#2864f0] text-[#285ac8]'
                            : 'border-transparent text-gray-700 hover:border-gray-200 hover:text-[#285ac8] dark:text-gray-300'
                    "
                >
                    <component :is="tab.icon" class="size-5" />
                    {{ tab.label }}
                </Link>
            </nav>
        </header>

        <!-- 今日の目標進捗バー -->
        <div
            v-if="stats"
            class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mx-auto max-w-6xl px-4 py-2.5 sm:px-6">
                <div
                    class="mb-2 flex items-center justify-between gap-3 text-xs font-bold md:hidden"
                >
                    <div class="flex items-center gap-4">
                        <span
                            :class="
                                stats.goal_met
                                    ? 'text-[#285ac8]'
                                    : 'text-gray-500'
                            "
                        >
                            🔥 連続記録 {{ stats.current_streak }}日
                        </span>
                        <span class="text-[#285ac8]">
                            💎 {{ stats.total_xp }} XP
                        </span>
                    </div>
                    <span class="shrink-0 text-gray-500 dark:text-gray-400">
                        今日 {{ stats.today_xp }} / {{ stats.daily_goal }} XP
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                    >
                        <div
                            class="h-full rounded-full bg-[#2864f0] transition-all duration-700"
                            :style="{
                                width: `${Math.min(100, (stats.today_xp / Math.max(1, stats.daily_goal)) * 100)}%`,
                            }"
                        />
                    </div>
                    <span
                        class="hidden text-xs font-bold text-gray-500 md:inline dark:text-gray-400"
                    >
                        {{ stats.today_xp }} / {{ stats.daily_goal }} XP
                    </span>
                </div>
            </div>
        </div>

        <main
            class="mx-auto w-full max-w-6xl flex-1 px-4 pt-6 pb-24 sm:px-6 md:pb-10"
        >
            <slot />
        </main>

        <!-- ボトムタブナビ（モバイルファースト） -->
        <nav
            class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 bg-white/95 backdrop-blur md:hidden dark:border-gray-800 dark:bg-gray-900/95"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <div class="mx-auto flex max-w-3xl items-stretch justify-around">
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-bold transition-colors"
                    :class="
                        isActive(tab.matches)
                            ? 'text-[#285ac8]'
                            : 'text-gray-500 hover:text-[#285ac8]'
                    "
                >
                    <component :is="tab.icon" class="size-5" />
                    {{ tab.label }}
                </Link>
            </div>
        </nav>
    </div>
</template>
