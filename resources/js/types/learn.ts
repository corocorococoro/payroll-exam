export type Stats = {
    total_xp: number;
    current_streak: number;
    streak_freezes: number;
    today_xp: number;
    goal_met: boolean;
    daily_goal: number;
    exam_date: string;
    days_to_exam: number;
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
    xp_earned: number;
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
    bonus_xp: number;
    total_xp: number;
    current_streak: number;
    today_xp: number;
    goal_met: boolean;
    daily_goal: number;
};
