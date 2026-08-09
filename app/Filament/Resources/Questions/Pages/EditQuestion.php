<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Enums\QuestionReviewStatus;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Question $record */
        $record = $this->record;
        $contentHash = Question::contentHash($data);
        $contentChanged = $record->content_hash !== $contentHash;
        $data['content_hash'] = $contentHash;

        if ($contentChanged) {
            $data['content_revision'] = $record->content_revision + 1;
            $data['review_status'] = QuestionReviewStatus::InReview->value;
            $data['reviewed_content_hash'] = $record->reviewed_content_hash;
            $data['reviewed_at'] = null;
            $data['review_due_at'] = null;
            $data['is_active'] = false;

            return $data;
        }

        if (($data['review_status'] ?? null) === QuestionReviewStatus::Approved->value) {
            if (empty($data['source_urls']) || empty($data['review_due_at'])) {
                throw ValidationException::withMessages([
                    'data.source_urls' => '承認には一次資料URLが必要です。',
                    'data.review_due_at' => '承認には次回レビュー期限が必要です。',
                ]);
            }

            $data['reviewed_content_hash'] = $contentHash;
            $data['reviewed_at'] = $data['reviewed_at'] ?? now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
