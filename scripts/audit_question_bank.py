"""Produce review inventory; never infer editorial approval from automatic checks.

Subjects (including PHP canonical fingerprints) are exported by
`php artisan content:review-subjects`. This script only initializes pending
records when explicitly requested; existing decisions/hashes are preserved.
"""
import argparse
import csv
import difflib
import json
import re
import subprocess
import unicodedata
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / 'database/seeders/data'


def normalize(text):
    return re.sub(r'\s+', '', unicodedata.normalize('NFKC', text))


def chapter(q):
    topic = q['topic_key']
    if topic == 'exam.scope':
        return '試験案内（章外）'
    if topic.startswith(('bonus.', 'calculation.bonus')):
        return '賞与計算のしかた'
    if topic.startswith(('calculation.', 'payroll.integrated')):
        return '給与計算の演習問題'
    if topic.startswith(('attendance.', 'labor.hours', 'labor.breaks', 'labor.holidays', 'labor.flex', 'labor.variable', 'labor.deemed', 'labor.discretionary', 'labor.supervisor', 'labor.leave', 'leave.')):
        return '勤怠欄'
    if topic.startswith(('social.qualification', 'social.acquisition', 'social.regular', 'social.monthly', 'social.base-days', 'social.employment-procedures', 'labor-insurance.')):
        return '社会保険の事務手続き'
    if topic.startswith(('social.', 'employment.')):
        return '給与計算担当者が知っておきたい社会保険制度'
    if topic.startswith(('tax.', 'withholding.', 'resident-tax.', 'rates.', 'labor.insurance')):
        return '控除項目欄'
    if topic.startswith(('premium.', 'commute.', 'wage.absence', 'wage.average')):
        return '支給項目欄'
    if topic.startswith(('labor.', 'rules.', 'wage.')):
        return '給与計算担当者が知っておきたい法律'
    return '給与計算とは'


