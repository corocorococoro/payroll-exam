#!/usr/bin/env python3
"""Convert the supplied 2026 level-2 workbook into question-bank JSON.

The runtime application never parses the PDF. This script is a reproducible,
development-only conversion step. Run it with a Python environment containing
``pypdf``.
"""

from __future__ import annotations

import argparse
import json
import re
from dataclasses import dataclass
from pathlib import Path

from pypdf import PdfReader


CHOICE_KEYS = {"ア": "A", "イ": "B", "ウ": "C", "エ": "D"}


@dataclass(frozen=True)
class Chapter:
    number: int
    start: int
    end: int
    title: str


CHAPTERS = [
    Chapter(1, 1, 50, "給与計算の基礎と賃金支払5原則"),
    Chapter(2, 51, 100, "賃金の範囲・平均賃金・端数処理"),
    Chapter(3, 101, 150, "労働時間・休憩・休日"),
    Chapter(4, 151, 200, "年次有給休暇・変形労働時間制"),
    Chapter(5, 201, 250, "割増賃金の計算演習①（基本）"),
    Chapter(6, 251, 300, "割増賃金・勤怠控除の計算演習②（応用）"),
    Chapter(7, 301, 350, "社会保険制度の基本（健保・介護・厚年）"),
    Chapter(8, 351, 400, "標準報酬月額と保険料のしくみ"),
    Chapter(9, 401, 450, "社会保険の事務手続①（資格取得・喪失）"),
    Chapter(10, 451, 500, "社会保険の事務手続②（定時決定・随時改定）"),
    Chapter(11, 501, 550, "社会保険料・労働保険料の計算演習"),
    Chapter(12, 551, 600, "所得税の源泉徴収のしくみ"),
    Chapter(13, 601, 650, "住民税の特別徴収と控除実務"),
    Chapter(14, 651, 700, "給与明細作成の総合計算演習"),
    Chapter(15, 701, 750, "賞与計算の知識と演習"),
    Chapter(16, 751, 800, "労働法令・社会保険制度の総合知識"),
]


# The supplied PDF is the transcription source.  These are the authoritative
# references used to independently review each legal topic as of the date used
# by the November 2026 level-2 exam (2026-09-01).
OFFICIAL_SOURCE_GROUPS: dict[str, list[str]] = {
    "exam_scope": ["https://fos.or.jp/shikaku/pc2/"],
    "labor_standards": ["https://elaws.e-gov.go.jp/document?lawid=322AC0000000049"],
    "labor_contract": ["https://elaws.e-gov.go.jp/document?lawid=419AC0000000128"],
    "worktime_leave": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/roudouzikan/index.html"],
    "overtime": ["https://hatarakikatakaikaku.mhlw.go.jp/overtime.html"],
    "rounding": ["https://jsite.mhlw.go.jp/kagoshima-roudoukyoku/yokuaru_goshitsumon/kyushokuchu/0310.html"],
    "digital_wage": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/zigyonushi/shienjigyou/03_00028.html"],
    "minimum_wage": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/chingin/index.html"],
    "labor_insurance": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/hoken/roudouhoken21/index.html"],
    "workers_comp": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/rousai/index.html"],
    "social_overview": ["https://www.nenkin.go.jp/service/learn/seidosetsumei.files/kounen2026_01.pdf"],
    "care_insurance": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/hukushi_kaigo/kaigo_koureisha/nintei/gaiyo4.html"],
    "short_worker": ["https://www.mhlw.go.jp/tekiyoukakudai/qa/"],
    "standard_remuneration": ["https://www.nenkin.go.jp/service/kounen/hokenryo/hoshu/20121017.html"],
    "regular_determination": ["https://www.nenkin.go.jp/service/kounen/hokenryo/hoshu/20121017.html"],
    "monthly_revision": ["https://www.nenkin.go.jp/section/faq/kounen/hyoujunhoushu/20140602-02.html"],
    "social_rates": ["https://www.kyoukaikenpo.or.jp/about/business/insurance_rate/rate_prefectures/r08/index.html"],
    "health_benefits": ["https://www.kyoukaikenpo.or.jp/faq/benefit/001/index.html"],
    "qualification_acquisition": ["https://www.nenkin.go.jp/service/kounen/tekiyo/hihokensha1/20150422.html"],
    "qualification_loss": ["https://www.nenkin.go.jp/service/kounen/tekiyo/hihokensha1/20150407-02.html"],
    "leave_exemption": ["https://www.nenkin.go.jp/service/kounen/hokenryo/menjo/"],
    "dependents": ["https://www.nenkin.go.jp/service/kounen/tekiyo/hihokensha1/20141202.html"],
    "bonus_insurance": ["https://www.nenkin.go.jp/service/kounen/hokenryo/hoshu/20141203.html"],
    "withholding": ["https://www.nta.go.jp/publication/pamph/gensen/zeigakuhyo2026/01.htm"],
    "commuting": ["https://www.nta.go.jp/users/gensen/2026tsukin/index.htm"],
    "resident_tax": ["https://www.tax.metro.tokyo.lg.jp/kazei/life/kojin_ju/tokubetsu/about"],
    "employment_insurance": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/0000134526.html"],
    "employment_procedures": ["https://www.mhlw.go.jp/bunya/koyou/koyouhoken/tetsuduki_ichiran01.html"],
    "working_conditions_2024": ["https://www.mhlw.go.jp/stf/newpage_32105.html"],
    "part_time": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/0000061842.html"],
    "childcare_leave": ["https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/0000130583.html"],
    "my_number": ["https://www.ppc.go.jp/legal/policy/my_number_guideline_jigyosha/"],
    "retirement_health": ["https://www.kyoukaikenpo.or.jp/faq/voluntary_continuation/002/"],
}


