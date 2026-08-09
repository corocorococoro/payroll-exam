<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Enums\QuestionReviewStatus;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestion extends CreateRecord
{
    protected static string $resource = QuestionResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['content_hash'] = Question::contentHash($data);
        $data['review_status'] = QuestionReviewStatus::Draft->value;
        $data['reviewed_content_hash'] = null;
        $data['reviewed_at'] = null;
        $data['review_due_at'] = null;
        $data['is_active'] = false;

        return $data;
    }
}