def export_csv(path, rows, columns):
    with path.open('w', encoding='utf-8-sig', newline='') as handle:
        writer = csv.DictWriter(handle, fieldnames=columns)
        writer.writeheader()
        writer.writerows(rows)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('subjects', type=Path)
    parser.add_argument('--output-dir', type=Path, required=True)
    parser.add_argument('--initialize-ledger', action='store_true')
    parser.add_argument('--pdf', type=Path)
    args = parser.parse_args()
    out = args.output_dir
    out.mkdir(parents=True, exist_ok=True)
    subjects = json.loads(args.subjects.read_text())
    ledger_path = DATA / 'question-reviews.json'
    ledger = json.loads(ledger_path.read_text()) if ledger_path.exists() else {'schema_version': 1, 'questions': {}}
    if args.initialize_ledger:
        for id, subject in subjects.items():
            ledger['questions'].setdefault(id, {
                'decision': 'insufficient', 'verification': 'pending',
                'fingerprint': subject['fingerprint'], 'reviewed_at': None,
                'review_due_at': None, 'checks': {}, 'evidence': [],
                'notes': '逐問の正答・全選択肢・解説と一次資料の該当箇所の照合は未完了。自動検査のみで承認しない。',
            })
        ledger_path.write_text(json.dumps(ledger, ensure_ascii=False, indent=2) + '\n')
    exams = json.loads((DATA / 'mock-exams.json').read_text())
    held = {item['question_id']: exam['slug'] for exam in exams for item in exam['questions']}
    baseline = json.loads((out / 'baseline.json').read_text())
    inventory, calculation, topics = [], [], defaultdict(list)
    ngrams = {}
    for id, subject in subjects.items():
        q = subject['question']
        record = ledger['questions'].get(id, {})
        choices = q.get('choices') or []
        flags = []
        if q['type'] == 'choice':
            if len(choices) != 4 or len({normalize(c['text']) for c in choices}) != 4:
                flags.append('選択肢数・重複')
            if q['answer'].get('choice') not in {c['key'] for c in choices}:
                flags.append('正答キー不在')
            if set(q.get('distractor_feedback') or {}) - {c['key'] for c in choices}:
                flags.append('誤答解説キー不在')
        if not q.get('explanation'):
            flags.append('解説欠落')
        if isinstance(subject['sources'], dict) and None in subject['sources'].values():
            flags.append('根拠資料未登録')
        is_amount = bool(choices) and all(re.fullmatch(r'[\d,.]+(?:円|万円)', normalize(c['text'])) for c in choices)
        stem = normalize(q['question_text'])
        multiple_values = len(re.findall(r'\d[\d,]*(?:円|万円|時間|分|日|%|％)', stem)) >= 2
        candidate = bool(q.get('calc_params') or is_amount or re.search(r'いくら|何時間|何分|何日|算出せよ|求めよ', stem)
                         or (multiple_values and re.search(r'額|算定|支払|控除|計算|何|どれ', stem)))
        if q.get('exam_role') == 'calculation' and not candidate:
            flags.append('計算分類の再確認')
        if candidate and not q.get('calc_params'):
            flags.append('構造化計算未登録')
        row = {'id': id, 'baseline': id in baseline['questions'], 'topic': q['topic_key'],
               'chapter_mapping': chapter(q), 'study_tier': q['study_tier'], 'mock': held.get(id, ''),
               'question': q['question_text'], 'decision': record.get('decision', 'missing'),
               'verification': record.get('verification', 'pending'), 'automatic_flags': ';'.join(flags),
               'fingerprint_matches': record.get('fingerprint') == subject['fingerprint']}
        inventory.append(row)
        topics[q['topic_key']].append(row)
        if candidate or q.get('exam_role') == 'calculation':
            calculation.append({'id': id, 'question': q['question_text'], 'candidate_by_text': candidate,
                                'calc_type': (q.get('calc_params') or {}).get('calc_type'),
                                'mock': held.get(id), 'independent_verification': record.get('checks', {}).get('calculation')})
        stem = normalize(q['question_text'])
        ngrams[id] = {stem[i:i+3] for i in range(max(0, len(stem)-2))}
    for id, record in ledger['questions'].items():
        if id in subjects:
            continue
        q = record.get('retired_question', {})
        inventory.append({'id': id, 'baseline': id in baseline['questions'],
                          'topic': q.get('topic_key', ''), 'chapter_mapping': '', 'study_tier': q.get('study_tier', ''),
                          'mock': '', 'question': q.get('question_text', ''), 'decision': record.get('decision', 'missing'),
                          'verification': record.get('verification', 'pending'), 'automatic_flags': '',
                          'fingerprint_matches': '退役：公開対象外'})
    inventory.sort(key=lambda r: r['id'])
    export_csv(out / 'question-inventory.csv', inventory, list(inventory[0]))
    coverage = []
    for topic, rows in sorted(topics.items()):
        coverage.append({'topic': topic, 'objective': subjects[rows[0]['id']]['learning_objective'],
                         'chapter_mapping': rows[0]['chapter_mapping'],
                         'normal_ids': ' '.join(r['id'] for r in rows if not r['mock']),
                         'normal_core_ids': ' '.join(r['id'] for r in rows if not r['mock'] and r['study_tier']=='core'),
                         'mock_ids': ' '.join(r['id'] for r in rows if r['mock']),
                         'scope_verification': '章への仮割当。公式テキスト本文・章内の範囲との照合は未実施'})
    export_csv(out / 'coverage.csv', coverage, list(coverage[0]))
    # Rebuild these reports with the inventory so retired IDs cannot linger in
    # the scope map and later revisions cannot be omitted from the change list.
    official_chapters = [
        '給与計算とは', '勤怠欄', '支給項目欄', '控除項目欄', '社会保険の事務手続き',
        '賞与計算のしかた', '給与計算担当者が知っておきたい法律',
        '給与計算担当者が知っておきたい社会保険制度', '給与計算の演習問題',
    ]
    scope = []
    for name in official_chapters:
        rows = [row for row in inventory if row['id'] in subjects and row['chapter_mapping'] == name]
        scope.append({
            'official_chapter': name, 'source': 'https://jitsumu-up.jp/textbooks/',
            'normal_ids': ' '.join(row['id'] for row in rows if not row['mock']),
            'mock_ids': ' '.join(row['id'] for row in rows if row['mock']),
            'assessment': '公開目次の章と既存問題の仮対応。公式テキストの節・例題・演習を未入手のため章の充足を判定しない。',
        })
    export_csv(out / 'official-scope.csv', scope, list(scope[0]))
    baseline_bank = json.loads(subprocess.check_output(
        ['git', 'show', f"{baseline['git_commit']}:database/seeders/data/question-bank.json"], cwd=ROOT))
    baseline_questions = {q['id']: q for q in baseline_bank['questions']}
    changes = []
    for row in inventory:
        id = row['id']
        record = ledger['questions'].get(id, {})
        current = subjects.get(id, {}).get('question')
        old = baseline_questions.get(id)
        fields = sorted(k for k in set(current or {}) | set(old or {})
                        if (current or {}).get(k) != (old or {}).get(k))
        if current is None:
            kind, fields = '退役', []
        elif old is None:
            kind, fields = '追加', []
        elif fields:
            kind = '修正'
        else:
            continue
        changes.append({'id': id, 'change': kind, 'fields': ' '.join(fields),
                        'reason': record.get('notes', ''), 'merged_into': record.get('merged_into', ''),
                        'approval': record.get('verification', 'pending')})
    export_csv(out / 'changes.csv', changes, ['id', 'change', 'fields', 'reason', 'merged_into', 'approval'])
    similarities = []
    similarity_review_path = out / 'similarity-reviews.json'
    similarity_reviews = {
        (row['left'], row['right']): row
        for row in (json.loads(similarity_review_path.read_text()) if similarity_review_path.exists() else [])
    }
    ids = list(subjects)
    for n, left in enumerate(ids):
        for right in ids[n+1:]:
            a, b = ngrams[left], ngrams[right]
            score = len(a & b) / max(1, len(a | b))
            if score >= .65:
                review = similarity_reviews.get((left, right), {})
                current_review = (review.get('left_fingerprint') == subjects[left]['fingerprint']
                                  and review.get('right_fingerprint') == subjects[right]['fingerprint'])
                similarities.append({'left': left, 'right': right, 'trigram_jaccard': round(score, 3),
                                     'decision': review['decision'] if current_review else '候補のみ。異なる技能・条件を測るか要確認',
                                     'review_matches': current_review})
    for name, data in [('calculation-inventory.json', calculation), ('similarity-candidates.json', similarities)]:
        (out / name).write_text(json.dumps(data, ensure_ascii=False, indent=2)+'\n')
    if args.pdf:
        from pypdf import PdfReader
        from extract_exam_202608 import extract_questions, NUMERIC_RESULT_CHECKS
        original = extract_questions(PdfReader(args.pdf))
        old_map = []
        for number, value in NUMERIC_RESULT_CHECKS.items():
            stem = normalize(original[number]['question_text'])
            candidates = sorted(({'id': id, 'similarity': round(difflib.SequenceMatcher(None, stem, normalize(subject['question']['question_text'])).ratio(), 4)} for id, subject in subjects.items()), key=lambda c: c['similarity'], reverse=True)[:3]
            old_map.append({'original_number': number, 'original_page': original[number]['source_page'],
                            'old_expected_result': value, 'candidates': candidates,
                            'mapping_status': 'exact_stem' if candidates[0]['similarity']==1 else 'candidate_only',
                            'current_calculation_verified': False})
        (out / 'legacy-calculation-map.json').write_text(json.dumps(old_map, ensure_ascii=False, indent=2)+'\n')
    print(json.dumps({'current_questions':len(subjects), 'inventory_records':len(inventory), 'baseline_questions':len(baseline['questions']),
                      'topics':len(coverage), 'normal_core_gaps':sum(not r['normal_core_ids'] for r in coverage),
                      'calculation_candidates':len(calculation), 'similarity_candidates':len(similarities),
                      'completed_reviews':sum(r['verification']=='complete' for r in inventory)}, ensure_ascii=False))


if __name__ == '__main__':
    main()
