<?php

namespace App\Http\Controllers;

use App\Models\ReferenceSheet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $items = $request->user()->reviewItems()
            ->whereDate('due_date', '<=', today())
            ->with('question.unit')
            ->orderBy('due_date')
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
            'reference_sheets' => $sheets,
        ]);
    }
}
