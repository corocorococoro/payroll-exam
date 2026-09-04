<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateMockExams([
            'mogi-1' => [
                'name' => '2級 模擬試験 第1回',
                'description' => '本番と同じ40問・120分・合格基準70点の模擬試験です。今の実力と復習すべき分野を確認できます。',
            ],
            'mogi-2' => [
                'name' => '2級 模擬試験 第2回',
                'description' => '本番と同じ40問・120分・合格基準70点の模擬試験です。複数の分野を通して今の実力を確認できます。',
            ],
            'mogi-3' => [
                'name' => '2級 模擬試験 第3回',
                'description' => '本番と同じ40問・120分・合格基準70点の模擬試験です。本番前の仕上がりを確認できます。',
            ],
        ]);
    }

    public function down(): void
    {
        $this->updateMockExams([
            'mogi-1' => [
                'name' => '2級 診断模試 第1回',
                'description' => '40問・120分・知識35問＋計算5問・合格基準70点の診断模試で、現在の実力と弱点を診断します。',
            ],
            'mogi-2' => [
                'name' => '2級 実戦模試 第2回',
                'description' => '40問・120分・知識35問＋計算5問・合格基準70点の実戦模試で、分野を横断して実戦力を確認します。',
            ],
            'mogi-3' => [
                'name' => '2級 実戦模試 第3回',
                'description' => '40問・120分・知識35問＋計算5問・合格基準70点の総仕上げ模試で、本番前の実力を確認します。',
            ],
        ]);
    }

    /**
     * @param  array<string, array{name: string, description: string}>  $mockExams
     */
    private function updateMockExams(array $mockExams): void
    {
        foreach ($mockExams as $slug => $copy) {
            DB::table('mock_exams')->where('slug', $slug)->update($copy);
        }
    }
};
