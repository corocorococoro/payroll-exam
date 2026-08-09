<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\Unit;
use App\Services\CalcVerifier;
use Illuminate\Database\Seeder;
use LogicException;

class GeneratedContentSeeder extends Seeder
{
    private const int FISCAL_YEAR = 2026;

    /** @var array<string, int> */
    public const array TARGET_COUNTS = [
        'roudou' => 60,
        'shikyu' => 45,
        'zei' => 70,
        'shaho' => 85,
        'keisan' => 40,
    ];

    public function run(): void
    {
        $this->seedKnowledge('roudou', self::TARGET_COUNTS['roudou'], $this->laborBank());
        $this->seedKnowledge('shikyu', self::TARGET_COUNTS['shikyu'], $this->payrollBank());
        $this->seedKnowledge('zei', self::TARGET_COUNTS['zei'], $this->taxBank());
        $this->seedKnowledge('shaho', self::TARGET_COUNTS['shaho'], $this->insuranceBank());
        $this->seedCalculations();
        $this->seedMockExams();
    }

    /**
     * @param  list<array{question: string, correct: string, wrong: list<string>, explanation: string, mistake: string}>  $bank
     */
    private function seedKnowledge(string $unitSlug, int $count, array $bank): void
    {
        $unit = Unit::where('slug', $unitSlug)->firstOrFail();
        $lessons = $unit->lessons()->get()->values();

        for ($i = 1; $i <= $count; $i++) {
            $base = $bank[($i - 1) % count($bank)];
            $rotation = ($i - 1) % 4;
            $texts = $base['wrong'];
            array_splice($texts, $rotation, 0, [$base['correct']]);
            $choices = collect($texts)->values()->map(fn (string $text, int $index) => [
                'key' => chr(65 + $index),
                'text' => $text,
            ])->all();
            $lesson = $lessons[($i - 1) % $lessons->count()];

            Question::updateOrCreate(['source_id' => sprintf('gen-%s-%03d', $unitSlug, $i)], [
                'unit_id' => $unit->id,
                'lesson_id' => $lesson->id,
                'type' => QuestionType::Choice,
                'category' => $this->category($unitSlug),
                'difficulty' => Difficulty::cases()[($i - 1) % 3],
                'fiscal_year' => self::FISCAL_YEAR,
                'question_text' => $this->knowledgeStem(
                    $base['question'],
                    intdiv($i - 1, count($bank)),
                ),
                'choices' => $choices,
                'answer' => ['choice' => chr(65 + $rotation)],
                'explanation' => $base['explanation'],
                'common_mistake' => $base['mistake'],
                'calc_params' => null,
                'reference_sheet_slugs' => [],
                'is_active' => true,
            ]);
        }
    }

    private function seedCalculations(): void
    {
        $unit = Unit::where('slug', 'keisan')->firstOrFail();
        $lessons = $unit->lessons()->get()->values();

        for ($i = 1; $i <= self::TARGET_COUNTS['keisan']; $i++) {
            $lesson = $lessons[($i - 1) % $lessons->count()];

            if ($i <= 14) {
                $wage = 210000 + ($i * 7500);
                $rate = 5;
                $params = ['calc_type' => 'employment_insurance', 'wage' => $wage, 'rate_per_mille' => $rate];
                $answer = CalcVerifier::roundHalfDown($wage * $rate / 1000);
                $text = "賃金総額が{$wage}円、労働者負担の雇用保険料率が{$rate}/1000のとき、雇用保険料はいくらか。";
                $explanation = '賃金総額に労働者負担率を乗じ、50銭以下切捨て・50銭超切上げで処理します。';
                $sheets = ['koyo-hoken'];
            } elseif ($i <= 27) {
                $gross = 280000 + (($i - 14) * 5000);
                $deductions = [42000 + (($i - 14) * 300), 1500 + (($i - 14) * 25), 5200 + (($i - 14) * 100)];
                $params = ['calc_type' => 'net_pay', 'gross' => $gross, 'deductions' => $deductions];
                $answer = $gross - array_sum($deductions);
                $text = "総支給額{$gross}円、社会保険料{$deductions[0]}円、雇用保険料{$deductions[1]}円、所得税{$deductions[2]}円のとき、差引支給額はいくらか。";
                $explanation = '総支給額から社会保険料、雇用保険料、所得税を順に控除します。';
                $sheets = [];
            } else {
                $included = [240000 + (($i - 27) * 4000), 20000];
                $hours = 4 + ($i - 27);
                $params = [
                    'calc_type' => 'overtime_pay',
                    'included_wages' => $included,
                    'monthly_hours' => 160,
                    'components' => [['hours' => $hours, 'rate' => 1.25]],
                ];
                $unitPay = CalcVerifier::roundHalfUp(array_sum($included) / 160);
                $answer = CalcVerifier::roundHalfUp($unitPay * 1.25 * $hours);
                $text = '割増賃金算定基礎が'.array_sum($included)."円、月平均所定労働時間160時間、時間外労働{$hours}時間の割増賃金はいくらか。";
                $explanation = '1時間単価を四捨五入し、1.25倍した金額に時間外労働時間を乗じます。';
                $sheets = [];
            }

            Question::updateOrCreate(['source_id' => sprintf('gen-keisan-%03d', $i)], [
                'unit_id' => $unit->id,
                'lesson_id' => $lesson->id,
                'type' => QuestionType::Numeric,
                'category' => '実務計算',
                'difficulty' => Difficulty::cases()[($i - 1) % 3],
                'fiscal_year' => self::FISCAL_YEAR,
                'question_text' => $text,
                'choices' => null,
                'answer' => ['value' => $answer],
                'explanation' => $explanation,
                'common_mistake' => '料率の単位と端数処理のタイミングを取り違えないようにします。',
                'calc_params' => $params,
                'reference_sheet_slugs' => $sheets,
                'is_active' => true,
            ]);
        }
    }

