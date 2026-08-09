<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class QuestionImport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'コンテンツ管理';

    protected static ?string $navigationLabel = '問題インポート';

    protected static ?string $title = '問題インポート';

    protected string $view = 'filament.pages.question-import';
}
