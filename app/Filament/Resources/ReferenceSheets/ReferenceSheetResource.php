<?php

namespace App\Filament\Resources\ReferenceSheets;

use App\Filament\Resources\ReferenceSheets\Pages\CreateReferenceSheet;
use App\Filament\Resources\ReferenceSheets\Pages\EditReferenceSheet;
use App\Filament\Resources\ReferenceSheets\Pages\ListReferenceSheets;
use App\Filament\Resources\ReferenceSheets\Schemas\ReferenceSheetForm;
use App\Filament\Resources\ReferenceSheets\Tables\ReferenceSheetsTable;
use App\Models\ReferenceSheet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReferenceSheetResource extends Resource
{
    protected static ?string $model = ReferenceSheet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ReferenceSheetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferenceSheetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferenceSheets::route('/'),
            'create' => CreateReferenceSheet::route('/create'),
            'edit' => EditReferenceSheet::route('/{record}/edit'),
        ];
    }
}
