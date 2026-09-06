<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Editorial approval is separate from seeding. A hash is evidence of identity,
 * not evidence that the answer is correct; completed checks need cited findings.
 */
class QuestionReviewLedger
{
    public const array CHECKS = ['answer', 'all_choices', 'explanation', 'legal_date', 'pedagogy', 'references', 'calculation'];

    /** @param array<string, array<string, mixed>>|null $records */
    public function __construct(private readonly ?array $records = null) {}

    /** @return array<string, array<string, mixed>> */
    public function records(): array
    {
        return $this->records ?? (File::json(database_path('seeders/data/question-reviews.json'))['questions'] ?? []);
    }

    /** @return array<string, array<string, mixed>> */
    public function subjects(): array
    {
        $bank = File::json(database_path('seeders/data/question-bank.json'));
        $sources = File::json(database_path('seeders/data/official-sources.json'));
        $sheets = [];
        foreach (File::json(database_path('seeders/data/reference-sheets-2026.json')) as $sheet) {
            $sheets[$sheet['slug']] = $sheet;
        }
        $course = File::json(database_path('seeders/data/course-2kyu.json'));
        $lessons = [];
        foreach ($course['units'] as $unit) {
            foreach ($unit['lessons'] as $lesson) {
                $lessons[$unit['slug'].'/'.$lesson['slug']] = $lesson;
            }
        }
        $subjects = [];
        foreach ($bank['questions'] as $question) {
            if (isset($subjects[$question['id']])) {
                throw new RuntimeException("問題ID {$question['id']} が重複しています。");
            }
            $questionSources = [];
            foreach ($question['source_keys'] as $key) {
                $questionSources[$key] = $sources[$key] ?? null;
            }
            $questionSheets = [];
            foreach ($question['reference_sheet_slugs'] ?? [] as $slug) {
                $questionSheets[$slug] = $sheets[$slug] ?? null;
            }
            $subjects[$question['id']] = [
                'question' => $question,
                'legal_as_of' => $bank['release']['legal_as_of'],
                'learning_objective' => $bank['topics'][$question['topic_key']] ?? null,
                'sources' => $questionSources,
                'reference_sheets' => $questionSheets,
                'lesson' => $lessons[$question['unit'].'/'.($question['lesson'] ?? '')] ?? null,
            ];
        }

        return $subjects;
    }

