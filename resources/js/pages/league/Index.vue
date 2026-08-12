<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Award, Check, Lock, Medal, Sparkles, Swords } from '@lucide/vue';
import { computed, ref } from 'vue';
import Kyuchan from '@/components/Kyuchan.vue';
import { useXpProgress } from '@/composables/useXpProgress';
import { patchJson } from '@/lib/api';
import type { MascotStyleSlug, XpLevelReward, XpProgress } from '@/types';

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
type MascotStyle = {
    slug: MascotStyleSlug;
    name: string;
    level: number;
    threshold: number;
    unlocked: boolean;
    equipped: boolean;
};

const props = defineProps<{
    leaderboard: Row[];
    badges: Badge[];
    week_label: string;
    xp_progress: XpProgress;
    styles: MascotStyle[];
    levels: XpLevelReward[];
}>();

const progress = ref(props.xp_progress);
const styles = ref(props.styles);
const equipping = ref<MascotStyleSlug | null>(null);
const errorMessage = ref<string | null>(null);
const { sync: syncXp } = useXpProgress();
syncXp(progress.value);

const nextLevel = computed(() =>
    props.levels.find((level) => level.threshold > progress.value.total_xp),
);

async function equip(style: MascotStyle) {
    if (!style.unlocked || style.equipped || equipping.value) {
        return;
    }

    equipping.value = style.slug;
    errorMessage.value = null;

    try {
        const result = await patchJson<{
            xp_progress: XpProgress;
            styles: MascotStyle[];
        }>('/rewards/mascot-style', { style: style.slug });
        progress.value = result.xp_progress;
        styles.value = result.styles;
        syncXp(result.xp_progress);
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : '衣装を変更できませんでした';
    } finally {
        equipping.value = null;
    }
}
</script>

