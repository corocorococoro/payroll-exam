import { usePage } from '@inertiajs/vue3';
import { ref, shallowRef } from 'vue';
import { useSoundEffects } from '@/composables/useSoundEffects';
import { useXpProgress } from '@/composables/useXpProgress';
import type { AnswerResult, Stats } from '@/types';
import type { LearningReward } from '@/types/rewards';

export function useLearningRewards() {
    const page = usePage();
    const sound = useSoundEffects();
    const { sync } = useXpProgress();
    const combo = ref(0);
    const bestCombo = ref(0);
    const earnedXp = ref(0);
    const reward = shallowRef<LearningReward | null>(null);
    let sequence = 0;
    let goalMet = (page.props.stats as Stats | undefined)?.goal_met ?? false;

    function answer(result: AnswerResult) {
        const reachedGoal = !goalMet && result.xp_progress.goal_met;
        goalMet = result.xp_progress.goal_met;
        earnedXp.value += result.xp_total_earned;
        sync(result.xp_progress);

        if (!result.correct) {
            combo.value = 0;
            reward.value = null;
            sound.incorrect();

            return;
        }

        combo.value++;
        bestCombo.value = Math.max(bestCombo.value, combo.value);
        const level = result.level_ups.at(-1);
        const milestone =
            combo.value === 3 || combo.value === 5 || combo.value % 10 === 0;
        let kind: LearningReward['kind'] = 'correct';
        let title = '正解！';
        let detail =
            combo.value > 1
                ? `${combo.value}問連続で正解`
                : result.assisted
                  ? '例を使って解けた！'
                  : 'ひとつ、身についた！';

        if (level) {
            kind = 'level';
            title = `Lv.${level.level} にレベルアップ！`;
            detail = level.title;
            sound.celebrate('level');
        } else if (reachedGoal) {
            kind = 'goal';
            title = '今日の目標、達成！';
            detail = `${result.xp_progress.current_streak}日連続の積み重ね`;
            sound.celebrate('goal');
        } else {
            if (milestone) {
                kind = 'combo';
                title = `${combo.value}連続正解！`;
                detail =
                    combo.value >= 5
                        ? '絶好調、その調子！'
                        : 'いい流れ、きてる！';
            }

            sound.correct(combo.value);
        }

        reward.value = {
            id: ++sequence,
            kind,
            title,
            detail,
            xp: result.xp_total_earned,
        };
    }

    function complete(bonusXp = 0, perfect = false) {
        earnedXp.value += bonusXp;
        reward.value = {
            id: ++sequence,
            kind: perfect ? 'perfect' : 'complete',
            title: perfect ? '全問正解！' : 'やりきった！',
            detail: perfect
                ? 'パーフェクト、おめでとう！'
                : '今日も一歩、前へ。',
            xp: earnedXp.value,
        };
        sound.complete(perfect);
    }

    return {
        combo,
        bestCombo,
        earnedXp,
        reward,
        answer,
        complete,
        unlock: sound.unlock,
    };
}