def build_question_source_map() -> dict[int, list[str]]:
    """Assign relevant official material to every question exactly once."""
    assignments: list[tuple[range | list[int], list[str]]] = [
        (range(1, 3), ["exam_scope"]), ([3], ["regular_determination"]),
        ([4], ["labor_insurance"]), ([5], ["resident_tax"]),
        (range(6, 14), ["exam_scope"]), (range(14, 30), ["labor_standards"]),
        ([30], ["workers_comp"]), (range(31, 41), ["labor_standards"]),
        (range(41, 45), ["digital_wage"]), (range(45, 48), ["labor_standards"]),
        (range(48, 49), ["minimum_wage"]), (range(49, 51), ["labor_standards"]),
        ([51], ["exam_scope"]), ([52], ["regular_determination"]),
        ([53], ["labor_insurance"]), ([54], ["resident_tax"]),
        (range(55, 58), ["exam_scope"]), (range(58, 72), ["labor_standards"]),
        (range(72, 74), ["digital_wage"]), (range(74, 88), ["labor_standards"]),
        (range(88, 91), ["minimum_wage"]), (range(91, 97), ["labor_standards"]),
        (range(97, 101), ["rounding"]), (range(101, 201), ["worktime_leave"]),
        (range(201, 301), ["overtime"]), (range(301, 303), ["health_benefits"]),
        (range(303, 307), ["care_insurance"]), (range(307, 320), ["social_overview"]),
        (range(320, 330), ["short_worker"]), (range(330, 345), ["standard_remuneration", "social_rates"]),
        (range(345, 350), ["health_benefits"]), ([350], ["social_overview"]),
        (range(351, 363), ["standard_remuneration"]),
        (range(363, 379), ["standard_remuneration", "social_rates"]),
        (range(379, 390), ["short_worker"]), (range(390, 392), ["care_insurance"]),
        (range(392, 401), ["social_overview", "health_benefits"]),
        (range(401, 416), ["qualification_acquisition", "qualification_loss"]),
        (range(416, 426), ["regular_determination"]), (range(426, 437), ["monthly_revision"]),
        (range(437, 446), ["leave_exemption"]), (range(446, 451), ["dependents"]),
        (range(451, 463), ["regular_determination"]), (range(463, 477), ["monthly_revision"]),
        (range(477, 489), ["qualification_acquisition", "qualification_loss"]),
        (range(489, 496), ["leave_exemption"]), (range(496, 501), ["dependents"]),
        (range(501, 546), ["standard_remuneration", "social_rates"]),
        (range(546, 550), ["employment_insurance"]), ([550], ["labor_insurance"]),
        (range(551, 587), ["withholding"]), ([587], ["exam_scope"]),
        (range(588, 626), ["resident_tax"]), (range(626, 629), ["withholding"]),
        (range(629, 636), ["commuting"]), (range(636, 650), ["withholding"]),
        ([650], ["exam_scope"]), (range(651, 653), ["exam_scope"]),
        (range(653, 655), ["commuting"]), (range(655, 658), ["standard_remuneration", "social_rates"]),
        (range(658, 660), ["employment_insurance"]), (range(660, 662), ["withholding"]),
        (range(662, 664), ["resident_tax"]),
        ([664], ["standard_remuneration", "employment_insurance", "withholding", "resident_tax"]),
        ([665], ["labor_standards"]), ([666], ["exam_scope"]), ([667], ["overtime"]),
        (range(668, 671), ["withholding"]), (range(671, 675), ["standard_remuneration", "social_rates"]),
        ([675], ["exam_scope"]), (range(676, 683), ["withholding"]), ([683], ["resident_tax"]),
        (range(684, 686), ["exam_scope"]), (range(686, 688), ["commuting"]),
        ([688], ["standard_remuneration"]), ([689], ["employment_insurance"]),
        ([690], ["bonus_insurance"]), ([691], ["commuting", "withholding"]),
        ([692], ["employment_insurance"]), ([693], ["withholding"]),
        ([694], ["standard_remuneration"]), ([695], ["resident_tax"]),
        (range(696, 699), ["exam_scope"]), ([699], ["withholding"]), ([700], ["exam_scope"]),
        (range(701, 727), ["bonus_insurance"]), (range(727, 733), ["leave_exemption"]),
        (range(733, 745), ["withholding"]), (range(745, 748), ["resident_tax"]),
        ([748], ["bonus_insurance", "withholding"]), ([749], ["bonus_insurance"]),
        ([750], ["withholding"]), ([751], ["labor_standards"]),
        ([752], ["working_conditions_2024"]), (range(753, 768), ["labor_standards"]),
        (range(768, 772), ["labor_contract"]), (range(772, 775), ["part_time"]),
        (range(775, 784), ["childcare_leave"]), (range(784, 786), ["labor_standards"]),
        ([786], ["health_benefits"]), ([787], ["leave_exemption"]),
        (range(788, 792), ["employment_insurance"]), (range(792, 796), ["workers_comp"]),
        (range(796, 798), ["retirement_health"]), ([798], ["my_number"]),
        (range(799, 801), ["qualification_acquisition", "qualification_loss", "employment_procedures"]),
    ]

    result: dict[int, list[str]] = {}
    for numbers, group_keys in assignments:
        urls = list(dict.fromkeys(
            url for group_key in group_keys for url in OFFICIAL_SOURCE_GROUPS[group_key]
        ))
        for number in numbers:
            if number in result:
                raise ValueError(f"Question {number}: official sources assigned twice")
            result[number] = urls

    if set(result) != set(range(1, 801)):
        missing = sorted(set(range(1, 801)) - set(result))
        raise ValueError(f"Official source assignments are incomplete: {missing}")
    return result


