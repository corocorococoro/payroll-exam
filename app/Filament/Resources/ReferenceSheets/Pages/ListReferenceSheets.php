<?php

namespace App\Filament\Resources\ReferenceSheets\Pages;

use App\Filament\Resources\ReferenceSheets\ReferenceSheetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferenceSheets extends ListRecords
{
    protected static string $resource = ReferenceSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
