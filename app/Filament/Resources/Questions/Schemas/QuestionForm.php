<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
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
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
