<script setup lang="ts">
import { X } from '@lucide/vue';
import type { ReferenceSheetData } from '@/types';

defineProps<{
    sheets: ReferenceSheetData[];
    open: boolean;
}>();

const emit = defineEmits<{ close: [] }>();

type TaxRow = {
    min: number;
    max: number;
    by_dependents: Record<string, number>;
};

const taxRows = (sheet: ReferenceSheetData): TaxRow[] =>
    (sheet.content.rows ?? []) as TaxRow[];
const tableRows = (sheet: ReferenceSheetData): string[][] =>
    (sheet.content.rows ?? []) as string[][];
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
        >
            <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

            <div
                class="relative max-h-[85dvh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl dark:bg-stone-900"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2
                        class="text-base font-extrabold text-stone-700 dark:text-stone-200"
                    >
                        📖 資料集（2026年度）
                    </h2>
                    <button
                        class="rounded-full p-2 text-stone-400 hover:bg-stone-100 dark:hover:bg-stone-800"
                        aria-label="閉じる"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <p class="mb-4 text-xs text-stone-400">
                    本番でも資料集が配布されるよ。表を正しく引く練習をしよう！
                </p>

                <div class="flex flex-col gap-5">
                    <section
                        v-for="sheet in sheets"
                        :key="sheet.slug"
                        class="rounded-2xl border border-orange-100 p-3 dark:border-stone-800"
                    >
                        <h3
                            class="mb-2 text-sm font-bold text-stone-600 dark:text-stone-300"
                        >
                            {{ sheet.name }}
                        </h3>

                        <!-- 税額表タイプ -->
                        <template v-if="sheet.content.type === 'tax_table'">
                            <p
                                v-if="sheet.content.note"
                                class="mb-2 text-xs text-stone-400"
                            >
                                {{ sheet.content.note }}
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr
                                            class="bg-orange-50 text-stone-500 dark:bg-stone-800"
                                        >
                                            <th class="p-2 text-left font-bold">
                                                社会保険料等控除後の給与
                                            </th>
                                            <th
                                                class="p-2 text-right font-bold"
                                            >
                                                扶養0人
                                            </th>
                                            <th
                                                class="p-2 text-right font-bold"
                                            >
                                                扶養1人
                                            </th>
                                            <th
                                                class="p-2 text-right font-bold"
                                            >
                                                扶養2人
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, i) in taxRows(sheet)"
                                            :key="i"
                                            class="border-b border-orange-50 dark:border-stone-800"
                                        >
                                            <td class="p-2">
                                                {{
                                                    row.min.toLocaleString()
                                                }}円以上
                                                {{
                                                    row.max.toLocaleString()
                                                }}円未満
                                            </td>
                                            <td class="p-2 text-right">
                                                {{
                                                    row.by_dependents[
                                                        '0'
                                                    ]?.toLocaleString() ?? '—'
                                                }}円
                                            </td>
                                            <td class="p-2 text-right">
                                                {{
                                                    row.by_dependents[
                                                        '1'
                                                    ]?.toLocaleString() ?? '—'
                                                }}円
                                            </td>
                                            <td class="p-2 text-right">
                                                {{
                                                    row.by_dependents[
                                                        '2'
                                                    ]?.toLocaleString() ?? '—'
                                                }}円
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <!-- 汎用テーブルタイプ -->
                        <template v-else>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead v-if="sheet.content.columns">
                                        <tr
                                            class="bg-orange-50 text-stone-500 dark:bg-stone-800"
                                        >
                                            <th
                                                v-for="col in sheet.content
                                                    .columns"
                                                :key="col"
                                                class="p-2 text-left font-bold"
                                            >
                                                {{ col }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, i) in tableRows(sheet)"
                                            :key="i"
                                            class="border-b border-orange-50 dark:border-stone-800"
                                        >
                                            <td
                                                v-for="(cell, j) in row"
                                                :key="j"
                                                class="p-2"
                                            >
                                                {{ cell }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-for="ex in sheet.content.example_rows ?? []"
                                :key="ex.title"
                                class="mt-3"
                            >
                                <p
                                    class="mb-1 text-xs font-bold text-stone-500"
                                >
                                    {{ ex.title }}
                                </p>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr
                                                class="bg-orange-50 text-stone-500 dark:bg-stone-800"
                                            >
                                                <th
                                                    v-for="col in ex.columns"
                                                    :key="col"
                                                    class="p-2 text-left font-bold"
                                                >
                                                    {{ col }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, i) in ex.rows"
                                                :key="i"
                                                class="border-b border-orange-50 dark:border-stone-800"
                                            >
                                                <td
                                                    v-for="(cell, j) in row"
                                                    :key="j"
                                                    class="p-2"
                                                >
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        <ul
                            v-if="sheet.content.notes"
                            class="mt-2 list-inside list-disc text-xs text-stone-400"
                        >
                            <li v-for="note in sheet.content.notes" :key="note">
                                {{ note }}
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>
    </Teleport>
</template>
