<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Brain,
    Clock3,
    RotateCcw,
    ShieldCheck,
    Trophy,
} from '@lucide/vue';
import Kyuchan from '@/components/Kyuchan.vue';
</script>

<template>
    <Head title="給与計算実務能力検定2級">
        <meta
            name="description"
            content="給与計算実務能力検定2級の合格に向けて、短いレッスン・復習・公式公開仕様に合わせた診断模試を一つにした学習アプリ。"
        />
    </Head>

    <div
        class="min-h-dvh bg-white text-gray-800 dark:bg-gray-950 dark:text-gray-100"
    >
        <header
            class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 font-semibold text-[#2864f0]"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-sm bg-[#2864f0] text-sm font-bold text-white"
                        >給</span
                    >
                    <span>きゅーよ！</span>
                </Link>
                <nav class="flex items-center gap-2" aria-label="アカウント">
                    <Link
                        v-if="$page.props.auth.user"
                        href="/dashboard"
                        class="vibes-button-primary"
                    >
                        学習を続ける
                    </Link>
                    <template v-else>
                        <Link
                            href="/login"
                            class="px-4 py-2 text-sm font-semibold text-gray-700 hover:text-[#285ac8] dark:text-gray-200"
                        >
                            ログイン
                        </Link>
                        <Link href="/register" class="vibes-button-primary">
                            無料ではじめる
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section
                class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-14 md:grid-cols-[1.15fr_0.85fr] md:py-20"
            >
                <div>
                    <p
                        class="mb-4 inline-flex rounded-sm bg-blue-50 px-3 py-1.5 text-xs font-semibold text-[#285ac8] dark:bg-gray-900"
                    >
                        2026年度・給与計算実務能力検定2級対応
                    </p>
                    <h1
                        class="text-4xl leading-tight font-bold tracking-tight text-[#323232] sm:text-5xl dark:text-white"
                    >
                        毎日ひと区切り。<br />合格まで、迷わない。
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-8 text-gray-600 dark:text-gray-300"
                    >
                        労働法、給与計算、税、社会保険を短いレッスンで積み上げ、忘れた問題は自動で復習。40問の診断模試まで、一つの学習ループで仕上げます。
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="
                                $page.props.auth.user
                                    ? '/dashboard'
                                    : '/register'
                            "
                            class="vibes-button-primary px-7 py-3.5 text-base"
                        >
                            {{
                                $page.props.auth.user
                                    ? '今日の学習へ'
                                    : '学習をはじめる'
                            }}
                        </Link>
                        <Link
                            v-if="!$page.props.auth.user"
                            href="/login"
                            class="vibes-button-secondary px-7 py-3 text-base"
                        >
                            ログイン
                        </Link>
                    </div>
                    <div
                        class="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm font-bold text-gray-500"
                    >
                        <span class="flex items-center gap-1.5"
                            ><Clock3 class="size-4 text-sky-500" />1回
                            最大10問</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><Trophy
                                class="size-4 text-amber-500"
                            />合格基準70点</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><ShieldCheck
                                class="size-4 text-emerald-500"
                            />サーバー採点</span
                        >
                    </div>
                </div>

                <div
                    class="relative mx-auto flex min-h-80 w-full max-w-md items-center justify-center rounded-md border border-blue-100 bg-[#f3f7ff] p-8 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div
                        class="absolute top-6 left-6 rounded-sm border border-gray-200 bg-white px-4 py-3 shadow-xs dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p class="text-xs font-bold text-gray-400">
                            今日のゴール
                        </p>
                        <p class="text-lg font-bold text-[#285ac8]">20 XP</p>
                    </div>
                    <Kyuchan mood="clap" effect="confetti" :size="190" />
                    <div
                        class="absolute right-6 bottom-6 rounded-sm border border-gray-200 bg-white px-4 py-3 shadow-xs dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p class="text-xs font-bold text-gray-400">
                            公式公開仕様
                        </p>
                        <p class="text-lg font-bold text-emerald-500">
                            40問 / 120分
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 pb-20">
                <div
                    class="grid gap-4 border-t border-gray-200 pt-8 md:grid-cols-3"
                >
                    <article
                        class="rounded-md border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <BookOpen class="mb-4 size-7 text-[#2864f0]" />
                        <h2 class="font-semibold">短く、毎日続けられる</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">
                            問題バンクを一気に解かず、毎回最大10問のレッスンで理解と習慣を積み上げます。
                        </p>
                    </article>
                    <article
                        class="rounded-md border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <RotateCcw class="mb-4 size-7 text-[#2864f0]" />
                        <h2 class="font-semibold">間違いを、合格力に変える</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">
                            誤答は復習キューへ。記憶の定着に合わせて出し直し、弱点を放置しません。
                        </p>
                    </article>
                    <article
                        class="rounded-md border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <Brain class="mb-4 size-7 text-[#2864f0]" />
                        <h2 class="font-semibold">本番の配点で現在地を知る</h2>
                        <p class="mt-2 text-sm leading-6 text-gray-500">
                            知識35問・計算5問の模試を100点満点で採点し、次に補強する分野を示します。
                        </p>
                    </article>
                </div>
                <p class="mt-8 text-center text-xs leading-5 text-gray-400">
                    解答後の「関連する公式資料」から、法令や制度の原文まで確認できます。
                </p>
            </section>
        </main>
    </div>
</template>
