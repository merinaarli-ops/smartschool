<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * NCTB (Bangladesh National Curriculum and Textbook Board) subject
 * catalogue used to auto-seed a new tenant's `subject` table.
 *
 * Structure
 * ---------
 *   $config['nctb_subjects']['levels'] = [
 *       <level_key> => [
 *           'name'     => human-readable level label,
 *           'classes'  => list of class_numeric values this level covers,
 *           'subjects' => list of subject rows for this level,
 *       ],
 *       ...
 *   ];
 *
 * Each subject row supplies:
 *   name  — English NCTB nomenclature (suffixed with the level to avoid
 *           collisions across SSC/HSC streams, e.g. "Bangla 1st Paper (SSC)")
 *   code  — NCTB textbook code where one exists, otherwise an internal
 *           short code (kept stable for joins on subject_code)
 *   type  — 'Theory' | 'Practical' | 'Optional' | 'Mandatory'
 *           Matches the existing /subject form's subject_type dropdown.
 *
 * Codes follow the NCTB textbook codes printed on the back cover of each
 * book where official codes are available; for newer / less-standardised
 * subjects we use NCTB-style 3-digit codes.
 *
 * Seeder usage:
 *   $this->load->library('nctb_subject_seeder');
 *   $result = $this->nctb_subject_seeder->seedForBranch($branchId);
 *
 * @author SmartSchool.bd
 */
/**
 * Standard NCTB Bangladesh class roster — the seeder uses this to make
 * sure every tenant has the canonical class list (Play .. Twelve) so
 * subject_assign rows can be wired up cleanly. `name_numeric` matches
 * the column on the existing `class` table and is the join key the
 * subject catalogue uses to figure out which classes each subject
 * belongs to.
 */
$config['nctb_classes'] = [
    ['name' => 'শিশু শ্রেণি',     'name_en' => 'Play',     'name_numeric' => '0'],
    ['name' => 'নার্সারি',        'name_en' => 'Nursery',  'name_numeric' => '01'],
    ['name' => 'প্রথম শ্রেণি',     'name_en' => 'Class 1',  'name_numeric' => '1'],
    ['name' => 'দ্বিতীয় শ্রেণি',  'name_en' => 'Class 2',  'name_numeric' => '2'],
    ['name' => 'তৃতীয় শ্রেণি',    'name_en' => 'Class 3',  'name_numeric' => '3'],
    ['name' => 'চতুর্থ শ্রেণি',    'name_en' => 'Class 4',  'name_numeric' => '4'],
    ['name' => 'পঞ্চম শ্রেণি',     'name_en' => 'Class 5',  'name_numeric' => '5'],
    ['name' => 'ষষ্ঠ শ্রেণি',      'name_en' => 'Class 6',  'name_numeric' => '6'],
    ['name' => 'সপ্তম শ্রেণি',     'name_en' => 'Class 7',  'name_numeric' => '7'],
    ['name' => 'অষ্টম শ্রেণি',     'name_en' => 'Class 8',  'name_numeric' => '8'],
    ['name' => 'নবম শ্রেণি',       'name_en' => 'Class 9',  'name_numeric' => '9'],
    ['name' => 'দশম শ্রেণি',       'name_en' => 'Class 10', 'name_numeric' => '10'],
    ['name' => 'একাদশ শ্রেণি',     'name_en' => 'Class 11', 'name_numeric' => '11'],
    ['name' => 'দ্বাদশ শ্রেণি',    'name_en' => 'Class 12', 'name_numeric' => '12'],
];

/**
 * Institute-type → class roster map. The seeder consults this when
 * `branch.institute_type` (and, for `school`, `branch.institute_subtype`)
 * is set, so a freshly approved tenant only gets the classes that match
 * the kind of school they signed up as. Keys are `nctb_classes.name_numeric`.
 *
 *   primary          → শিশু..পঞ্চম শ্রেণি   (Play..Class 5)
 *   high_school      → ষষ্ঠ..দশম শ্রেণি     (Class 6..10)
 *   primary_high     → শিশু..দশম শ্রেণি     (Play..Class 10) — School "Primary + High School"
 *   school_college   → প্রথম..দ্বাদশ শ্রেণি (Class 1..12)
 *   college          → একাদশ..দ্বাদশ শ্রেণি (Class 11..12)
 *   madrasah         → প্রথম..দ্বাদশ শ্রেণি (Class 1..12)
 *   technical        → নবম..দ্বাদশ শ্রেণি   (Class 9..12)
 *   kg               → শিশু, নার্সারি, প্রথম, দ্বিতীয় শ্রেণি (Play..Class 2)
 *   university       → []                                  (no class roster)
 *   other / unknown  → full roster                          (safe fallback)
 *
 * When `institute_type === 'school'`, the seeder reads `institute_subtype`
 * (`primary` / `high_school` / `primary_high`) and uses the matching key
 * from this table. If `institute_subtype` is missing, falls back to
 * `primary_high` (most permissive of the three).
 */
