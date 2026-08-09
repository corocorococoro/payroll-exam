<?php

namespace App\Filament\Resources\ReferenceSheets\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReferenceSheetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('fiscal_year')
                    ->required()
                    ->numeric(),
                TextInput::make('content')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
