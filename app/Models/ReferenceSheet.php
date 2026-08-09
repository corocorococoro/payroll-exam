<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $fiscal_year
 * @property array<string, mixed> $content
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'fiscal_year', 'content', 'sort_order'])]
class ReferenceSheet extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