QUESTION_OFFICIAL_SOURCES = build_question_source_map()


QUESTION_CORRECTIONS: dict[int, dict[str, object]] = {
    587: {
        "question_text": "給与計算実務能力検定2級の公式な位置づけとして適切なものはどれか。",
        "choices": [
            {"key": "A", "text": "年末調整を除く通常の月次給与・賞与計算を扱う"},
            {"key": "B", "text": "年末調整の詳細計算を中心に扱う"},
            {"key": "C", "text": "退職金と年末調整を含む全業務を扱う"},
            {"key": "D", "text": "給与計算を扱わず労働法令だけを扱う"},
        ],
        "choice": "A",
        "explanation": "正解は『年末調整を除く通常の月次給与・賞与計算を扱う』である。検定公式案内では、2級は基本的な給与計算と賞与計算を行える水準とされ、年末調整を含む総合的な実務は1級の位置づけである。",
    },
    650: {
        "question_text": "給与計算実務能力検定2級で、原則として出題の前提となる法令の基準日はどれか。",
        "choices": [
            {"key": "A", "text": "試験実施日の当日"},
            {"key": "B", "text": "試験実施年の前年12月31日"},
            {"key": "C", "text": "受験申込を開始した日"},
            {"key": "D", "text": "試験実施月の前々月の1日"},
        ],
        "choice": "D",
        "explanation": "正解は『試験実施月の前々月の1日』である。検定公式案内では、11月の2級試験は同年9月1日現在、3月の2級試験は同年1月1日現在に施行されている法令等を原則的な前提としている。",
    },
}


QUESTION_TEXT_REWRITES: dict[int, str] = {
    77: "給与明細で労災保険料を処理する際、労働者負担分の控除として正しいものはどれか。",
    395: "業務外の病気やけがで医療機関を受診した場合の、健康保険の療養の給付として適切な説明はどれか。",
    396: "同一月の医療費の窓口負担が高額になった場合に利用する、高額療養費の説明として適切なものはどれか。",
}


VARIANT_ROLES = ["recall", "application", "boundary", "workflow", "misconception"]


