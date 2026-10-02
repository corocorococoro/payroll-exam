<script setup lang="ts">
import { ref } from 'vue';
import type { StudyExample } from '@/types';

defineProps<{ example: StudyExample }>();
const revealed = ref(false);
</script>

<template>
    <section
        class="rounded-xl border border-blue-100 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/40"
    >
        <h2 class="text-sm font-bold text-[#285ac8] dark:text-blue-300">
            まず、例を1つ
        </h2>
        <p
            class="mt-2 text-sm leading-7 whitespace-pre-line text-gray-700 dark:text-gray-200"
        >
            {{ example.question_text }}
        </p>
        <button
            v-if="!revealed"
            class="mt-3 rounded-lg bg-white px-4 py-2 text-sm font-bold text-[#285ac8] dark:bg-gray-900"
            @click="revealed = true"
        >
            考え方と答えを見る
        </button>
        <div
            v-else
            class="mt-3 space-y-2 border-t border-blue-200 pt-3 dark:border-blue-800"
        >
            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">
                {{ example.answer_text }}
            </p>
            <p
                class="text-sm leading-7 whitespace-pre-line text-gray-600 dark:text-gray-300"
            >
                {{ example.explanation }}
            </p>
            <a
                v-for="source in example.official_sources"
                :key="source.url"
                :href="source.url"
                class="mr-3 inline-block text-xs font-bold text-[#285ac8] underline"
                target="_blank"
                rel="noopener noreferrer"
                >{{ source.label }}</a
            >
        </div>
    </section>
</template>