<template>
    <Head title="成長・ごほうび" />
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <section
            class="overflow-hidden rounded-xl bg-gradient-to-br from-blue-100 via-amber-50 to-rose-100 p-5 dark:from-blue-950 dark:via-gray-900 dark:to-rose-950"
        >
            <div
                class="grid grid-cols-[minmax(0,1fr)_128px] items-center gap-2 sm:grid-cols-[minmax(0,1fr)_180px]"
            >
                <div>
                    <p class="text-xs font-bold text-[#285ac8]">
                        きゅーちゃんとの成長
                    </p>
                    <h1
                        class="mt-1 text-2xl font-semibold text-gray-800 dark:text-gray-100"
                    >
                        Lv.{{ progress.level }} {{ progress.title }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        合計 {{ progress.total_xp.toLocaleString() }} XP
                    </p>
                    <div
                        class="mt-4 h-3 overflow-hidden rounded-full bg-white/80 dark:bg-gray-800"
                    >
                        <div
                            class="h-full rounded-full bg-[#2864f0] transition-all duration-500"
                            :style="{ width: `${progress.progress_percent}%` }"
                        />
                    </div>
                    <p class="mt-2 text-xs font-bold text-gray-500">
                        <template
                            v-if="progress.xp_to_next !== null && nextLevel"
                        >
                            あと {{ progress.xp_to_next }} XPで Lv.{{
                                nextLevel.level
                            }}
                            {{ nextLevel.title }}
                        </template>
                        <template v-else>最高レベルに到達しました！</template>
                    </p>
                </div>
                <Kyuchan
                    mood="cheer"
                    :size="160"
                    class="-mr-5 justify-self-end"
                />
            </div>
        </section>

        <section>
            <div class="mb-3 flex items-end justify-between gap-3">
                <div>
                    <h2
                        class="flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-gray-100"
                    >
                        <Sparkles
                            class="size-5 text-amber-500"
                        />きゅーちゃんの衣装
                    </h2>
                    <p class="mt-1 text-xs text-gray-500">
                        XPを積み重ねると、相棒の衣装が増えていきます。
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button
                    v-for="style in styles"
                    :key="style.slug"
                    type="button"
                    :disabled="
                        !style.unlocked || style.equipped || equipping !== null
                    "
                    :class="[
                        'relative flex min-h-52 flex-col items-center rounded-lg border bg-white p-3 text-center transition dark:bg-gray-900',
                        style.equipped
                            ? 'border-[#2864f0] ring-2 ring-blue-100 dark:ring-blue-950'
                            : style.unlocked
                              ? 'border-gray-200 hover:border-blue-300 dark:border-gray-700'
                              : 'border-gray-100 bg-gray-50 dark:border-gray-800',
                    ]"
                    @click="equip(style)"
                >
                    <span
                        v-if="style.equipped"
                        class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-[#2864f0] text-white"
                        title="装備中"
                    >
                        <Check class="size-4" />
                    </span>
                    <Kyuchan
                        mood="normal"
                        :outfit="style.slug"
                        :size="112"
                        :class="!style.unlocked && 'opacity-45 grayscale'"
                    />
                    <p
                        class="mt-1 text-sm font-semibold text-gray-700 dark:text-gray-100"
                    >
                        {{ style.name }}
                    </p>
                    <p class="mt-auto pt-2 text-[11px] font-bold text-gray-400">
                        <template v-if="style.unlocked">
                            {{
                                style.equipped ? '装備中' : 'タップして着替える'
                            }}
                        </template>
                        <template v-else>
                            <Lock class="mr-1 inline size-3" />Lv.{{
                                style.level
                            }}・{{ style.threshold }} XP
                        </template>
                    </p>
                </button>
            </div>
            <p v-if="errorMessage" class="mt-2 text-sm font-bold text-rose-500">
                {{ errorMessage }}
            </p>
        </section>

        <section>
            <h2
                class="mb-3 flex items-center gap-2 text-lg font-semibold text-gray-700 dark:text-gray-100"
            >
                <Award class="size-5 text-amber-500" />バッジコレクション
            </h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <article
                    v-for="badge in badges"
                    :key="badge.id"
                    :class="[
                        'rounded-md border p-4 text-center',
                        badge.earned
                            ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                            : 'border-gray-100 bg-gray-50 grayscale dark:border-gray-800 dark:bg-gray-900',
                    ]"
                >
                    <span class="text-3xl">{{
                        badge.earned ? badge.icon : '🔒'
                    }}</span>
                    <h3 class="mt-2 text-sm font-semibold">{{ badge.name }}</h3>
                    <p class="mt-1 text-[10px] leading-relaxed text-gray-400">
                        {{ badge.description }}
                    </p>
                    <Medal
                        v-if="badge.earned"
                        class="mx-auto mt-2 size-4 text-amber-500"
                    />
                </article>
            </div>
        </section>

        <section>
            <div class="mb-3">
                <h2
                    class="flex items-center gap-2 text-lg font-semibold text-gray-700 dark:text-gray-100"
                >
                    <Swords class="size-5 text-[#285ac8]" />週間リーグ
                </h2>
                <p class="mt-1 text-xs text-gray-500">
                    {{ week_label }} の獲得XPランキング
                </p>
            </div>
            <div
                class="overflow-hidden rounded-lg border border-blue-100 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    v-if="leaderboard.length === 0"
                    class="p-8 text-center text-sm text-gray-400"
                >
                    今週の学習を始めるとランキングに参加できます。
                </div>
                <div
                    v-for="row in leaderboard"
                    :key="`${row.rank}-${row.name}`"
                    :class="[
                        'flex items-center gap-3 border-b p-3 last:border-0 dark:border-gray-800',
                        row.is_me ? 'bg-blue-50 dark:bg-blue-950' : '',
                    ]"
                >
                    <span
                        :class="[
                            'flex size-8 items-center justify-center rounded-full font-semibold',
                            row.rank === 1
                                ? 'bg-amber-300 text-white'
                                : row.rank === 2
                                  ? 'bg-gray-300 text-white'
                                  : row.rank === 3
                                    ? 'bg-blue-300 text-white'
                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-800',
                        ]"
                        >{{ row.rank }}</span
                    >
                    <img
                        v-if="row.avatar"
                        :src="row.avatar"
                        alt=""
                        class="size-9 rounded-full"
                    />
                    <div
                        v-else
                        class="flex size-9 items-center justify-center rounded-full bg-blue-100 font-bold text-[#285ac8]"
                    >
                        {{ row.name.slice(0, 1) }}
                    </div>
                    <p class="min-w-0 flex-1 truncate text-sm font-bold">
                        {{ row.name
                        }}<span
                            v-if="row.is_me"
                            class="ml-1 text-[10px] text-[#285ac8]"
                            >あなた</span
                        >
                    </p>
                    <span class="font-semibold text-[#285ac8]"
                        >{{ row.xp }} XP</span
                    >
                </div>
            </div>
        </section>
    </div>
</template>