    private function seedMockExams(): void
    {
        $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();

        foreach ([2, 3] as $set) {
            $knowledge = collect([
                ['slug' => 'roudou', 'count' => 10],
                ['slug' => 'shikyu', 'count' => 5],
                ['slug' => 'zei', 'count' => 8],
                ['slug' => 'shaho', 'count' => 12],
            ])->flatMap(function (array $part) use ($set) {
                return Question::where('source_id', 'like', "gen-{$part['slug']}-%")
                    ->orderBy('source_id')
                    ->skip(($set - 2) * $part['count'])
                    ->take($part['count'])
                    ->get();
            })->values();
            $calculations = Question::where('source_id', 'like', 'gen-keisan-%')
                ->orderBy('source_id')->skip(($set - 2) * 5)->take(5)->get();
            $questions = $knowledge->concat($calculations)->values();
            $exam = MockExam::updateOrCreate(['slug' => "mogi-{$set}"], [
                'course_id' => $course->id,
                'name' => "本番形式 模擬試験 第{$set}回",
                'description' => '生成問題から構成した40問・120分の追加模試。知識35問と計算5問。',
                'time_limit_minutes' => 120,
                'passing_score' => 70,
                'sort_order' => $set,
            ]);

            foreach ($questions as $index => $question) {
                $exam->examQuestions()->updateOrCreate(
                    ['position' => $index + 1],
                    ['question_id' => $question->id, 'points' => $index < 35 ? 2 : 6],
                );
            }
        }
    }

    private function category(string $slug): string
    {
        return match ($slug) {
            'roudou' => '労働法・勤怠',
            'shikyu' => '給与基礎・支給控除',
            'zei' => '税',
            'shaho' => '社会保険',
            default => throw new LogicException("未対応のUnitです: {$slug}"),
        };
    }

    private function knowledgeStem(string $question, int $occurrence): string
    {
        $templates = [
            fn (string $text): string => $text,
            fn (string $text): string => "給与担当者として制度を確認する。{$text}",
            fn (string $text): string => "新人担当者への説明として正しい内容を選びたい。{$text}",
            fn (string $text): string => "給与計算前のチェックで、正しい取扱いを一つ選べ。{$text}",
            fn (string $text): string => "社内から質問を受けた。法令・実務に照らして答えよ。{$text}",
            fn (string $text): string => "誤処理を防ぐため、最も適切な理解を一つ選べ。{$text}",
            fn (string $text): string => "月次給与のレビュー中である。確認事項として正しいものはどれか。{$text}",
            fn (string $text): string => "引継ぎ資料に記載する内容として正しいものを選べ。{$text}",
            fn (string $text): string => "従業員への案内を作成する。根拠に沿う説明はどれか。{$text}",
            fn (string $text): string => "監査で取扱いの根拠を問われた。正しい回答を一つ選べ。{$text}",
        ];

        return $templates[$occurrence % count($templates)]($question);
    }