# Independently recompute every question whose answer is a concrete amount or
# rate.  Keeping the arithmetic here makes a transcription error fail the
# import instead of silently becoming accepted content.
NUMERIC_RESULT_CHECKS: dict[int, str] = {
    85: f"{900_000 // 90:,}円",
    218: f"{round(1_200 * 1.25 * 2):,}円",
    243: f"{round(1_000 * (1 + 0.35 + 0.25)):,}円",
    266: f"{round(1_000 * 1.75 * 2):,}円を支払う",
    296: f"控除額は{1_200 * 2:,}円となる",
    332: f"被保険者の負担率は{18.3 / 2:.2f}%である。",
    364: f"事業主{18.3 / 2:.2f}%、被保険者{18.3 / 2:.2f}%",
    515: f"{round(200_000 * 0.099 / 2):,}円",
    516: f"{round(200_000 * 0.0162 / 2):,}円",
    560: f"{(300_000 - 10_000 - 40_000) // 10_000}万円",
    675: f"{200_000 + 20_000 + 10_000:,}円",
    676: f"{230_000 - 10_000 - 30_000:,}円",
    749: f"{250_999 // 1_000 * 1_000:,}円",
    750: f"{round(100_000 * 0.02 * 1.021):,}円",
}


def chapter_for(number: int) -> Chapter:
    return next(chapter for chapter in CHAPTERS if chapter.start <= number <= chapter.end)


def destination_for(number: int) -> tuple[str, str]:
    ranges = [
        (1, 50, "shikyu", "payroll-flow"),
        (51, 100, "shikyu", "payroll-flow"),
        (101, 200, "roudou", "roudou-jikan"),
        (201, 300, "roudou", "warimashi"),
        (301, 350, "shaho", "ryoritsu"),
        (351, 400, "shaho", "hyojun-hoshu"),
        (401, 425, "shaho", "tekiyo-tetsuzuki"),
        (426, 450, "shaho", "taishoku-shoyo"),
        (451, 500, "shaho", "zuiji-kaitei"),
        (501, 550, "keisan", "kyuyo-keisan"),
        (551, 600, "zei", "gensen"),
        (601, 650, "zei", "juminzei-shoyo"),
        (651, 700, "keisan", "kyuyo-keisan"),
        (701, 750, "keisan", "shoyo-tedori"),
        (751, 775, "roudou", "chingin-shiharai"),
        (776, 785, "roudou", "roudou-jikan"),
        (786, 800, "shaho", "ryoritsu"),
    ]
    for start, end, unit, lesson in ranges:
        if start <= number <= end:
            return unit, lesson
    raise ValueError(f"No destination for question {number}")


def clean_lines(value: str) -> str:
    lines: list[str] = []
    chapter_titles = {chapter.title for chapter in CHAPTERS}
    for raw in value.splitlines():
        line = raw.strip()
        if not line or re.fullmatch(r"-\s*\d+\s*-", line):
            continue
        if line in chapter_titles or line in {"（50問）", "解答・解説編"}:
            continue
        lines.append(line)
    return "".join(lines).strip()


def page_map(reader: PdfReader, page_start: int, page_end: int) -> dict[int, int]:
    mapping: dict[int, int] = {}
    for page_number in range(page_start, page_end + 1):
        text = reader.pages[page_number - 1].extract_text() or ""
        for number in re.findall(r"(?m)^問(\d+)\s*$", text):
            mapping[int(number)] = page_number
    return mapping


def extract_questions(reader: PdfReader) -> dict[int, dict[str, object]]:
    text = "\n".join((page.extract_text() or "") for page in reader.pages[3:184])
    pieces = re.split(r"(?m)^問(\d+)\s*$", text)[1:]
    if len(pieces) != 1600:
        raise ValueError(f"Expected 800 question blocks, got {len(pieces) // 2}")

    pages = page_map(reader, 4, 184)
    records: dict[int, dict[str, object]] = {}
    for number_raw, body in zip(pieces[0::2], pieces[1::2], strict=True):
        number = int(number_raw)
        split = re.split(r"(?m)^\s*([アイウエ])．", body)
        labels = split[1::2]
        if labels != list(CHOICE_KEYS):
            raise ValueError(f"Question {number}: expected ア〜エ, got {labels}")
        question_text = clean_lines(split[0])
        choices = [
            {"key": CHOICE_KEYS[label], "text": clean_lines(choice)}
            for label, choice in zip(labels, split[2::2], strict=True)
        ]
        if not question_text or any(not choice["text"] for choice in choices):
            raise ValueError(f"Question {number}: question or choice text is empty")
        if len({choice["text"] for choice in choices}) != 4:
            raise ValueError(f"Question {number}: choices are not unique")

        records[number] = {
            "question_text": question_text,
            "choices": choices,
            "source_page": pages[number],
        }
    return records


