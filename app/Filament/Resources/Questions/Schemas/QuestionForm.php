<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Enums\Difficulty;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('unit_id')
                    ->relationship('unit', 'name')
                    ->required(),
                Select::make('lesson_id')
                    ->relationship('lesson', 'name'),
                TextInput::make('source_id'),
                TextInput::make('concept_key')
                    ->helperText('同じ知識・計算パターンの派生問題は同じキーにします。')
                    ->required(),
                Select::make('type')
                    ->options([
                        QuestionType::Choice->value => '四肢択一',
                        QuestionType::Numeric->value => '数値入力',
                    ])
                    ->required(),
                TextInput::make('category')
                    ->required(),
                Select::make('difficulty')
                    ->options([
                        Difficulty::Easy->value => '易しい',
                        Difficulty::Medium->value => '標準',
                        Difficulty::Hard->value => '難しい',
                    ])
                    ->required(),
                Select::make('review_status')
                    ->options(collect(QuestionReviewStatus::cases())->mapWithKeys(
                        fn (QuestionReviewStatus $status): array => [$status->value => $status->label()],
                    )->all())
                    ->required(),
                TextInput::make('content_revision')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('fiscal_year')
                    ->required()
                    ->numeric(),
                Textarea::make('question_text')
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('choices')
                    ->schema([
                        TextInput::make('key')->required()->maxLength(4),
                        TextInput::make('text')->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                KeyValue::make('answer')->required(),
                Textarea::make('explanation')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('common_mistake')
                    ->columnSpanFull(),
                KeyValue::make('calc_params')->columnSpanFull(),
                TagsInput::make('reference_sheet_slugs')->columnSpanFull(),
                TagsInput::make('source_urls')
                    ->label('一次資料URL')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('review_notes')
                    ->label('レビュー記録')
                    ->columnSpanFull(),
                DateTimePicker::make('reviewed_at')
                    ->label('最終レビュー日時'),
                DateTimePicker::make('review_due_at')
                    ->label('次回レビュー期限'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
