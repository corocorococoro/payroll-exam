<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Enums\QuestionReviewStatus;
use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use App\Services\QuestionReviewLedger;
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
        $contentChanged = $record->content_hash !== $contentHash
            || Question::reviewDependenciesHash($record->toArray()) !== Question::reviewDependenciesHash(array_replace($record->toArray(), $data));
        $data['content_hash'] = $contentHash;

        if ($contentChanged) {
            $data['content_revision'] = $record->content_revision + 1;
            $data['review_status'] = QuestionReviewStatus::InReview->value;
            $data['reviewed_content_hash'] = $record->reviewed_content_hash;
            $data['reviewed_at'] = null;
            $data['review_due_at'] = null;
            $data['review_fingerprint'] = null;
            $data['is_active'] = false;

            return $data;
        }

        if (($data['review_status'] ?? null) === QuestionReviewStatus::Approved->value) {
            $ledger = app(QuestionReviewLedger::class);
            $review = $ledger->records()[$record->source_id] ?? [];
            $errors = array_filter($ledger->errors(), fn (string $error): bool => str_starts_with($error, $record->source_id.':'));
            if ($record->review_status !== QuestionReviewStatus::Approved || $record->review_fingerprint === null
                || $record->review_fingerprint !== ($review['fingerprint'] ?? null) || $errors !== []) {
                throw ValidationException::withMessages([
                    'data.review_status' => '公開承認には正本と監査台帳の照合・同期が必要です。管理画面だけでは承認できません。',
                ]);
            }

            $data['reviewed_content_hash'] = $contentHash;
            $data['reviewed_at'] = $review['reviewed_at'];
            $data['review_due_at'] = $review['review_due_at'].' 23:59:59';
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
