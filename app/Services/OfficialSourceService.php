<?php

namespace App\Services;

use App\Models\Question;

class OfficialSourceService
{
    private const array OFFICIAL_HOSTS = [
        'elaws.e-gov.go.jp',
        'fos.or.jp',
        'hatarakikatakaikaku.mhlw.go.jp',
        'jsite.mhlw.go.jp',
        'www.kyoukaikenpo.or.jp',
        'www.mhlw.go.jp',
        'www.nenkin.go.jp',
        'www.nta.go.jp',
        'www.ppc.go.jp',
        'www.tax.metro.tokyo.lg.jp',
    ];

    /** @return list<array{label: string, url: string}> */
    public function forQuestion(Question $question): array
    {
        $sources = [];

        foreach (array_values(array_unique($question->source_urls ?? [])) as $url) {
            if (! $this->isOfficialUrl($url)) {
                continue;
            }

            $sources[] = [
                'label' => $this->label($url),
                'url' => $url,
            ];
        }

        return $sources;
    }

    public function isOfficialUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), self::OFFICIAL_HOSTS, true);
    }

    private function label(string $url): string
    {
        return match (true) {
            str_contains($url, 'fos.or.jp') => '検定公式：2級の範囲・基準日',
            str_contains($url, '322AC0000000049') => 'e-Gov法令検索：労働基準法',
            str_contains($url, '419AC0000000128') => 'e-Gov法令検索：労働契約法',
            str_contains($url, '/roudouzikan/') => '厚生労働省：労働時間・休憩・休日・年休',
            str_contains($url, 'hatarakikatakaikaku.mhlw.go.jp/overtime') => '厚生労働省：時間外労働の上限と割増賃金',
            str_contains($url, '/0310.html') => '鹿児島労働局：割増賃金の端数処理',
            str_contains($url, '/shienjigyou/03_00028') => '厚生労働省：賃金のデジタル払い',
            str_contains($url, '/roudoukijun/chingin/') => '厚生労働省：最低賃金制度',
            str_contains($url, '/hoken/roudouhoken21/') => '厚生労働省：労働保険の年度更新',
            str_contains($url, '/roudoukijun/rousai/') => '厚生労働省：労災保険制度',
            str_contains($url, '2026tsukin') => '国税庁：2026年の通勤手当',
            str_contains($url, 'zeigakuhyo2026') => '国税庁：2026年分の源泉徴収税額表',
            str_contains($url, 'tax.metro.tokyo.lg.jp') => '東京都主税局：住民税の特別徴収',
            str_contains($url, 'kounen2026_01') => '日本年金機構：厚生年金・社会保険の概要',
            str_contains($url, '/nintei/gaiyo4.html') => '厚生労働省：介護保険の被保険者',
            str_contains($url, 'tekiyoukakudai') => '厚生労働省：短時間労働者の社会保険',
            str_contains($url, '/hyoujunhoushu/20120903') => '日本年金機構：社会保険上の報酬の範囲',
            str_contains($url, '/hokenryo/hoshu/20121017') => '日本年金機構：標準報酬月額・定時決定',
            str_contains($url, '/hokenryo/hoshu/20120330') => '日本年金機構：資格取得時の標準報酬月額',
            str_contains($url, '/hyoujunhoushu/20140602-02') => '日本年金機構：随時改定',
            str_contains($url, '/insurance_rate/rate_prefectures/r08/') => '協会けんぽ：2026年度保険料率',
            str_contains($url, '/shibu/tokyo/assets/R0802.pdf') => '協会けんぽ東京支部：2026年度保険料率',
            str_contains($url, 'R08kikinryougaku.pdf') => '日本年金機構：2026年度子ども・子育て拠出金率',
            str_contains($url, '001692566.pdf') => '厚生労働省：2026年度雇用保険料率',
            str_contains($url, '/faq/benefit/001/') => '協会けんぽ：健康保険給付',
            str_contains($url, '/hihokensha1/20150422') => '日本年金機構：資格取得の手続',
            str_contains($url, '/hihokensha1/20150407-02') => '日本年金機構：資格喪失の手続',
            str_contains($url, 'voluntary_continuation'), str_contains($url, 'sbb3180') => '協会けんぽ：退職後の健康保険',
            str_contains($url, '/benefit/childbirth/') => '協会けんぽ：出産に関する給付',
            str_contains($url, '/hihokensha1/20141202') => '日本年金機構：被扶養者の認定・手続',
            str_contains($url, '/hokenryo/menjo/'), str_contains($url, '/2022/202210/100302') => '日本年金機構：産休・育休中の保険料',
            str_contains($url, 'shoyokeisan'), str_contains($url, '20141203') => '日本年金機構：賞与の保険料',
            str_contains($url, '/0000134526') => '厚生労働省：雇用保険給付',
            str_contains($url, 'tetsuduki_ichiran01') => '厚生労働省：雇用保険の届出期限',
            str_contains($url, '/newpage_32105.html') => '厚生労働省：2024年改正の労働条件明示',
            str_contains($url, '/0000061842') => '厚生労働省：パート・有期雇用労働法',
            str_contains($url, '/0000130583') => '厚生労働省：育児・介護休業法',
            str_contains($url, 'ppc.go.jp') => '個人情報保護委員会：マイナンバー取扱指針',
            str_contains($url, 'kyoukaikenpo.or.jp') => '協会けんぽ：健康保険の公式資料',
            str_contains($url, 'nenkin.go.jp') => '日本年金機構：制度・手続の解説',
            str_contains($url, 'nta.go.jp') => '国税庁：税務の公式資料',
            str_contains($url, 'mhlw.go.jp') => '厚生労働省：制度の公式資料',
            default => '公式資料',
        };
    }
}
