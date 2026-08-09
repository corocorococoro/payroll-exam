<x-filament-panels::page>
    <form method="POST" action="{{ route('admin.questions.import') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if (session('import_success'))
            <div class="rounded-xl bg-success-50 p-4 text-success-700">{{ session('import_success') }}</div>
        @endif
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-bold">JSON / CSVファイル</h2>
            <p class="mt-1 text-sm text-gray-500">source_idをキーに新規登録または更新します。最大10MB。</p>
            <input type="file" name="questions_file" accept=".json,.csv" required class="mt-4 block w-full rounded-lg border p-3">
            @error('questions_file')<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
        </div>
        <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">インポートする</x-filament::button>
    </form>
</x-filament-panels::page>
