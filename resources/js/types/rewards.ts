export type LearningReward = {
    id: number;
    kind: 'correct' | 'combo' | 'goal' | 'level' | 'complete' | 'perfect';
    title: string;
    detail: string;
    xp: number;
};
