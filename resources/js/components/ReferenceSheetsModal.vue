<script setup lang="ts">
import { X } from '@lucide/vue';
import Kyuchan from '@/components/Kyuchan.vue';
import type { ReferenceSheetData } from '@/types';

withDefaults(
    defineProps<{
        sheets: ReferenceSheetData[];
        open: boolean;
        showMascot?: boolean;
    }>(),
    { showMascot: false },
);

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
                class="relative max-h-[85dvh] w-full max-w-2xl overflow-y-auto rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-lg dark:bg-gray-900"
            >
                <div
                    class="sticky -top-5 z-20 -mx-5 -mt-5 mb-3 flex items-center justify-between gap-3 bg-white px-5 py-3 dark:bg-gray-900"
                >
                    <h2
                        class="text-base font-semibold text-gray-700 dark:text-gray-200"
                    >
                        📖 資料集（2026年度）
                    </h2>
                    <Kyuchan
                        v-if="showMascot"
                        mood="calculate"
                        :size="64"
                        loading="lazy"
                        class="ml-auto"
                    />
                    <button
                        class="rounded-full p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-label="閉じる"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                    アプリ用に編集した学習資料です。出題条件と表の年度・区分を確認して使ってください。
                </p>

                <div class="flex flex-col gap-5">
                    <section
                        v-for="sheet in sheets"
                        :key="sheet.slug"
                        class="rounded-md border border-blue-100 p-3 dark:border-gray-800"
                    >
                        <h3
                            class="mb-2 text-sm font-bold text-gray-600 dark:text-gray-300"
                        >
                            {{ sheet.name }}
                        </h3>

                        <p
                            v-if="sheet.content.note"
                            class="mb-2 text-xs text-gray-500"
                        >
                            {{ sheet.content.note }}
                        </p>

                        <!-- 税額表タイプ -->
                        <template v-if="sheet.content.type === 'tax_table'">
                            <div class="max-h-[50dvh] overflow-auto">
                                <table class="w-full text-xs">
                                    <thead class="sticky top-0 z-10">
                                        <tr
                                            class="bg-blue-50 text-gray-500 dark:bg-gray-800"
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
                                            class="border-b border-blue-50 dark:border-gray-800"
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
                            <p
                                v-if="(sheet.content.columns?.length ?? 0) > 4"
                                class="mb-2 text-xs text-gray-500"
                            >
                                横にスクロールすると、右側の列まで確認できます。
                            </p>
                            <div class="max-h-[50dvh] overflow-auto">
                                <table
                                    class="w-full text-xs"
                                    :class="{
                                        'min-w-max whitespace-nowrap':
                                            (sheet.content.columns?.length ??
                                                0) > 4,
                                    }"
                                >
                                    <thead
                                        v-if="sheet.content.columns"
                                        class="sticky top-0 z-10"
                                    >
                                        <tr
                                            class="bg-blue-50 text-gray-500 dark:bg-gray-800"
                                        >
                                            <th
                                                v-for="(col, j) in sheet.content
                                                    .columns"
                                                :key="col"
                                                class="p-2 text-left font-bold"
                                                :class="{
                                                    'sticky left-0 z-10 bg-blue-50 dark:bg-gray-800':
                                                        j === 0 &&
                                                        sheet.content.columns
                                                            .length > 4,
                                                }"
                                            >
                                                {{ col }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, i) in tableRows(sheet)"
                                            :key="i"
                                            class="border-b border-blue-50 dark:border-gray-800"
                                        >
                                            <td
                                                v-for="(cell, j) in row"
                                                :key="j"
                                                class="p-2"
                                                :class="{
                                                    'sticky left-0 bg-white dark:bg-gray-900':
                                                        j === 0 &&
                                                        (sheet.content.columns
                                                            ?.length ?? 0) > 4,
                                                }"
                                            >
                                                {{ cell }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <details
                                v-for="ex in sheet.content.example_rows ?? []"
                                :key="ex.title"
                                :open="ex.rows.length <= 12"
                                class="mt-3"
                            >
                                <summary
                                    class="mb-2 cursor-pointer text-xs font-bold text-gray-600 dark:text-gray-300"
                                >
                                    {{ ex.title }}
                                </summary>
                                <p
                                    v-if="ex.columns.length > 4"
                                    class="mb-2 text-xs text-gray-500"
                                >
                                    横にスクロールすると、右側の列まで確認できます。
                                </p>
                                <div class="max-h-[50dvh] overflow-auto">
                                    <table
                                        class="w-full text-xs"
                                        :class="{
                                            'min-w-max whitespace-nowrap':
                                                ex.columns.length > 4,
                                        }"
                                    >
                                        <thead class="sticky top-0 z-10">
                                            <tr
                                                class="bg-blue-50 text-gray-500 dark:bg-gray-800"
                                            >
                                                <th
                                                    v-for="(
                                                        col, j
                                                    ) in ex.columns"
                                                    :key="col"
                                                    class="p-2 text-left font-bold"
                                                    :class="{
                                                        'sticky left-0 z-10 bg-blue-50 dark:bg-gray-800':
                                                            j === 0 &&
                                                            ex.columns.length >
                                                                4,
                                                    }"
                                                >
                                                    {{ col }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(row, i) in ex.rows"
                                                :key="i"
                                                class="border-b border-blue-50 dark:border-gray-800"
                                            >
                                                <td
                                                    v-for="(cell, j) in row"
                                                    :key="j"
                                                    class="p-2"
                                                    :class="{
                                                        'sticky left-0 bg-white dark:bg-gray-900':
                                                            j === 0 &&
                                                            ex.columns.length >
                                                                4,
                                                    }"
                                                >
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </template>

                        <ul
                            v-if="sheet.content.notes"
                            class="mt-2 list-inside list-disc text-xs text-gray-500 dark:text-gray-400"
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
