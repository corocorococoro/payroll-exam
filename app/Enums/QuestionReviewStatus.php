<?php

namespace App\Enums;

enum QuestionReviewStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::InReview => 'レビュー待ち',
            self::Approved => '承認済み',
            self::Retired => '廃止',
        };
    }
}
