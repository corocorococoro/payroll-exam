<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-[#f7f8fb] p-6 md:p-10 dark:bg-gray-950"
    >
        <div
            class="w-full max-w-md rounded-md border border-gray-200 bg-white p-7 shadow-xs sm:p-9 dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex items-center gap-2 text-lg font-semibold text-[#2864f0]"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-sm bg-[#2864f0] text-base font-bold text-white"
                        >
                            給
                        </div>
                        <span>きゅーよ！</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1
                            class="text-xl font-semibold text-gray-800 dark:text-gray-100"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
