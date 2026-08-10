<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { BellRing, LogOut, Palette, ShieldCheck, UserRound } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const settingsNavItems: NavItem[] = [
    {
        title: 'プロフィール',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'セキュリティ',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: '表示設定',
        href: editAppearance(),
        icon: Palette,
    },
    {
        title: '学習設定',
        href: '/settings/learning',
        icon: BellRing,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-4xl">
        <header class="mb-5">
            <p class="text-xs font-bold tracking-wide text-[#285ac8]">
                ACCOUNT
            </p>
            <h1
                class="mt-1 text-2xl font-semibold text-gray-800 dark:text-gray-100"
            >
                設定
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                アカウントと学習環境を管理します
            </p>
        </header>

        <div class="grid gap-5 lg:grid-cols-[190px_minmax(0,1fr)]">
            <aside>
                <nav
                    class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-1"
                    aria-label="設定メニュー"
                >
                    <Link
                        v-for="item in settingsNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        :class="[
                            'flex min-h-11 items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold transition-colors',
                            isCurrentOrParentUrl(item.href)
                                ? 'border-blue-200 bg-blue-50 text-[#285ac8] dark:border-blue-900 dark:bg-blue-950'
                                : 'border-transparent bg-white text-gray-600 hover:border-gray-200 hover:text-[#285ac8] dark:bg-gray-900 dark:text-gray-300',
                        ]"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        <span>{{ item.title }}</span>
                    </Link>
                </nav>

                <Form action="/logout" method="post" class="mt-2">
                    <button
                        type="submit"
                        class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-semibold text-gray-500 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950"
                    >
                        <LogOut class="size-4" />
                        ログアウト
                    </button>
                </Form>
            </aside>

            <section class="vibes-card min-w-0 space-y-10 p-5 sm:p-7">
                <slot />
            </section>
        </div>
    </div>
</template>
