export type Stats = {
    total_xp: number;
    current_streak: number;
    streak_freezes: number;
    today_xp: number;
    goal_met: boolean;
    daily_goal: number;
    exam_date: string;
    days_to_exam: number;
    xp_progress: XpProgress;
};

export type MascotStyleSlug =
    | 'default'
    | 'study-parka'
    | 'payroll-cardigan'
    | 'focus-glasses'
    | 'exam-wish';

export type KyuchanMood =
    | 'normal'
    | 'happy'
    | 'sad'
    | 'cheer'
    | 'wave'
    | 'study'
    | 'think'
    | 'point'
    | 'calculate'
    | 'rest';

export type XpProgress = {
    total_xp: number;
    level: number;
    title: string;
    level_start_xp: number;
    next_level_xp: number | null;
    xp_to_next: number | null;
    progress_percent: number;
    mascot_style: MascotStyleSlug;
    today_xp: number;
    daily_goal: number;
    goal_met: boolean;
    current_streak: number;
};

export type XpLevelReward = {
    level: number;
    threshold: number;
    title: string;
    message: string;
    style: MascotStyleSlug | null;
    style_name: string | null;
};

export type Choice = {
    key: string;
    text: string;
};

export type PlayerQuestion = {
    id: number;
    type: 'choice' | 'numeric';
    question_text: string;
    choices: Choice[] | null;
    is_calculation: boolean;
    reference_sheet_slugs: string[];
};

export type AnswerResult = {
    correct: boolean;
    correct_answer: string;
    explanation: string;
    common_mistake: string | null;
    selected_feedback: string | null;
    xp_earned: number;
    xp_status: 'earned' | 'already_credited' | 'incorrect';
    xp_bonus_earned: number;
    xp_total_earned: number;
    xp_progress: XpProgress;
    level_ups: XpLevelReward[];
};

export type ReferenceSheetData = {
    slug: string;
    name: string;
    content: {
        type: string;
        columns?: string[];
        rows?: unknown[];
        notes?: string[];
        note?: string;
        example_rows?: {
            title: string;
            columns: string[];
            rows: string[][];
        }[];
    };
};

export type SkillTreeLesson = {
    id: number;
    name: string;
    description: string;
    crown_level: number;
    unlocked: boolean;
    question_count: number;
};

export type SkillTreeUnit = {
    id: number;
    slug: string;
    name: string;
    description: string;
    icon: string;
    color: string;
    is_advanced: boolean;
    lessons: SkillTreeLesson[];
};

export type LessonComplete = {
    crown_level: number;
    crown_increased: boolean;
    bonus_xp: number;
    xp_bonus_earned: number;
    xp_total_earned: number;
    total_xp: number;
    current_streak: number;
    today_xp: number;
    goal_met: boolean;
    daily_goal: number;
    xp_progress: XpProgress;
    level_ups: XpLevelReward[];
};
