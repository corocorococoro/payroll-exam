<?php

namespace App\Filament\Resources\ReferenceSheets\Pages;

use App\Filament\Resources\ReferenceSheets\ReferenceSheetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferenceSheet extends EditRecord
{
    protected static string $resource = ReferenceSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
