<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password(),
                TextInput::make('google_id'),
                TextInput::make('avatar'),
                Toggle::make('is_admin')
                    ->required(),
                TextInput::make('daily_goal')
                    ->required()
                    ->numeric()
                    ->default(20),
                TimePicker::make('reminder_time'),
                Toggle::make('reminder_enabled')
                    ->required(),
                DatePicker::make('exam_date'),
                Toggle::make('sound_enabled')
                    ->required(),
                Toggle::make('onboarded')
                    ->required(),
                DatePicker::make('last_reminded_on'),
            ]);
    }
}