    /** @return list<array{question: string, correct: string, wrong: list<string>, explanation: string, mistake: string}> */
    private function laborBank(): array
    {
        return [
            $this->fact('賃金支払5原則に含まれるものはどれか。', '通貨で、直接、全額を、毎月1回以上、一定期日に支払う', ['現物で自由に支払える', '毎月の支払日は変更自由である', '使用者は任意に相殺できる'], '労基法24条が賃金支払5原則を定めています。', '労使協定による控除例外と原則を混同しないこと。'),
            $this->fact('法定労働時間の原則として正しいものはどれか。', '1日8時間・1週40時間', ['1日7時間・1週35時間', '1日8時間・1週48時間', '1日10時間・1週50時間'], '労基法32条の原則は1日8時間、1週40時間です。', '所定労働時間と法定労働時間を区別します。'),
            $this->fact('法定休日労働の割増率として正しいものはどれか。', '35％以上', ['25％以上', '50％以上', '60％以上'], '法定休日労働は35％以上の割増です。', '所定休日の時間外労働25％と混同しないこと。'),
            $this->fact('深夜労働となる時間帯はどれか。', '22時から翌5時まで', ['20時から翌5時まで', '21時から翌6時まで', '23時から翌6時まで'], '深夜労働は22時から翌5時までです。', '会社独自の夜勤時間帯と法定深夜帯を混同しないこと。'),
            $this->fact('時間外労働が月60時間を超えた部分の割増率はどれか。', '50％以上', ['25％以上', '35％以上', '40％以上'], '月60時間超の時間外労働は50％以上です。', '60時間までの25％と区分します。'),
            $this->fact('年次有給休暇が原則10日付与される条件はどれか。', '6か月継続勤務し全労働日の8割以上出勤', ['3か月勤務し5割出勤', '1年勤務し全日出勤', '6か月勤務すれば出勤率を問わない'], '継続勤務6か月と8割以上出勤が基本要件です。', '勤続期間と出勤率の両方を確認します。'),
            $this->fact('36協定の役割として正しいものはどれか。', '法定時間外・休日労働を行わせるための協定', ['賃金を自由に減額する協定', '年休を廃止する協定', '社会保険を適用除外にする協定'], '法定時間外・休日労働には36協定の締結・届出が必要です。', '就業規則の記載だけでは足りません。'),
            $this->fact('常時10人以上を使用する事業場に必要なものはどれか。', '就業規則の作成と届出', ['労働組合の設立', '毎月の36協定更新', '全員との個別年俸契約'], '常時10人以上の事業場は就業規則を作成し届け出ます。', '企業全体でなく事業場単位で判定します。'),
        ];
    }

    /** @return list<array{question: string, correct: string, wrong: list<string>, explanation: string, mistake: string}> */
    private function payrollBank(): array
    {
        return [
            $this->fact('所得税の課税対象となる給与はどれか。', '基本給', ['一定限度内の通勤手当', '出張旅費の実費精算', '業務用立替金の返金'], '基本給は給与所得として課税対象です。', '実費弁償的な支給と給与を区別します。'),
            $this->fact('社会保険の報酬に含まれるものはどれか。', '通勤手当', ['慶弔見舞金', '出張旅費の実費', '解雇予告手当'], '通勤手当は税法上非課税でも社会保険上は報酬に含まれます。', '税と社会保険で取扱いが違います。'),
            $this->fact('給与明細の控除項目に通常該当するものはどれか。', '健康保険料', ['基本給', '役職手当', '時間外手当'], '健康保険料は法定控除項目です。', '支給項目と控除項目を区別します。'),
            $this->fact('マイカー通勤手当の非課税限度額を決める主な基準はどれか。', '片道の通勤距離', ['年齢', '基本給', '扶養人数'], '交通用具使用者は片道距離区分で限度額を判定します。', '公共交通機関の合理的運賃基準と混同しないこと。'),
            $this->fact('賃金台帳の記載事項として適切なものはどれか。', '労働日数・労働時間数・賃金額', ['家族の病歴', '私用メール履歴', '支持政党'], '労働日数、時間数、基本給や手当等を記載します。', '給与明細と賃金台帳は目的が異なります。'),
            $this->fact('欠勤控除の説明として適切なものはどれか。', '就業規則等に定めた計算方法で不就労分を控除する', ['使用者が自由に全額控除できる', '必ず暦日数で割る', '社会保険料も同時に免除される'], '欠勤控除は賃金規程等の定めに従い合理的に計算します。', 'ノーワーク・ノーペイでも計算根拠が必要です。'),
        ];
    }