$config['nctb_institute_type_classes'] = [
    'primary'        => ['0', '01', '1', '2', '3', '4', '5'],
    'high_school'    => ['6', '7', '8', '9', '10'],
    'primary_high'   => ['0', '01', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
    'school_college' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
    'college'        => ['11', '12'],
    'madrasah'       => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
    'technical'      => ['9', '10', '11', '12'],
    'kg'             => ['0', '01', '1', '2'],
    'university'     => [],
];

/**
 * When the user picks "School" on the signup form they're then asked
 * to specify the sub-type via a second dropdown. The values stored on
 * `saas_pending_request.institute_subtype` (and copied to
 * `branch.institute_subtype` on approval) are the keys below; the
 * labels are surfaced verbatim by `Signup::institute_subtypes()`.
 *
 *  primary      = প্রাথমিক              → শিশু..পঞ্চম শ্রেণি
 *  high_school  = মাধ্যমিক              → ষষ্ঠ..দশম শ্রেণি
 *  primary_high = প্রাথমিক ও মাধ্যমিক  → শিশু..দশম শ্রেণি
 */
$config['nctb_institute_type_subtypes'] = [
    'school' => [
        'primary'      => 'প্রাথমিক (Primary)',
        'high_school'  => 'মাধ্যমিক (High School)',
        'primary_high' => 'প্রাথমিক ও মাধ্যমিক (Primary + High School)',
    ],
];

/**
 * Legacy class names that the seeder is allowed to auto-rename to the
 * current canonical name above. Keyed by `name_numeric`. If a class on
 * a tenant branch has the matching numeric AND its current `name`
 * exactly matches one of the entries in the corresponding legacy list,
 * the seeder will rename it to the canonical name. Any other name
 * (e.g. a school's custom name like "SSC Section") is left alone, so
 * customised class names are never overwritten.
 */
$config['nctb_class_legacy_names'] = [
    '0'  => ['Play', 'KG', 'KG-1', 'Pre-Primary', 'Pre Primary', 'Class 0', 'Shishu'],
    '01' => ['Nursery', 'KG-2', 'Class 01'],
    '1'  => ['One', 'Class One', 'Standard 1', '1', 'Class-1', 'Class I', 'Class 1'],
    '2'  => ['Two', 'Class Two', 'Standard 2', '2', 'Class-2', 'Class II', 'Class 2'],
    '3'  => ['Three', 'Class Three', 'Standard 3', '3', 'Class-3', 'Class III', 'Class 3'],
    '4'  => ['Four', 'Class Four', 'Standard 4', '4', 'Class-4', 'Class IV', 'Class 4'],
    '5'  => ['Five', 'Class Five', 'Standard 5', '5', 'Class-5', 'Class V', 'Class 5'],
    '6'  => ['Six', 'Class Six', 'Standard 6', '6', 'Class-6', 'Class VI', 'Class 6'],
    '7'  => ['Seven', 'Class Seven', 'Standard 7', '7', 'Class-7', 'Class VII', 'Class 7'],
    '8'  => ['Eight', 'Class Eight', 'Standard 8', '8', 'Class-8', 'Class VIII', 'Class 8'],
    '9'  => ['Nine', 'Class Nine', 'Standard 9', '9', 'Class-9', 'Class IX', 'Class 9'],
    '10' => ['Ten', 'Class Ten', 'Standard 10', '10', 'Class-10', 'Class X', 'Class 10'],
    '11' => ['Eleven', 'Class Eleven', 'Standard 11', '11', 'Class-11', 'Class XI', 'Class 11'],
    '12' => ['Twelve', 'Class Twelve', 'Standard 12', '12', 'Class-12', 'Class XII', 'Class 12'],
];

/**
 * Default Section seeded onto every freshly-provisioned tenant so the
 * subject_assign foreign keys have something to point at. Tenants can
 * add more sections (B, C, …) later; new sections automatically inherit
 * the same subject roster via the Sections::save hook + the seeder's
 * `seedSubjectAssignsForBranch()`.
 */
$config['nctb_default_section'] = ['name' => 'A', 'capacity' => ''];

/**
 * NCTB Bangladesh GPA-5 grade scale. Seeded onto every tenant on
 * approval; admin can edit/delete/add from /grades.
 *   A+ : 80-100  → 5.00 (Excellent)
 *   A  : 70-79   → 4.00 (Very Good)
 *   A- : 60-69   → 3.50 (Good)
 *   B  : 50-59   → 3.00 (Satisfactory)
 *   C  : 40-49   → 2.00 (Acceptable)
 *   D  : 33-39   → 1.00 (Pass)
 *   F  : 0-32    → 0.00 (Fail)
 */
$config['nctb_grades'] = [
    ['name' => 'A+', 'grade_point' => '5.00', 'lower_mark' => 80, 'upper_mark' => 100, 'remark' => 'Excellent'],
    ['name' => 'A',  'grade_point' => '4.00', 'lower_mark' => 70, 'upper_mark' => 79,  'remark' => 'Very Good'],
    ['name' => 'A-', 'grade_point' => '3.50', 'lower_mark' => 60, 'upper_mark' => 69,  'remark' => 'Good'],
    ['name' => 'B',  'grade_point' => '3.00', 'lower_mark' => 50, 'upper_mark' => 59,  'remark' => 'Satisfactory'],
    ['name' => 'C',  'grade_point' => '2.00', 'lower_mark' => 40, 'upper_mark' => 49,  'remark' => 'Acceptable'],
    ['name' => 'D',  'grade_point' => '1.00', 'lower_mark' => 33, 'upper_mark' => 39,  'remark' => 'Pass'],
    ['name' => 'F',  'grade_point' => '0.00', 'lower_mark' => 0,  'upper_mark' => 32,  'remark' => 'Fail'],
];

/**
 * Default exam term roster — three terms used by most Bangladesh
 * schools. Seeded onto every tenant for the active session; admin
 * can rename / add more (eg "Pre-Test", "Class Test 1") from
 * /exam_term.
 */
$config['nctb_exam_terms'] = [
    'First Term',
    'Half-Yearly',
    'Final',
];

/**
 * Institute-type → exam term roster. Used by the seeder to pick the
 * three default term names that match the kind of school being
 * provisioned (Q4 of the auto-setup plan). Falls back to
 * `nctb_exam_terms` (Half Yearly · Mid · Annual) when the type is
 * unknown.
 *
 * Order matters — the matching `nctb_institute_type_starter_exams`
 * entry below pairs each term name with the starter exam to seed
 * against it.
 */
$config['nctb_institute_type_exam_terms'] = [
    'primary'        => ['১ম সাময়িক',  '২য় সাময়িক',         'বার্ষিক'],
    'high_school'    => ['অর্ধ বার্ষিক', 'প্রাক্-নির্বাচনী',  'বার্ষিক'],
    'primary_high'   => ['১ম সাময়িক',  'অর্ধ বার্ষিক',       'বার্ষিক'],
    'school_college' => ['অর্ধ বার্ষিক', 'প্রাক্-নির্বাচনী',  'বার্ষিক'],
    'college'        => ['প্রথম সাময়িক', 'দ্বিতীয় সাময়িক',  'নির্বাচনী'],
    'madrasah'       => ['১ম সাময়িক',  '২য় সাময়িক',         'বার্ষিক'],
    'technical'      => ['প্রথম পর্ব',   'দ্বিতীয় পর্ব',       'চূড়ান্ত পর্ব'],
    'kg'             => ['১ম সাময়িক',  '২য় সাময়িক',         'বার্ষিক'],
    'university'     => [], // no auto exam terms — universities use semesters
];

/**
 * Default mark-distribution categories used by the exam module to
 * break a subject's total into pieces. Names follow NCTB convention.
 * Admin can edit/add from /mark_distribution.
 */
$config['nctb_mark_distributions'] = [
    'Theory',
    'Practical',
    'Subjective',
    'Objective',
    'CT',         // Class Test
    'MCQ',
];

/**
 * Default full / pass marks per distribution category. Keyed by the
 * canonical English name (case-insensitive lookup also matches the
 * Bangla aliases below). Used by the seeder when it builds the
 * `timetable_exam.mark_distribution` JSON so the mark-entry form has
 * a working `full_mark` / `pass_mark` per slice out of the box.
 *
 * Admin can rewrite per (class, section, subject, exam) from
 * /timetable → "Exam Schedule".
 */
$config['nctb_mark_distribution_defaults'] = [
    'theory'     => ['full_mark' => 100, 'pass_mark' => 33],
    'practical'  => ['full_mark' => 50,  'pass_mark' => 17],
    'subjective' => ['full_mark' => 70,  'pass_mark' => 23],
    'objective'  => ['full_mark' => 30,  'pass_mark' => 10],
    'ct'         => ['full_mark' => 20,  'pass_mark' => 7],
    'mcq'        => ['full_mark' => 30,  'pass_mark' => 10],
    // Bangla aliases used by _bnAlias() in Nctb_subject_seeder.
    'তত্ত্বীয়'   => ['full_mark' => 100, 'pass_mark' => 33],
    'ব্যবহারিক'  => ['full_mark' => 50,  'pass_mark' => 17],
    'রচনামূলক'   => ['full_mark' => 70,  'pass_mark' => 23],
    'বহুনির্বাচনি' => ['full_mark' => 30,  'pass_mark' => 10],
    'শ্রেণি অভীক্ষা (সিটি)' => ['full_mark' => 20, 'pass_mark' => 7],
    'এমসিকিউ'    => ['full_mark' => 30,  'pass_mark' => 10],
];

/**
 * Default starter exams seeded onto every tenant once the exam terms
 * + mark distributions are in place. Each entry creates one `exam`
 * row anchored to the matching term (resolved by name) with the
 * listed mark distribution slices (resolved by name → id and stored
 * as a JSON id list, matching the existing Exam_model::exam_save
 * format). Admin can rename / delete / add from /exam.
 *
 * type_id values match the column comment on `exam`:
 *   1 = mark only · 2 = GPA only · 3 = both (recommended default)
 *
 * publish_result = 1 makes the exam appear on the public
 * /<school>/exam_results lookup page so students/parents can
 * immediately see the demo report cards. Admin can unpublish from
 * /exam at any time.
 */
$config['nctb_starter_exams'] = [
    [
        'name'              => 'First Term Examination',
        'term_name'         => 'First Term',
        'type_id'           => 3,
        'mark_distribution' => ['Theory', 'Practical'],
        'remark'            => 'Auto-seeded NCTB starter exam — edit or replace as needed.',
        'publish'           => 1,
    ],
    [
        'name'              => 'Half-Yearly Examination',
        'term_name'         => 'Half-Yearly',
        'type_id'           => 3,
        'mark_distribution' => ['Theory', 'Practical'],
        'remark'            => 'Auto-seeded NCTB starter exam — edit or replace as needed.',
        'publish'           => 1,
    ],
    [
        'name'              => 'Final Examination',
        'term_name'         => 'Final',
        'type_id'           => 3,
        'mark_distribution' => ['Theory', 'Practical'],
        'remark'            => 'Auto-seeded NCTB starter exam — edit or replace as needed.',
        'publish'           => 1,
    ],
];

/**
 * Canonical Bangla name for each NCTB English subject base name.
 * Used by Nctb_subject_seeder to:
 *   - translate catalogue rows from English → Bangla at insert time
 *   - collapse Bangla / English / parenthetical-level variants of the
 *     same subject into a single `subject` row (one row, many class
 *     assignments — instead of one row per (subject × level)).
 *
 * Keys are the level-qualifier-free English form (e.g. "Bangla 1st
 * Paper", NOT "Bangla 1st Paper (HSC)"). Lookup happens after the
 * seeder strips any parenthetical "(Primary)" / "(JSC)" / "(এসএসসি)"
 * qualifier from the catalogue name.
 *
 * Order of preference for entries that share a base name across NCTB
 * levels (e.g. Primary "Mathematics" vs JSC "Mathematics"): one entry
 * is enough — the seeder merges multi-level rows into a single subject
 * and assigns it to every relevant class.
 */
$config['nctb_subject_canonical_names'] = [
    'Accounting'                                       => 'হিসাববিজ্ঞান',
    'Accounting 1st Paper'                             => 'হিসাববিজ্ঞান ১ম পত্র',
    'Accounting 2nd Paper'                             => 'হিসাববিজ্ঞান ২য় পত্র',
    'Agriculture Studies'                              => 'কৃষিশিক্ষা',
    'Arabic'                                           => 'আরবি',
    'Arts and Crafts'                                  => 'চারু ও কারুকলা',
    'Bangla'                                           => 'বাংলা',
    'Bangla 1st Paper'                                 => 'বাংলা ১ম পত্র',
    'Bangla 2nd Paper'                                 => 'বাংলা ২য় পত্র',
    'Bangladesh and Global Studies'                    => 'বাংলাদেশ ও বিশ্বপরিচয়',
    'Bengali Literature'                               => 'বাংলা সাহিত্য',
    'Biology'                                          => 'জীববিজ্ঞান',
    'Biology 1st Paper'                                => 'জীববিজ্ঞান ১ম পত্র',
    'Biology 2nd Paper'                                => 'জীববিজ্ঞান ২য় পত্র',
    'Buddhist Religion and Moral Education'            => 'বৌদ্ধধর্ম শিক্ষা',
    'Business Entrepreneurship'                        => 'ব্যবসায় উদ্যোগ',
    'Business Organization and Management 1st Paper'   => 'ব্যবসায় সংগঠন ও ব্যবস্থাপনা ১ম পত্র',
    'Business Organization and Management 2nd Paper'   => 'ব্যবসায় সংগঠন ও ব্যবস্থাপনা ২য় পত্র',
    'Career Education'                                 => 'কর্ম ও জীবনমুখী শিক্ষা',
    'Chemistry'                                        => 'রসায়ন',
    'Chemistry 1st Paper'                              => 'রসায়ন ১ম পত্র',
    'Chemistry 2nd Paper'                              => 'রসায়ন ২য় পত্র',
    'Christian Religion and Moral Education'           => 'খ্রিষ্টধর্ম শিক্ষা',
    'Civics and Citizenship'                           => 'পৌরনীতি ও নাগরিকতা',
    'Civics and Good Governance 1st Paper'             => 'পৌরনীতি ও সুশাসন ১ম পত্র',
    'Civics and Good Governance 2nd Paper'             => 'পৌরনীতি ও সুশাসন ২য় পত্র',
    'Economics'                                        => 'অর্থনীতি',
    'Economics 1st Paper'                              => 'অর্থনীতি ১ম পত্র',
    'Economics 2nd Paper'                              => 'অর্থনীতি ২য় পত্র',
    'English'                                          => 'ইংরেজি',
    'English 1st Paper'                                => 'ইংরেজি ১ম পত্র',
    'English 2nd Paper'                                => 'ইংরেজি ২য় পত্র',
    'Finance and Banking'                              => 'ফিন্যান্স ও ব্যাংকিং',
    'Finance, Banking and Insurance 1st Paper'         => 'ফিন্যান্স, ব্যাংকিং ও বীমা ১ম পত্র',
    'Finance, Banking and Insurance 2nd Paper'         => 'ফিন্যান্স, ব্যাংকিং ও বীমা ২য় পত্র',
    'General Mathematics'                              => 'সাধারণ গণিত',
    'Geography 1st Paper'                              => 'ভূগোল ১ম পত্র',
    'Geography 2nd Paper'                              => 'ভূগোল ২য় পত্র',
    'Geography and Environment'                        => 'ভূগোল ও পরিবেশ',
    'Higher Mathematics'                               => 'উচ্চতর গণিত',
    'Higher Mathematics 1st Paper'                     => 'উচ্চতর গণিত ১ম পত্র',
    'Higher Mathematics 2nd Paper'                     => 'উচ্চতর গণিত ২য় পত্র',
    'Hindu Religion and Moral Education'               => 'হিন্দুধর্ম শিক্ষা',
    'History 1st Paper'                                => 'ইতিহাস ১ম পত্র',
    'History 2nd Paper'                                => 'ইতিহাস ২য় পত্র',
    'History of Bangladesh and World Civilization'     => 'বাংলাদেশের ইতিহাস ও বিশ্বসভ্যতা',
    'Home Science'                                     => 'গার্হস্থ্য বিজ্ঞান',
    'Information and Communication Technology'         => 'তথ্য ও যোগাযোগ প্রযুক্তি',
    'Islam and Moral Education'                        => 'ইসলাম শিক্ষা',
    'Islamic History and Culture 1st Paper'            => 'ইসলামের ইতিহাস ও সংস্কৃতি ১ম পত্র',
    'Islamic History and Culture 2nd Paper'            => 'ইসলামের ইতিহাস ও সংস্কৃতি ২য় পত্র',
    'Islamic Studies 1st Paper'                        => 'ইসলাম শিক্ষা ১ম পত্র',
    'Islamic Studies 2nd Paper'                        => 'ইসলাম শিক্ষা ২য় পত্র',
    'Logic 1st Paper'                                  => 'যুক্তিবিদ্যা ১ম পত্র',
    'Logic 2nd Paper'                                  => 'যুক্তিবিদ্যা ২য় পত্র',
    'Mathematics'                                      => 'গণিত',
    'Pali'                                             => 'পালি',
    'Physical Education and Health'                    => 'শারীরিক শিক্ষা ও স্বাস্থ্য',
    'Physics'                                          => 'পদার্থবিজ্ঞান',
    'Physics 1st Paper'                                => 'পদার্থবিজ্ঞান ১ম পত্র',
    'Physics 2nd Paper'                                => 'পদার্থবিজ্ঞান ২য় পত্র',
    'Primary Science'                                  => 'প্রাথমিক বিজ্ঞান',
    'Production Management and Marketing 1st Paper'    => 'উৎপাদন ব্যবস্থাপনা ও বিপণন ১ম পত্র',
    'Production Management and Marketing 2nd Paper'    => 'উৎপাদন ব্যবস্থাপনা ও বিপণন ২য় পত্র',
    'Psychology 1st Paper'                             => 'মনোবিজ্ঞান ১ম পত্র',
    'Psychology 2nd Paper'                             => 'মনোবিজ্ঞান ২য় পত্র',
    'Religion and Moral Education'                     => 'ধর্ম ও নৈতিক শিক্ষা',
    'Sanskrit'                                         => 'সংস্কৃত',
    'Science'                                          => 'বিজ্ঞান',
    'Social Work 1st Paper'                            => 'সমাজকর্ম ১ম পত্র',
    'Social Work 2nd Paper'                            => 'সমাজকর্ম ২য় পত্র',
    'Sociology 1st Paper'                              => 'সমাজবিজ্ঞান ১ম পত্র',
    'Sociology 2nd Paper'                              => 'সমাজবিজ্ঞান ২য় পত্র',
    'Statistics 1st Paper'                             => 'পরিসংখ্যান ১ম পত্র',
    'Statistics 2nd Paper'                             => 'পরিসংখ্যান ২য় পত্র',
];

$config['nctb_subjects'] = [
    'levels' => [

        // ---- Primary (Class 1-5) ----
        'primary' => [
            'name'    => 'Primary (Class 1-5)',
            'classes' => ['1', '2', '3', '4', '5'],
            'subjects' => [
                ['name' => 'Bangla (Primary)',                         'code' => '101', 'type' => 'Theory'],
                ['name' => 'English (Primary)',                        'code' => '102', 'type' => 'Theory'],
                ['name' => 'Mathematics (Primary)',                    'code' => '103', 'type' => 'Theory'],
                ['name' => 'Bangladesh and Global Studies (Primary)',  'code' => '150', 'type' => 'Theory'],
                ['name' => 'Primary Science',                          'code' => '154', 'type' => 'Theory'],
                ['name' => 'Islam and Moral Education (Primary)',      'code' => '111', 'type' => 'Optional'],
                ['name' => 'Hindu Religion and Moral Education (Primary)',     'code' => '112', 'type' => 'Optional'],
                ['name' => 'Buddhist Religion and Moral Education (Primary)',  'code' => '113', 'type' => 'Optional'],
                ['name' => 'Christian Religion and Moral Education (Primary)', 'code' => '114', 'type' => 'Optional'],
                ['name' => 'Arts and Crafts (Primary)',                'code' => '160', 'type' => 'Theory'],
                ['name' => 'Physical Education and Health (Primary)',  'code' => '147', 'type' => 'Practical'],
            ],
        ],

        // ---- Junior Secondary (Class 6-8, JSC) ----
        'jsc' => [
            'name'    => 'Junior Secondary (Class 6-8)',
            'classes' => ['6', '7', '8'],
            'subjects' => [
                ['name' => 'Bangla (JSC)',                              'code' => '101', 'type' => 'Theory'],
                ['name' => 'English (JSC)',                             'code' => '107', 'type' => 'Theory'],
                ['name' => 'Mathematics (JSC)',                         'code' => '109', 'type' => 'Theory'],
                ['name' => 'Science (JSC)',                             'code' => '127', 'type' => 'Theory'],
                ['name' => 'Bangladesh and Global Studies (JSC)',       'code' => '150', 'type' => 'Theory'],
                ['name' => 'Information and Communication Technology (JSC)', 'code' => '154', 'type' => 'Theory'],
                ['name' => 'Agriculture Studies (JSC)',                 'code' => '134', 'type' => 'Optional'],
                ['name' => 'Home Science (JSC)',                        'code' => '151', 'type' => 'Optional'],
                ['name' => 'Arabic (JSC)',                              'code' => '121', 'type' => 'Optional'],
                ['name' => 'Sanskrit (JSC)',                            'code' => '123', 'type' => 'Optional'],
                ['name' => 'Pali (JSC)',                                'code' => '124', 'type' => 'Optional'],
                ['name' => 'Islam and Moral Education (JSC)',           'code' => '111', 'type' => 'Optional'],
                ['name' => 'Hindu Religion and Moral Education (JSC)',  'code' => '112', 'type' => 'Optional'],
                ['name' => 'Buddhist Religion and Moral Education (JSC)',  'code' => '113', 'type' => 'Optional'],
                ['name' => 'Christian Religion and Moral Education (JSC)', 'code' => '114', 'type' => 'Optional'],
                ['name' => 'Career Education (JSC)',                    'code' => '156', 'type' => 'Theory'],
                ['name' => 'Physical Education and Health (JSC)',       'code' => '147', 'type' => 'Practical'],
                ['name' => 'Arts and Crafts (JSC)',                     'code' => '160', 'type' => 'Theory'],
            ],
        ],

        // ---- SSC Core (Class 9-10) ----
        'ssc_core' => [
            'name'    => 'SSC Core (Class 9-10)',
            'classes' => ['9', '10'],
            'subjects' => [
                ['name' => 'Bangla 1st Paper (SSC)',                    'code' => '101', 'type' => 'Theory'],
                ['name' => 'Bangla 2nd Paper (SSC)',                    'code' => '102', 'type' => 'Theory'],
                ['name' => 'English 1st Paper (SSC)',                   'code' => '107', 'type' => 'Theory'],
                ['name' => 'English 2nd Paper (SSC)',                   'code' => '108', 'type' => 'Theory'],
                ['name' => 'General Mathematics (SSC)',                 'code' => '109', 'type' => 'Theory'],
                ['name' => 'Information and Communication Technology (SSC)', 'code' => '154', 'type' => 'Theory'],
                ['name' => 'Career Education (SSC)',                    'code' => '156', 'type' => 'Theory'],
                ['name' => 'Physical Education and Health (SSC)',       'code' => '147', 'type' => 'Practical'],
                ['name' => 'Islam and Moral Education (SSC)',           'code' => '111', 'type' => 'Optional'],
                ['name' => 'Hindu Religion and Moral Education (SSC)',  'code' => '112', 'type' => 'Optional'],
                ['name' => 'Buddhist Religion and Moral Education (SSC)',  'code' => '113', 'type' => 'Optional'],
                ['name' => 'Christian Religion and Moral Education (SSC)', 'code' => '114', 'type' => 'Optional'],
            ],
        ],

        // ---- SSC Science stream (Class 9-10) ----
        'ssc_sci' => [
            'name'    => 'SSC Science (Class 9-10)',
            'classes' => ['9', '10'],
            'subjects' => [
                ['name' => 'Physics (SSC) — Theory',          'code' => '136', 'type' => 'Theory'],
                ['name' => 'Physics (SSC) — Practical',       'code' => '136', 'type' => 'Practical'],
                ['name' => 'Chemistry (SSC) — Theory',        'code' => '137', 'type' => 'Theory'],
                ['name' => 'Chemistry (SSC) — Practical',     'code' => '137', 'type' => 'Practical'],
                ['name' => 'Biology (SSC) — Theory',          'code' => '138', 'type' => 'Theory'],
                ['name' => 'Biology (SSC) — Practical',       'code' => '138', 'type' => 'Practical'],
                ['name' => 'Higher Mathematics (SSC) — Theory',    'code' => '126', 'type' => 'Theory'],
                ['name' => 'Higher Mathematics (SSC) — Practical', 'code' => '126', 'type' => 'Practical'],
                ['name' => 'Agriculture Studies (SSC)',       'code' => '134', 'type' => 'Optional'],
            ],
        ],

        // ---- SSC Business Studies stream (Class 9-10) ----
        'ssc_biz' => [
            'name'    => 'SSC Business Studies (Class 9-10)',
            'classes' => ['9', '10'],
            'subjects' => [
                ['name' => 'Accounting (SSC)',                'code' => '146', 'type' => 'Theory'],
                ['name' => 'Business Entrepreneurship (SSC)', 'code' => '143', 'type' => 'Theory'],
                ['name' => 'Finance and Banking (SSC)',       'code' => '152', 'type' => 'Theory'],
            ],
        ],

        // ---- SSC Humanities stream (Class 9-10) ----
        'ssc_hum' => [
            'name'    => 'SSC Humanities (Class 9-10)',
            'classes' => ['9', '10'],
            'subjects' => [
                ['name' => 'History of Bangladesh and World Civilization (SSC)', 'code' => '153', 'type' => 'Theory'],
                ['name' => 'Geography and Environment (SSC)', 'code' => '110', 'type' => 'Theory'],
                ['name' => 'Civics and Citizenship (SSC)',    'code' => '140', 'type' => 'Theory'],
                ['name' => 'Economics (SSC)',                 'code' => '141', 'type' => 'Theory'],
            ],
        ],

        // ---- HSC Core (Class 11-12) ----
        'hsc_core' => [
            'name'    => 'HSC Core (Class 11-12)',
            'classes' => ['11', '12'],
            'subjects' => [
                ['name' => 'Bangla 1st Paper (HSC)',                    'code' => '101', 'type' => 'Theory'],
                ['name' => 'Bangla 2nd Paper (HSC)',                    'code' => '102', 'type' => 'Theory'],
                ['name' => 'English 1st Paper (HSC)',                   'code' => '107', 'type' => 'Theory'],
                ['name' => 'English 2nd Paper (HSC)',                   'code' => '108', 'type' => 'Theory'],
                ['name' => 'Information and Communication Technology (HSC)', 'code' => '275', 'type' => 'Theory'],
                ['name' => 'Islam and Moral Education (HSC)',           'code' => '249', 'type' => 'Optional'],
                ['name' => 'Hindu Religion and Moral Education (HSC)',  'code' => '250', 'type' => 'Optional'],
                ['name' => 'Buddhist Religion and Moral Education (HSC)',  'code' => '251', 'type' => 'Optional'],
                ['name' => 'Christian Religion and Moral Education (HSC)', 'code' => '252', 'type' => 'Optional'],
            ],
        ],

        // ---- HSC Science stream (Class 11-12) ----
        'hsc_sci' => [
            'name'    => 'HSC Science (Class 11-12)',
            'classes' => ['11', '12'],
            'subjects' => [
                ['name' => 'Physics 1st Paper (HSC) — Theory',    'code' => '174', 'type' => 'Theory'],
                ['name' => 'Physics 1st Paper (HSC) — Practical', 'code' => '174', 'type' => 'Practical'],
                ['name' => 'Physics 2nd Paper (HSC) — Theory',    'code' => '175', 'type' => 'Theory'],
                ['name' => 'Physics 2nd Paper (HSC) — Practical', 'code' => '175', 'type' => 'Practical'],
                ['name' => 'Chemistry 1st Paper (HSC) — Theory',    'code' => '176', 'type' => 'Theory'],
                ['name' => 'Chemistry 1st Paper (HSC) — Practical', 'code' => '176', 'type' => 'Practical'],
                ['name' => 'Chemistry 2nd Paper (HSC) — Theory',    'code' => '177', 'type' => 'Theory'],
                ['name' => 'Chemistry 2nd Paper (HSC) — Practical', 'code' => '177', 'type' => 'Practical'],
                ['name' => 'Biology 1st Paper (HSC) — Theory',     'code' => '178', 'type' => 'Theory'],
                ['name' => 'Biology 1st Paper (HSC) — Practical',  'code' => '178', 'type' => 'Practical'],
                ['name' => 'Biology 2nd Paper (HSC) — Theory',     'code' => '179', 'type' => 'Theory'],
                ['name' => 'Biology 2nd Paper (HSC) — Practical',  'code' => '179', 'type' => 'Practical'],
                ['name' => 'Higher Mathematics 1st Paper (HSC) — Theory',    'code' => '265', 'type' => 'Theory'],
                ['name' => 'Higher Mathematics 1st Paper (HSC) — Practical', 'code' => '265', 'type' => 'Practical'],
                ['name' => 'Higher Mathematics 2nd Paper (HSC) — Theory',    'code' => '266', 'type' => 'Theory'],
                ['name' => 'Higher Mathematics 2nd Paper (HSC) — Practical', 'code' => '266', 'type' => 'Practical'],
                ['name' => 'Statistics 1st Paper (HSC)', 'code' => '129', 'type' => 'Theory'],
                ['name' => 'Statistics 2nd Paper (HSC)', 'code' => '130', 'type' => 'Theory'],
            ],
        ],

        // ---- HSC Business Studies stream (Class 11-12) ----
        'hsc_biz' => [
            'name'    => 'HSC Business Studies (Class 11-12)',
            'classes' => ['11', '12'],
            'subjects' => [
                ['name' => 'Accounting 1st Paper (HSC)',                       'code' => '253', 'type' => 'Theory'],
                ['name' => 'Accounting 2nd Paper (HSC)',                       'code' => '254', 'type' => 'Theory'],
                ['name' => 'Business Organization and Management 1st Paper (HSC)', 'code' => '277', 'type' => 'Theory'],
                ['name' => 'Business Organization and Management 2nd Paper (HSC)', 'code' => '278', 'type' => 'Theory'],
                ['name' => 'Finance, Banking and Insurance 1st Paper (HSC)',   'code' => '292', 'type' => 'Theory'],
                ['name' => 'Finance, Banking and Insurance 2nd Paper (HSC)',   'code' => '293', 'type' => 'Theory'],
                ['name' => 'Production Management and Marketing 1st Paper (HSC)', 'code' => '286', 'type' => 'Theory'],
                ['name' => 'Production Management and Marketing 2nd Paper (HSC)', 'code' => '287', 'type' => 'Theory'],
                ['name' => 'Economics 1st Paper (HSC)',                        'code' => '109', 'type' => 'Theory'],
                ['name' => 'Economics 2nd Paper (HSC)',                        'code' => '110', 'type' => 'Theory'],
            ],
        ],

        // ---- HSC Humanities stream (Class 11-12) ----
        'hsc_hum' => [
            'name'    => 'HSC Humanities (Class 11-12)',
            'classes' => ['11', '12'],
            'subjects' => [
                ['name' => 'History 1st Paper (HSC)',                          'code' => '304', 'type' => 'Theory'],
                ['name' => 'History 2nd Paper (HSC)',                          'code' => '305', 'type' => 'Theory'],
                ['name' => 'Islamic History and Culture 1st Paper (HSC)',      'code' => '267', 'type' => 'Theory'],
                ['name' => 'Islamic History and Culture 2nd Paper (HSC)',      'code' => '268', 'type' => 'Theory'],
                ['name' => 'Civics and Good Governance 1st Paper (HSC)',       'code' => '269', 'type' => 'Theory'],
                ['name' => 'Civics and Good Governance 2nd Paper (HSC)',       'code' => '270', 'type' => 'Theory'],
                ['name' => 'Geography 1st Paper (HSC)',                        'code' => '125', 'type' => 'Theory'],
                ['name' => 'Geography 2nd Paper (HSC)',                        'code' => '126', 'type' => 'Theory'],
                ['name' => 'Economics 1st Paper (HSC)',                        'code' => '109', 'type' => 'Theory'],
                ['name' => 'Economics 2nd Paper (HSC)',                        'code' => '110', 'type' => 'Theory'],
                ['name' => 'Sociology 1st Paper (HSC)',                        'code' => '117', 'type' => 'Theory'],
                ['name' => 'Sociology 2nd Paper (HSC)',                        'code' => '118', 'type' => 'Theory'],
                ['name' => 'Logic 1st Paper (HSC)',                            'code' => '121', 'type' => 'Theory'],
                ['name' => 'Logic 2nd Paper (HSC)',                            'code' => '122', 'type' => 'Theory'],
                ['name' => 'Psychology 1st Paper (HSC)',                       'code' => '123', 'type' => 'Theory'],
                ['name' => 'Psychology 2nd Paper (HSC)',                       'code' => '124', 'type' => 'Theory'],
                ['name' => 'Social Work 1st Paper (HSC)',                      'code' => '271', 'type' => 'Theory'],
                ['name' => 'Social Work 2nd Paper (HSC)',                      'code' => '272', 'type' => 'Theory'],
                ['name' => 'Bengali Literature (HSC)',                         'code' => '273', 'type' => 'Optional'],
                ['name' => 'English Literature (HSC)',                         'code' => '274', 'type' => 'Optional'],
            ],
        ],
    ],
];
