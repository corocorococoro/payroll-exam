<?php

namespace App\Filament\Resources\Questions\Tables;

use App\Enums\QuestionReviewStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit.name')
                    ->searchable(),
                TextColumn::make('lesson.name')
                    ->searchable(),
                TextColumn::make('source_id')
                    ->searchable(),
                TextColumn::make('concept_key')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('variant_role')
                    ->label('役割')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '未設定'),
                TextColumn::make('study_tier')
                    ->label('学習優先度')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'core' ? '合格コア' : '上積み演習'),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('difficulty')
                    ->badge()
                    ->searchable(),
                TextColumn::make('review_status')
                    ->badge()
                    ->formatStateUsing(fn (QuestionReviewStatus $state): string => $state->label()),
                TextColumn::make('content_revision')
                    ->label('版')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('review_due_at')
                    ->label('次回レビュー')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('fiscal_year')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('review_status')
                    ->options(collect(QuestionReviewStatus::cases())->mapWithKeys(
                        fn (QuestionReviewStatus $status): array => [$status->value => $status->label()],
                    )->all()),
                SelectFilter::make('study_tier')
                    ->label('学習優先度')
                    ->options([
                        'core' => '合格コア',
                        'reinforcement' => '上積み演習',
                    ]),
                TernaryFilter::make('is_active'),
                Filter::make('review_due')
                    ->label('レビュー期限切れ')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('review_due_at')
                        ->where('review_due_at', '<=', now())),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