def extract_answers(reader: PdfReader) -> dict[int, dict[str, str]]:
    text = "\n".join((page.extract_text() or "") for page in reader.pages[185:265])
    pieces = re.split(r"(?m)^問(\d+)\s*正解[：:]\s*([アイウエ])\s*$", text)[1:]
    if len(pieces) != 2400:
        raise ValueError(f"Expected 800 answer blocks, got {len(pieces) // 3}")

    records: dict[int, dict[str, str]] = {}
    for number_raw, answer, body in zip(pieces[0::3], pieces[1::3], pieces[2::3], strict=True):
        number = int(number_raw)
        explanation = clean_lines(body)
        if not explanation.startswith("正解は"):
            raise ValueError(f"Question {number}: explanation is missing")
        records[number] = {"choice": CHOICE_KEYS[answer], "explanation": explanation}
    return records


def is_calculation(number: int, text: str) -> bool:
    calculation_chapters = {5, 6, 11, 14, 15}
    chapter = chapter_for(number)
    return chapter.number in calculation_chapters and bool(
        re.search(r"いくら|求め|計算|算出|金額|支払額|差引支給額", text)
    )


def build_records(reader: PdfReader) -> list[dict[str, object]]:
    questions = extract_questions(reader)
    answers = extract_answers(reader)
    if set(questions) != set(range(1, 801)) or set(answers) != set(range(1, 801)):
        raise ValueError("Question or answer numbers are not exactly 1..800")

    records: list[dict[str, object]] = []
    for number in range(1, 801):
        chapter = chapter_for(number)
        unit, lesson = destination_for(number)
        block = (number - chapter.start) // 5 + 1
        correction = QUESTION_CORRECTIONS.get(number)
        if correction is not None:
            questions[number]["question_text"] = correction["question_text"]
            questions[number]["choices"] = correction["choices"]
            answers[number]["choice"] = correction["choice"]
            answers[number]["explanation"] = correction["explanation"]
        if number in QUESTION_TEXT_REWRITES:
            questions[number]["question_text"] = QUESTION_TEXT_REWRITES[number]
        calculation = is_calculation(number, str(questions[number]["question_text"]))
        correct_choice = answers[number]["choice"]
        correct_text = next(
            choice["text"]
            for choice in questions[number]["choices"]
            if choice["key"] == correct_choice
        )
        if correct_text not in answers[number]["explanation"]:
            raise ValueError(
                f"Question {number}: explanation does not name the correct choice"
            )
        recomputed_result = NUMERIC_RESULT_CHECKS.get(number)
        if recomputed_result is not None and recomputed_result not in correct_text:
            raise ValueError(
                f"Question {number}: recomputed result {recomputed_result!r} "
                f"does not match correct choice {correct_text!r}"
            )
        records.append(
            {
                "source_id": f"exam-202608-q{number:03d}",
                "source_question_number": number,
                "source_chapter": chapter.number,
                "source_chapter_title": chapter.title,
                "source_page": questions[number]["source_page"],
                "verification_status": "official_sources_reviewed",
                "scope_status": "exam_2026-09-01",
                "exam_role": "calculation" if calculation else "knowledge",
                "unit": unit,
                "lesson": lesson,
                "concept_key": f"curriculum.{unit}.{lesson}.objective-{(number - 1) // 5 + 1:03d}",
                "learning_objective": f"{chapter.title}の重要論点を具体例から判断できる（{block}）",
                "variant_role": VARIANT_ROLES[(number - chapter.start) % len(VARIANT_ROLES)],
                "misconception_key": f"objective-{(number - 1) // 5 + 1:03d}-question-{number:03d}",
                "type": "choice",
                "category": chapter.title,
                "difficulty": "hard" if calculation else "medium",
                "question_text": questions[number]["question_text"],
                "choices": questions[number]["choices"],
                "answer": {"choice": correct_choice},
                "explanation": answers[number]["explanation"],
                "common_mistake": None,
                "distractor_feedback": None,
                "calc_params": None,
                "reference_sheet_slugs": [],
                "source_urls": QUESTION_OFFICIAL_SOURCES[number],
            }
        )
    return records


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("input", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()

    records = build_records(PdfReader(str(args.input)))
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(records, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print(f"Wrote {len(records)} questions to {args.output}")


if __name__ == "__main__":
    main()