    public static function fingerprint(mixed $value): string
    {
        return hash('sha256', json_encode(self::canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    /** @return list<string> */
    public function errors(): array
    {
        $records = $this->records();
        $subjects = $this->subjects();
        $errors = [];
        // Public source labels are resolved by URL. An alias could otherwise
        // override a reviewed label without changing that question's source key.
        $sourceUrls = [];
        foreach (File::json(database_path('seeders/data/official-sources.json')) as $key => $source) {
            if (isset($sourceUrls[$source['url']])) {
                $errors[] = "根拠資料 {$key}: URLが{$sourceUrls[$source['url']]}と重複しています。資料キーを統合してください。";
            }
            $sourceUrls[$source['url']] = $key;
        }
        if ($this->records === null) {
            // Individual question checks do not approve the whole course. Keep
            // seeding closed while cross-content and learning-flow review runs.
            $release = File::json(database_path('seeders/data/question-bank.json'))['release'] ?? [];
            if (($release['editorial_status'] ?? null) !== 'approved') {
                $errors[] = '教材全体の横断監査・公開承認が未完了です。個別問題の確認完了だけでは同期できません。';
            }
            $baselineIds = File::json(database_path('seeders/data/question-reviews.json'))['baseline_ids'] ?? [];
            if ($baselineIds === []) {
                $errors[] = '監査開始時の問題ID一覧がありません。';
            }
            foreach (array_diff($baselineIds, array_keys($records)) as $id) {
                $errors[] = "{$id}: 監査開始時の問題が台帳から失われています。";
            }
        }
        foreach ($subjects as $id => $subject) {
            if ($subject['learning_objective'] === null || $subject['lesson'] === null
                || $subject['sources'] === [] || in_array(null, $subject['sources'], true)
                || in_array(null, $subject['reference_sheets'], true)) {
                $errors[] = "{$id}: 学習目標・レッスン・根拠資料・参照表の依存先が不足しています。";
            }
            $record = $records[$id] ?? [];
            if (($record['verification'] ?? null) !== 'complete' || ! in_array($record['decision'] ?? null, ['maintain', 'revise'], true)) {
                $errors[] = "{$id}: 全問監査が未完了です（根拠不足・修正待ちを承認できません）。";

                continue;
            }
            if (($record['fingerprint'] ?? null) !== self::fingerprint($subject)) {
                $errors[] = "{$id}: 問題または依存資料が監査時から変更されています。";
            }
            foreach (self::CHECKS as $check) {
                if (! is_string($record['checks'][$check] ?? null) || trim($record['checks'][$check]) === '') {
                    $errors[] = "{$id}: {$check}の確認結果がありません。";
                }
            }
            if (! is_string($record['notes'] ?? null) || trim($record['notes']) === '') {
                $errors[] = "{$id}: 判定理由がありません。";
            }
            $evidence = $record['evidence'] ?? [];
            if (! is_array($evidence) || $evidence === []) {
                $errors[] = "{$id}: 根拠の該当箇所がありません。";
            } else {
                foreach ($evidence as $citation) {
                    $source = $subject['sources'][$citation['source_key'] ?? ''] ?? null;
                    if ($source === null || ($citation['url'] ?? null) !== $source['url'] || trim($citation['locator'] ?? '') === '') {
                        $errors[] = "{$id}: 根拠のURL・資料キー・該当箇所が対応していません。";
                    }
                }
            }
            try {
                $reviewed = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($record['reviewed_at'] ?? ''));
                $due = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($record['review_due_at'] ?? ''));
                if ($reviewed === null || $due === null
                    || $reviewed->toDateString() !== $record['reviewed_at']
                    || $due->toDateString() !== $record['review_due_at']
                    || $reviewed->isFuture() || $due->endOfDay()->isPast() || $due->lessThan($reviewed)) {
                    throw new RuntimeException('Invalid review dates');
                }
            } catch (\Throwable) {
                $errors[] = "{$id}: 監査日・監査期限が不正または期限切れです。";
            }
        }
        foreach (array_diff(array_keys($records), array_keys($subjects)) as $id) {
            $record = $records[$id];
            if (($record['decision'] ?? null) !== 'retire' || ($record['verification'] ?? null) !== 'retired'
                || trim($record['notes'] ?? '') === '' || trim($record['retired_at'] ?? '') === '') {
                $errors[] = "{$id}: 正本にない問題の退役記録がありません。";
            }
            $snapshot = $record['retired_question'] ?? null;
            if (! is_array($snapshot) || ($snapshot['id'] ?? null) !== $id
                || ($record['retirement_content_hash'] ?? null) !== self::fingerprint($snapshot)) {
                $errors[] = "{$id}: 退役時の問題内容と保存ハッシュが一致しません。";
            }
            $evidence = $record['evidence'] ?? [];
            if (! is_array($evidence) || $evidence === []) {
                $errors[] = "{$id}: 退役判断の参照根拠がありません。";
            } else {
                foreach ($evidence as $citation) {
                    if (! isset($sourceUrls[$citation['url'] ?? ''])
                        || $sourceUrls[$citation['url'] ?? ''] !== ($citation['source_key'] ?? null)
                        || trim($citation['locator'] ?? '') === '') {
                        $errors[] = "{$id}: 退役判断の資料キー・URL・該当箇所が対応していません。";
                    }
                }
            }
            if (isset($record['merged_into']) && ! isset($subjects[$record['merged_into']])) {
                $errors[] = "{$id}: 統合先が現行の正本にありません。";
            }
        }

        return array_values(array_unique($errors));
    }

    /** @return array<string, array<string, mixed>> */
    public function approvedRecords(): array
    {
        $errors = $this->errors();
        if ($errors !== []) {
            throw new RuntimeException('問題監査台帳に未解決項目があるため、DBを変更せず同期を停止します。'.count($errors).'件: '.implode(' / ', array_slice($errors, 0, 5)));
        }

        return $this->records();
    }
}
