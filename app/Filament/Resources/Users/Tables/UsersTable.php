<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('google_id')
                    ->searchable(),
                TextColumn::make('avatar')
                    ->searchable(),
                IconColumn::make('is_admin')
                    ->boolean(),
                TextColumn::make('daily_goal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reminder_time')
                    ->time()
                    ->sortable(),
                IconColumn::make('reminder_enabled')
                    ->boolean(),
                TextColumn::make('exam_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('sound_enabled')
                    ->boolean(),
                IconColumn::make('onboarded')
                    ->boolean(),
                TextColumn::make('last_reminded_on')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
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
