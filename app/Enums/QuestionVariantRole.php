<?php

namespace App\Enums;

enum QuestionVariantRole: string
{
    case Recall = 'recall';
    case Application = 'application';
    case Boundary = 'boundary';
    case Misconception = 'misconception';
    case Workflow = 'workflow';
    case Calculation = 'calculation';

    public function label(): string
    {
        return match ($this) {
            self::Recall => '想起',
            self::Application => '適用',
            self::Boundary => '境界判断',
            self::Misconception => '誤概念修正',
            self::Workflow => '手順',
            self::Calculation => '計算',
        };
    }
}
