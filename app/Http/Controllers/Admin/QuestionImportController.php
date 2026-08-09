<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class QuestionImportController extends Controller
{
    public function __invoke(Request $request, QuestionImportService $importer): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $validated = $request->validate([
            'questions_file' => ['required', 'file', 'mimes:json,csv,txt', 'max:10240'],
        ]);

        try {
            $file = $validated['questions_file'];
            $extension = strtolower($file->getClientOriginalExtension());
            $count = $importer->import($file->getRealPath(), $extension);

            return back()->with('import_success', "{$count}問をインポートしました。");
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['questions_file' => $exception->getMessage()]);
        }
    }
}
