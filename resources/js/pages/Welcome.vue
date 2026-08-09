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
    <Head title="給与計算実務能力検定2級 合格クエスト">
        <meta
            name="description"
            content="給与計算実務能力検定2級の合格に向けて、短いレッスン・復習・本番形式模試を一つにした学習アプリ。"
        />
    </Head>

    <div
        class="min-h-dvh bg-orange-50 text-stone-700 dark:bg-stone-950 dark:text-stone-100"
    >
        <header
            class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5"
        >
            <Link
                href="/"
                class="flex items-center gap-2 font-extrabold text-orange-500"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-2xl bg-orange-400 text-xl text-white"
                    >給</span
                >
                <span>合格クエスト</span>
            </Link>
            <nav class="flex items-center gap-2" aria-label="アカウント">
                <Link
                    v-if="$page.props.auth.user"
                    href="/dashboard"
                    class="rounded-full bg-orange-400 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-orange-500"
                >
                    学習を続ける
                </Link>
                <template v-else>
                    <Link
                        href="/login"
                        class="rounded-full px-4 py-2 text-sm font-bold hover:bg-white dark:hover:bg-stone-900"
                    >
                        ログイン
                    </Link>
                    <Link
                        href="/register"
                        class="rounded-full bg-orange-400 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-orange-500"
                    >
                        無料ではじめる
                    </Link>
                </template>
            </nav>
        </header>

        <main>
            <section
                class="mx-auto grid max-w-6xl items-center gap-8 px-5 py-12 md:grid-cols-[1.2fr_0.8fr] md:py-20"
            >
                <div>
                    <p
                        class="mb-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-extrabold text-orange-500 shadow-sm dark:bg-stone-900"
                    >
                        2026年度・給与計算実務能力検定2級対応
                    </p>
                    <h1
                        class="text-4xl leading-tight font-black tracking-tight text-stone-800 sm:text-5xl dark:text-white"
                    >
                        毎日7問。<br />合格まで、迷わない。
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-8 text-stone-600 dark:text-stone-300"
                    >
                        労働法、給与計算、税、社会保険を短いレッスンで積み上げ、忘れた問題は自動で復習。本番形式の40問模試まで、一つの学習ループで仕上げます。
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="
                                $page.props.auth.user
                                    ? '/dashboard'
                                    : '/register'
                            "
                            class="rounded-2xl bg-orange-400 px-7 py-3.5 font-extrabold text-white shadow-[0_4px_0] shadow-orange-500 transition hover:bg-orange-500 active:translate-y-1 active:shadow-none"
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
                            class="rounded-2xl border-2 border-orange-200 bg-white px-7 py-3 font-extrabold text-orange-500 hover:border-orange-300 dark:bg-stone-900"
                        >
                            ログイン
                        </Link>
                    </div>
                    <div
                        class="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm font-bold text-stone-500"
                    >
                        <span class="flex items-center gap-1.5"
                            ><Clock3 class="size-4 text-sky-500" />1回7問</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><Trophy
                                class="size-4 text-amber-500"
                            />合格ライン70点</span
                        >
                        <span class="flex items-center gap-1.5"
                            ><ShieldCheck
                                class="size-4 text-emerald-500"
                            />サーバー採点</span
                        >
                    </div>
                </div>

                <div
                    class="relative mx-auto flex min-h-80 w-full max-w-md items-center justify-center rounded-[2.5rem] bg-gradient-to-br from-amber-100 via-white to-sky-100 p-8 shadow-xl shadow-orange-100 dark:from-orange-950 dark:via-stone-900 dark:to-sky-950 dark:shadow-none"
                >
                    <div
                        class="absolute top-6 left-6 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-stone-800"
                    >
                        <p class="text-xs font-bold text-stone-400">
                            今日のゴール
                        </p>
                        <p class="text-lg font-black text-orange-500">20 XP</p>
                    </div>
                    <Kyuchan mood="cheer" :size="190" />
                    <div
                        class="absolute right-6 bottom-6 rounded-2xl bg-white px-4 py-3 shadow-sm dark:bg-stone-800"
                    >
                        <p class="text-xs font-bold text-stone-400">本番形式</p>
                        <p class="text-lg font-black text-emerald-500">
                            40問 / 120分
                        </p>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-5 pb-20">
                <div class="grid gap-4 md:grid-cols-3">
                    <article
                        class="rounded-3xl border-2 border-orange-100 bg-white p-6 dark:border-stone-800 dark:bg-stone-900"
                    >
                        <BookOpen class="mb-4 size-8 text-orange-400" />
                        <h2 class="font-extrabold">短く、毎日続けられる</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-500">
                            長い問題集を一気に解かず、毎回7問のレッスンで理解と習慣を積み上げます。
                        </p>
                    </article>
                    <article
                        class="rounded-3xl border-2 border-violet-100 bg-white p-6 dark:border-stone-800 dark:bg-stone-900"
                    >
                        <RotateCcw class="mb-4 size-8 text-violet-500" />
                        <h2 class="font-extrabold">間違いを、合格力に変える</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-500">
                            誤答は復習キューへ。記憶の定着に合わせて出し直し、弱点を放置しません。
                        </p>
                    </article>
                    <article
                        class="rounded-3xl border-2 border-emerald-100 bg-white p-6 dark:border-stone-800 dark:bg-stone-900"
                    >
                        <Brain class="mb-4 size-8 text-emerald-500" />
                        <h2 class="font-extrabold">本番の配点で現在地を知る</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-500">
                            知識35問・計算5問の模試を100点満点で採点し、次に補強する分野を示します。
                        </p>
                    </article>
                </div>
                <p class="mt-8 text-center text-xs leading-5 text-stone-400">
                    本サービスは学習支援ツールです。合格を保証するものではなく、最新の公式教材・受験案内との併用を推奨します。
                </p>
            </section>
        </main>
    </div>
</template>
