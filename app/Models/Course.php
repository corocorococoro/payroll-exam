<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'description', 'sort_order'])]
class Course extends Model
{
    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('sort_order');
    }

    /** @return HasMany<MockExam, $this> */
    public function mockExams(): HasMany
    {
        return $this->hasMany(MockExam::class)->orderBy('sort_order');
    }
}