    /** @return list<array{question: string, correct: string, wrong: list<string>, explanation: string, mistake: string}> */
    private function taxBank(): array
    {
        return [
            $this->fact('源泉徴収税額表の甲欄を使用する従業員はどれか。', '扶養控除等申告書を主たる勤務先に提出した人', ['申告書をどこにも提出していない人', '従たる勤務先だけの人', '必ず日雇いの人'], '主たる給与の支払者へ申告書を提出した場合は甲欄です。', '申告書の提出有無で甲欄・乙欄を判定します。'),
            $this->fact('住民税の特別徴収で通常、税額を控除する期間はどれか。', '6月から翌年5月', ['1月から12月', '4月から翌年3月', '7月から翌年6月'], '個人住民税の特別徴収は6月から翌年5月です。', '所得税の年末調整期間と混同しないこと。'),
            $this->fact('賞与の源泉所得税率を求める際に使うものはどれか。', '前月の社会保険料等控除後給与と扶養人数', ['当月の基本給だけ', '賞与支給日の年齢だけ', '前年の住民税額だけ'], '賞与算出率表は前月給与と扶養人数を使います。', '賞与額そのものだけで率を決めません。'),
            $this->fact('年末調整の対象となる所得税はどれか。', '給与から源泉徴収された所得税', ['住民税', '固定資産税', '自動車税'], '年末調整は給与所得に係る所得税の年税額を精算します。', '住民税は自治体が別途計算します。'),
            $this->fact('給与所得者の扶養控除等申告書を提出できる勤務先は原則いくつか。', '1か所', ['2か所', 'すべての勤務先', '提出できない'], '主たる給与の支払者1か所へ提出します。', '複数勤務先すべてで甲欄にはできません。'),
            $this->fact('非居住者への国内源泉所得となる給与の取扱いで重要なものはどれか。', '居住者と異なる源泉徴収ルールを確認する', ['必ず甲欄を使う', '常に非課税にする', '住民税だけ控除する'], '非居住者には原則として別の税率・手続が適用されます。', '居住者用月額表を機械的に使わないこと。'),
            $this->fact('退職者の住民税で1月から4月退職時の原則はどれか。', '未徴収税額を一括徴収する', ['必ず普通徴収にする', '徴収を免除する', '翌年まで保留する'], '1月から4月の退職は原則一括徴収です。', '6月から12月退職の本人申出の場合と区別します。'),
        ];
    }

    /** @return list<array{question: string, correct: string, wrong: list<string>, explanation: string, mistake: string}> */
    private function insuranceBank(): array
    {
        return [
            $this->fact('定時決定の対象となる報酬月はどれか。', '4月・5月・6月', ['1月・2月・3月', '6月・7月・8月', '9月・10月・11月'], '4〜6月の報酬を基に9月からの標準報酬月額を決定します。', '改定月の9月と算定対象月を混同しないこと。'),
            $this->fact('随時改定の要件の一つはどれか。', '固定的賃金の変動がある', ['賞与だけが増えた', '残業だけが一時的に増えた', '通勤日数だけが減った'], '固定的賃金の変動、3か月平均、2等級以上等を確認します。', '非固定的賃金だけの変動では原則対象外です。'),
            $this->fact('健康保険料の一般的な労使負担はどれか。', '事業主と被保険者が折半', ['全額被保険者', '全額国', '全額事業主'], '健康保険料は原則労使折半です。', '子ども・子育て拠出金など事業主のみ負担と区別します。'),
            $this->fact('厚生年金保険料率として正しいものはどれか。', '18.3％', ['9.15％', '9.85％', '20.0％'], '厚生年金保険料率は18.3％で固定されています。', '9.15％は被保険者負担相当の半分です。'),
            $this->fact('40歳以上65歳未満の健康保険被保険者に関係するものはどれか。', '介護保険料', ['労災保険料だけ', '児童手当だけ', '国民年金だけ'], '40歳以上65歳未満は介護保険第2号被保険者です。', '年齢到達月の取扱いを確認します。'),
            $this->fact('標準賞与額の計算方法はどれか。', '賞与額の1,000円未満を切り捨てる', ['100円未満を四捨五入する', '10,000円未満を切り上げる', '賞与額をそのまま使う'], '標準賞与額は1,000円未満切捨てです。', '標準報酬月額の等級表と混同しないこと。'),
            $this->fact('月末退職者の社会保険料について正しいものはどれか。', '退職月分まで保険料が発生する', ['退職月分は必ず不要', '前月分も免除', '日割り計算する'], '資格喪失日は退職日の翌日で、月末退職は翌月喪失となります。', '社会保険料は日割りしません。'),
            $this->fact('雇用保険料の算定基礎に含まれるものはどれか。', '通勤手当', ['役員報酬のみの非常勤役員', '実費弁償の出張旅費', '退職金'], '通勤手当は雇用保険の賃金に含まれます。', '所得税の非課税取扱いと混同しないこと。'),
            $this->fact('労災保険料を負担する者は誰か。', '事業主', ['労働者が全額', '労使折半', '市区町村'], '労災保険料は全額事業主負担です。', '雇用保険には労働者負担がある点と区別します。'),
            $this->fact('資格取得時決定で用いるものはどれか。', '資格取得時の報酬月額見込み', ['前年の年収だけ', '最初の賞与だけ', '住民税額'], '入社時の報酬見込みを標準報酬月額へ当てはめます。', '実績が3か月揃うまで待つ制度ではありません。'),
        ];
    }

    /**
     * @param  list<string>  $wrong
     * @return array{question: string, correct: string, wrong: list<string>, explanation: string, mistake: string}
     */
    private function fact(string $question, string $correct, array $wrong, string $explanation, string $mistake): array
    {
        return compact('question', 'correct', 'wrong', 'explanation', 'mistake');
    }
}
