<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\ReferenceSheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    private const int SESSION_COUNT = 20;

    public function __invoke(Request $request): Response
    {
        $query = $request->user()->reviewItems()
            ->whereDate('due_date', '<=', today())
            ->whereHas('question', function (Builder $query): void {
                /** @var Builder<Question> $query */
                $query->published();
            })
            ->with('question.unit')
            ->orderBy('due_date')
            ->orderByDesc('lapses');

        $dueTotal = (clone $query)->count();
        $items = $query
            ->limit(self::SESSION_COUNT)
            ->get();

        $questions = $items->map(fn ($item) => [
            'id' => $item->question->id,
            'type' => $item->question->type,
            'question_text' => $item->question->question_text,
            'choices' => $item->question->choices,
            'is_calculation' => $item->question->isCalculation(),
            'reference_sheet_slugs' => $item->question->reference_sheet_slugs ?? [],
            'unit_name' => $item->question->unit->name,
            'box' => $item->box,
        ])->values();

        $sheetSlugs = $items->pluck('question.reference_sheet_slugs')->flatten()->filter()->unique();
        $sheets = ReferenceSheet::whereIn('slug', $sheetSlugs)
            ->where('fiscal_year', 2026)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'content']);

        return Inertia::render('review/Index', [
            'questions' => $questions,
            'due_total' => $dueTotal,
            'reference_sheets' => $sheets,
        ]);
    }
}
