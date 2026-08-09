<?php

namespace App\Services;

use App\Models\Question;
use App\Models\ReferenceSheet;
use RuntimeException;

/**
 * 計算問題の正答をシードデータの calc_params から再計算して検証する。
 * コンテンツ品質ゲート（tests/Feature/Content/CalcVerificationTest.php）から使用。
 *
 * calc_params の共通形: {"calc_type": "...", ...型ごとのパラメータ}
 * 検証対象の正答値は answer.value（choice 型の計算問題も value を併記する）。
 */
class CalcVerifier
{
    /**
     * 再計算した金額（円）を返す。
     */
    public function compute(Question $question): int
    {
        $p = $question->calc_params ?? throw new RuntimeException("Question {$question->id} has no calc_params");

        return match ($p['calc_type']) {
            'overtime_pay' => $this->overtimePay($p),
            'social_insurance_employee' => $this->socialInsuranceEmployee($p),
            'employment_insurance' => $this->employmentInsurance($p),
            'withholding_tax_monthly' => $this->withholdingTaxMonthly($p, $question),
            'bonus_withholding' => $this->bonusWithholding($p),
            'net_pay' => $this->netPay($p),
            'income_check' => $this->incomeCheck($p),
            default => throw new RuntimeException("Unknown calc_type: {$p['calc_type']}"),
        };
    }

    /**
     * 割増賃金: 1時間単価は 50銭未満切捨て・50銭以上切上げ（四捨五入）。
     *
     * @param  array<string, mixed>  $p
     */
    private function overtimePay(array $p): int
    {
        $base = array_sum($p['included_wages']);
        $unit = self::roundHalfUp($base / $p['monthly_hours']);

        $total = 0;
        foreach ($p['components'] as $c) {
            $total += self::roundHalfUp($unit * $c['rate'] * $c['hours']);
        }

        return $total;
    }

    /**
     * 社会保険料の被保険者負担: 各項目ごとに 50銭以下切捨て・50銭超切上げ。
     *
     * @param  array<string, mixed>  $p
     */
    private function socialInsuranceEmployee(array $p): int
    {
        $standard = $p['standard_monthly'];
        $share = $p['employee_share'] ?? 0.5;

        $total = 0;
        foreach ($p['rates_percent'] as $rate) {
            $total += self::roundHalfDown($standard * $rate / 100 * $share);
        }

        return $total;
    }

    /** @param  array<string, mixed>  $p */
    private function employmentInsurance(array $p): int
    {
        return self::roundHalfDown($p['wage'] * $p['rate_per_mille'] / 1000);
    }

    /**
     * 月額表による源泉所得税: 社会保険料等控除後の給与を資料集の税額表から引く。
     *
     * @param  array<string, mixed>  $p
     */
    private function withholdingTaxMonthly(array $p, Question $question): int
    {
        $taxable = $p['gross'] - $p['social_insurance'] - ($p['employment_insurance'] ?? 0);

        $sheet = $this->sheet($question, $p['table']);

        foreach ($sheet['rows'] as $row) {
            if ($taxable >= $row['min'] && $taxable < $row['max']) {
                return $row['by_dependents'][(string) $p['dependents']]
                    ?? throw new RuntimeException("No tax for dependents={$p['dependents']}");
            }
        }

        throw new RuntimeException("Taxable {$taxable} not in table {$p['table']}");
    }

    /**
     * 賞与の源泉所得税: 標準賞与ベースの社会保険料等を控除後、算出率を乗じ1円未満切捨て。
     *
     * @param  array<string, mixed>  $p
     */
    private function bonusWithholding(array $p): int
    {
        $bonus = $p['bonus'];
        $standardBonus = intdiv($bonus, 1000) * 1000;

        $si = 0;
        foreach ($p['rates_percent'] as $rate) {
            $si += self::roundHalfDown($standardBonus * $rate / 100 * ($p['employee_share'] ?? 0.5));
        }
        $si += self::roundHalfDown($bonus * ($p['employment_rate_per_mille'] ?? 0) / 1000);

        $after = $bonus - $si;

        return (int) floor($after * $p['rate_percent'] / 100);
    }

    /** @param  array<string, mixed>  $p */
    private function netPay(array $p): int
    {
        return $p['gross'] - array_sum($p['deductions']);
    }

    /** @param  array<string, mixed>  $p */
    private function incomeCheck(array $p): int
    {
        return $p['income'] - $p['deduction'];
    }

    /** @return array<string, mixed> */
    private function sheet(Question $question, string $slug): array
    {
        $sheet = ReferenceSheet::where('slug', $slug)
            ->where('fiscal_year', $question->fiscal_year)
            ->first() ?? throw new RuntimeException("ReferenceSheet {$slug}/{$question->fiscal_year} not found");

        return $sheet->content;
    }

    /** 50銭未満切捨て・50銭以上切上げ */
    public static function roundHalfUp(float $value): int
    {
        return (int) floor($value + 0.5);
    }

    /** 50銭以下切捨て・50銭超切上げ */
    public static function roundHalfDown(float $value): int
    {
        return (int) ceil($value - 0.5);
    }
}
