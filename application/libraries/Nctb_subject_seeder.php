<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Nctb_subject_seeder — platform-wide subject catalogue manager.
 *
 * Architecture
 * ------------
 *   * Source of truth (long-term)  : `subject_catalog` DB table —
 *     a global (no branch_id) list managed by the super-admin via
 *     /saas/subject_catalog. CRUD lives there.
 *
 *   * Source of truth (bootstrap)  : application/config/nctb_subjects.php
 *     — the NCTB starter pack. Used to populate `subject_catalog` on
 *     first import. Also used as a runtime fallback so freshly
 *     approved tenants still get a subject list even if the
 *     super-admin hasn't visited /saas/subject_catalog yet.
 *
 *   * Per-tenant copies            : the existing branch-scoped
 *     `subject` table. Tenants get their initial rows copied from
 *     `subject_catalog` (or the PHP fallback) on approval; later they
 *     can pull updates idempotently via /subject/import_nctb.
 *
 * Dedupe key everywhere is (lower(name), code, type) so adding the
 * same row twice is a no-op.
 *
 * @author SmartSchool.bd
 */
class Nctb_subject_seeder
{
    /** @var CI_Controller */
    protected $CI;

    /** Cached "does the subject_catalog table exist?" check. */
    private $_catalogTableChecked = false;
    private $_catalogTableExists  = false;

    public function __construct($controller = null)
    {
        $this->CI = $controller ?: get_instance();
        // Load all `$config['nctb_*']` entries into the top-level config
        // namespace so `config->item('nctb_subjects')`,
        // `config->item('nctb_classes')`, `config->item('nctb_default_section')`
        // and `config->item('nctb_class_legacy_names')` all resolve.
        // (Passing TRUE here would namespace the items, which would
        // require every call site to pass the section name as a 2nd
        // argument — and we don't, so the lookups would silently
        // return NULL. That's the bug that made the import button
        // appear to do nothing.)
        $this->CI->config->load('nctb_subjects', false, true);
    }

    // -----------------------------------------------------------------
    // PHP-catalog accessors (used as bootstrap + fallback)
    // -----------------------------------------------------------------

    /** Return the raw PHP-catalogue (used by import preview UI). */
    public function getCatalog()
    {
        $cfg = $this->CI->config->item('nctb_subjects');
        if (!is_array($cfg) || empty($cfg['levels'])) {
            return ['levels' => []];
        }
        return $cfg;
    }

    /**
     * Flat list of every NCTB subject in the PHP catalogue, with level
     * metadata folded in.
     *
     * @return array<int, array{name:string, code:string, type:string, level_key:string, level_name:string, classes:array}>
     */
    public function flatList()
    {
        $out = [];
        foreach ($this->getCatalog()['levels'] as $levelKey => $level) {
            foreach (($level['subjects'] ?? []) as $row) {
                $out[] = [
                    'name'       => (string)($row['name'] ?? ''),
                    'code'       => (string)($row['code'] ?? ''),
                    'type'       => (string)($row['type'] ?? 'Theory'),
                    'level_key'  => $levelKey,
                    'level_name' => (string)($level['name'] ?? $levelKey),
                    'classes'    => (array)($level['classes'] ?? []),
                ];
            }
        }
        return $out;
    }

    /** Distinct level keys + labels from the PHP catalogue. */
    public function levelOptions()
    {
        $out = [];
        foreach ($this->getCatalog()['levels'] as $levelKey => $level) {
            $out[$levelKey] = (string)($level['name'] ?? $levelKey);
        }
        return $out;
    }

    // -----------------------------------------------------------------
    // subject_catalog DB table
    // -----------------------------------------------------------------

    /**
     * Best-effort: create the `subject_catalog` table if it's missing.
     * Returns TRUE if the table exists (now or already), FALSE if we
     * couldn't create it (e.g. DB user lacks CREATE TABLE).
     */
    public function ensureCatalogTable()
    {
        if ($this->_catalogTableChecked) return $this->_catalogTableExists;
        $this->_catalogTableChecked = true;

        $db = $this->CI->db;
        try {
            $q = @$db->query("SHOW TABLES LIKE 'subject_catalog'");
            if ($q && $q->num_rows() > 0) {
                $this->_catalogTableExists = true;
                return true;
            }
        } catch (\Throwable $e) {
            // fall through to create attempt
        }

        $sql = "CREATE TABLE IF NOT EXISTS `subject_catalog` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `subject_code` varchar(64) NOT NULL,
            `subject_type` varchar(64) NOT NULL DEFAULT 'Theory',
            `level_key` varchar(64) NOT NULL DEFAULT '',
            `level_name` varchar(128) NOT NULL DEFAULT '',
            `class_numerics` varchar(255) NOT NULL DEFAULT '',
            `subject_author` varchar(255) NOT NULL DEFAULT '',
            `notes` text NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `sort_order` int(11) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NULL,
            PRIMARY KEY (`id`),
            KEY `idx_level_key` (`level_key`),
            KEY `idx_is_active` (`is_active`),
            UNIQUE KEY `uq_catalog_name_code_type` (`name`, `subject_code`, `subject_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        try {
            @$db->query($sql);
            $q = @$db->query("SHOW TABLES LIKE 'subject_catalog'");
            $this->_catalogTableExists = ($q && $q->num_rows() > 0);
        } catch (\Throwable $e) {
            $this->_catalogTableExists = false;
        }
        return $this->_catalogTableExists;
    }

    /**
     * Return every row currently stored in `subject_catalog`. Falls
     * back to an empty array if the table is missing.
     *
     * Options:
     *   active_only — bool (default false). If true, returns only is_active=1 rows.
     *   level_key   — string filter
     *
     * @return array<int, array{id:int, name:string, subject_code:string, subject_type:string, level_key:string, level_name:string, class_numerics:string, subject_author:string, is_active:int}>
     */
    public function getCatalogRows(array $options = [])
    {
        if (!$this->ensureCatalogTable()) return [];

        $db = $this->CI->db;
        if (!empty($options['active_only'])) {
            $db->where('is_active', 1);
        }
        if (!empty($options['level_key'])) {
            $db->where('level_key', (string)$options['level_key']);
        }
        $db->order_by('sort_order', 'ASC');
        $db->order_by('level_key', 'ASC');
        $db->order_by('name', 'ASC');
        return $db->get('subject_catalog')->result_array();
    }

    /** Count rows currently in the catalogue. Returns 0 if table missing. */
    public function countCatalogRows()
    {
        if (!$this->ensureCatalogTable()) return 0;
        return (int)$this->CI->db->count_all('subject_catalog');
    }

    /**
     * Import the PHP NCTB catalogue into the `subject_catalog` table.
     * Idempotent: duplicates (by unique key name+code+type) are skipped.
     *
     * Options:
     *   author — default subject_author for newly inserted rows
     *   levels — optional whitelist of level keys
     *
     * @return array{inserted:int, skipped:int, levels:array}
     */
    public function importPhpCatalogToTable(array $options = [])
    {
        $result = ['inserted' => 0, 'skipped' => 0, 'levels' => []];
        if (!$this->ensureCatalogTable()) return $result;

        $db      = $this->CI->db;
        $author  = (string)($options['author'] ?? 'NCTB Bangladesh');
        $allowed = isset($options['levels']) && is_array($options['levels'])
            ? array_flip(array_map('strval', $options['levels']))
            : null;

        $existingRows = $db->select('name, subject_code, subject_type')
                           ->get('subject_catalog')->result_array();
        $existing = [];
        foreach ($existingRows as $r) {
            $existing[$this->_key($r['name'], $r['subject_code'], $r['subject_type'])] = true;
        }

        foreach ($this->getCatalog()['levels'] as $levelKey => $level) {
            if ($allowed !== null && !isset($allowed[$levelKey])) continue;
            $ins = 0; $skp = 0;
            foreach (($level['subjects'] ?? []) as $row) {
                $name = trim((string)($row['name'] ?? ''));
                $code = trim((string)($row['code'] ?? ''));
                $type = trim((string)($row['type'] ?? 'Theory'));
                if ($name === '' || $code === '') continue;

                $k = $this->_key($name, $code, $type);
                if (isset($existing[$k])) { $skp++; continue; }

                $db->insert('subject_catalog', [
                    'name'           => $name,
                    'subject_code'   => $code,
                    'subject_type'   => $type,
                    'level_key'      => (string)$levelKey,
                    'level_name'     => (string)($level['name'] ?? $levelKey),
                    'class_numerics' => implode(',', (array)($level['classes'] ?? [])),
                    'subject_author' => $author,
                    'notes'          => '',
                    'is_active'      => 1,
                    'sort_order'     => 0,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);
                $existing[$k] = true;
                $ins++;
                $result['inserted']++;
            }
            $result['skipped'] += $skp;
            $result['levels'][$levelKey] = [
                'name'     => (string)($level['name'] ?? $levelKey),
                'inserted' => $ins,
                'skipped'  => $skp,
            ];
        }
        return $result;
    }

    // -----------------------------------------------------------------
    // Per-tenant copies
    // -----------------------------------------------------------------

    /**
     * Insert every active catalogue subject that is not already present
     * for the given branch. Reads from `subject_catalog` when populated;
     * falls back to the PHP NCTB catalogue otherwise so fresh installs
     * still seed sensibly.
     *
     * Options:
     *   author    — string (default 'NCTB Bangladesh' or row's own author)
     *   levels    — optional whitelist of level keys (only honoured when
     *               falling back to PHP catalogue; catalogue rows are
     *               already curated)
     *
     * @return array{inserted:int, skipped:int, source:string, levels:array, subjects:array}
     */
    public function seedForBranch($branchId, array $options = [])
    {
        $branchId = (int)$branchId;
        $result = [
            'inserted' => 0,
            'skipped'  => 0,
            'source'   => 'php',
            'levels'   => [],
            'subjects' => [],
        ];
        if ($branchId <= 0) return $result;

        $db = $this->CI->db;
        if (!$db->table_exists('subject')) return $result;

        // Cache the branch's existing subjects once.
        $existing = [];
        foreach ($db->select('name, subject_code, subject_type')
                    ->where('branch_id', $branchId)
                    ->get('subject')
                    ->result_array() as $r) {
            $existing[$this->_key($r['name'], $r['subject_code'], $r['subject_type'])] = true;
        }

        // Prefer the super-admin-managed catalogue table.
        if ($this->ensureCatalogTable() && $this->countCatalogRows() > 0) {
            $result['source'] = 'catalog';
            $rows = $this->getCatalogRows(['active_only' => true]);
            foreach ($rows as $r) {
                $rawName  = (string)$r['name'];
                $insName  = $this->_canonicalSubjectName($rawName);
                if ($insName === '') $insName = $rawName;
                $key = $this->_key($rawName, $r['subject_code'], $r['subject_type']);
                $levelKey = (string)($r['level_key'] ?? '');
                if (!isset($result['levels'][$levelKey])) {
                    $result['levels'][$levelKey] = [
                        'name'     => (string)($r['level_name'] ?? $levelKey),
                        'inserted' => 0,
                        'skipped'  => 0,
                    ];
                }
                if (isset($existing[$key])) {
                    $result['skipped']++;
                    $result['levels'][$levelKey]['skipped']++;
                    continue;
                }
                $db->insert('subject', [
                    'name'           => $insName,
                    'subject_code'   => $r['subject_code'],
                    'subject_type'   => $r['subject_type'],
                    'subject_author' => (string)($r['subject_author'] ?? ($options['author'] ?? 'NCTB Bangladesh')),
                    'branch_id'      => $branchId,
                ]);
                $existing[$key] = true;
                $result['inserted']++;
                $result['levels'][$levelKey]['inserted']++;
                $result['subjects'][] = [
                    'name'      => $insName,
                    'code'      => $r['subject_code'],
                    'type'      => $r['subject_type'],
                    'level_key' => $levelKey,
                ];
            }
            return $result;
        }

        // Fallback — copy directly from the PHP catalogue.
        $author  = (string)($options['author'] ?? 'NCTB Bangladesh');
        $allowed = isset($options['levels']) && is_array($options['levels'])
            ? array_flip(array_map('strval', $options['levels']))
            : null;

        foreach ($this->getCatalog()['levels'] as $levelKey => $level) {
            if ($allowed !== null && !isset($allowed[$levelKey])) continue;
            $ins = 0; $skp = 0;

            foreach (($level['subjects'] ?? []) as $row) {
                $name = trim((string)($row['name'] ?? ''));
                $code = trim((string)($row['code'] ?? ''));
                $type = trim((string)($row['type'] ?? 'Theory'));
                if ($name === '' || $code === '') continue;

                $insName = $this->_canonicalSubjectName($name);
                if ($insName === '') $insName = $name;

                $key = $this->_key($name, $code, $type);
                if (isset($existing[$key])) { $skp++; continue; }

                $db->insert('subject', [
                    'name'           => $insName,
                    'subject_code'   => $code,
                    'subject_type'   => $type,
                    'subject_author' => $author,
                    'branch_id'      => $branchId,
                ]);
                $existing[$key] = true;
                $ins++;
                $result['subjects'][] = [
                    'name' => $insName, 'code' => $code, 'type' => $type, 'level_key' => $levelKey,
                ];
            }
            $result['inserted'] += $ins;
            $result['skipped']  += $skp;
            $result['levels'][$levelKey] = [
                'name'     => (string)($level['name'] ?? $levelKey),
                'inserted' => $ins,
                'skipped'  => $skp,
            ];
        }
        return $result;
    }

    /**
     * Run seedForBranch() across every active tenant branch. Useful when
     * the super-admin has just added new rows to `subject_catalog` and
     * wants them pushed to every existing subsite.
     *
     * @return array{branches:int, inserted:int, skipped:int, assigns:int}
     */
    public function pushCatalogToAllBranches()
    {
        $totals = ['branches' => 0, 'inserted' => 0, 'skipped' => 0, 'assigns' => 0];
        $db = $this->CI->db;
        if (!$db->table_exists('branch')) return $totals;

        $branches = $db->select('id')->where('status', 1)->get('branch')->result_array();
        foreach ($branches as $b) {
            $r = $this->seedEverythingForBranch((int)$b['id']);
            $totals['branches']++;
            $totals['inserted'] += (int)$r['subjects']['inserted'];
            $totals['skipped']  += (int)$r['subjects']['skipped'];
            $totals['assigns']  += (int)$r['assigns']['inserted'];
        }
        return $totals;
    }

    // -----------------------------------------------------------------
    // Classes + default section + subject_assign wiring
    // -----------------------------------------------------------------

    /**
     * Seed the canonical NCTB class roster (Play..Class 12) for a branch.
     * Idempotent — only inserts classes whose (branch_id, name_numeric)
     * pair isn't already present.
     *
     * Existing rows whose `name` exactly matches a known legacy NCTB
     * word (e.g. "One", "Class One", "Class I") are auto-renamed to the
     * current canonical name ("Class 1"), so tenants seeded by older
     * versions of the seeder get a clean upgrade. Custom names set by
     * the school admin (anything not in the legacy list) are left
     * alone — never overwritten.
     *
     * @return array{inserted:int, skipped:int, renamed:int}
     */
    public function seedClassesForBranch($branchId, $instituteType = null, $instituteSubtype = null)
    {
        $out = ['inserted' => 0, 'skipped' => 0, 'renamed' => 0];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;
        if (!$db->table_exists('class')) return $out;

        $roster = (array)$this->CI->config->item('nctb_classes');
        if (empty($roster)) return $out;
        $legacyMap = (array)$this->CI->config->item('nctb_class_legacy_names');

        // Filter roster to the classes that match the institute type.
        $rosterKey = $this->_resolveRosterKey($instituteType, $instituteSubtype);
        $allowedNumerics = null; // null = no filter (full roster)
        if ($rosterKey !== null) {
            $typeMap = (array)$this->CI->config->item('nctb_institute_type_classes');
            if (isset($typeMap[$rosterKey])) {
                $allowedNumerics = array_flip($typeMap[$rosterKey]);
            }
        }
        if ($allowedNumerics !== null) {
            $roster = array_filter($roster, function ($cls) use ($allowedNumerics) {
                return isset($allowedNumerics[(string)($cls['name_numeric'] ?? '')]);
            });
        }

        // Index existing rows by name_numeric (the stable join key).
        $existing = [];
        foreach ($db->select('id, name, name_numeric')
                    ->where('branch_id', $branchId)
                    ->get('class')->result_array() as $row) {
            $existing[(string)$row['name_numeric']] = $row;
        }

        foreach ($roster as $cls) {
            $numeric = (string)($cls['name_numeric'] ?? '');
            $name    = (string)($cls['name'] ?? '');
            if ($name === '' || $numeric === '') continue;

            if (isset($existing[$numeric])) {
                $currentName = (string)$existing[$numeric]['name'];
                // Auto-rename legacy NCTB words to the canonical name,
                // but never overwrite custom names the school has set.
                $legacy = isset($legacyMap[$numeric]) ? (array)$legacyMap[$numeric] : [];
                $isLegacy = false;
                foreach ($legacy as $candidate) {
                    if (strcasecmp(trim((string)$candidate), $currentName) === 0) {
                        $isLegacy = true;
                        break;
                    }
                }
                if ($isLegacy && $currentName !== $name) {
                    $db->where('id', (int)$existing[$numeric]['id'])
                       ->update('class', ['name' => $name, 'updated_at' => date('Y-m-d H:i:s')]);
                    $out['renamed']++;
                } else {
                    $out['skipped']++;
                }
                continue;
            }
            $db->insert('class', [
                'name'         => $name,
                'name_numeric' => $numeric,
                'branch_id'    => $branchId,
            ]);
            $out['inserted']++;
        }
        return $out;
    }

    /**
     * If the branch has no sections yet, insert the default Section A
     * so subject_assign rows have a valid section_id to FK to.
     *
     * @return array{inserted:int, section_id:int|null, total_sections:int}
     */
    public function seedDefaultSectionForBranch($branchId)
    {
        $out = ['inserted' => 0, 'section_id' => null, 'total_sections' => 0];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;
        if (!$db->table_exists('section')) return $out;

        $count = (int)$db->where('branch_id', $branchId)
                         ->count_all_results('section');

        if ($count === 0) {
            $def = (array)$this->CI->config->item('nctb_default_section');
            $row = [
                'name'      => (string)($def['name'] ?? 'A'),
                'capacity'  => (string)($def['capacity'] ?? ''),
                'branch_id' => $branchId,
            ];
            $db->insert('section', $row);
            $out['inserted']   = 1;
            $out['section_id'] = (int)$db->insert_id();
            $out['total_sections'] = 1;
        } else {
            $first = $db->select('id')->where('branch_id', $branchId)
                        ->order_by('id', 'ASC')->limit(1)
                        ->get('section')->row_array();
            $out['section_id'] = $first ? (int)$first['id'] : null;
            $out['total_sections'] = $count;
        }
        return $out;
    }

    /**
     * Wire every NCTB subject in the branch's `subject` table to every
     * matching class (by `class_numerics` from the catalogue) × every
     * section on the branch, for the supplied session.
     *
     * Idempotent — checks for existing subject_assign rows.
     *
     * @param int      $branchId
     * @param int|null $sessionId   defaults to global_settings.session_id
     * @return array{inserted:int, skipped:int, missing_classes:array}
     */
    public function seedSubjectAssignsForBranch($branchId, $sessionId = null)
    {
        $out = ['inserted' => 0, 'skipped' => 0, 'missing_classes' => []];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;

        $db = $this->CI->db;
        foreach (['subject', 'class', 'section', 'subject_assign'] as $t) {
            if (!$db->table_exists($t)) return $out;
        }

        // Resolve the active session id (global setting).
        if ($sessionId === null) {
            $sessionId = $this->_activeSessionId();
        }
        $sessionId = (int)$sessionId;
        if ($sessionId <= 0) return $out;

        // Build the catalogue lookup: dedupe-key → list of class_numerics.
        $catalogIndex = $this->_catalogClassMap();
        if (empty($catalogIndex)) return $out;

        // Branch's subjects, classes, sections.
        $subjects = $db->select('id, name, subject_code, subject_type')
                       ->where('branch_id', $branchId)
                       ->get('subject')->result_array();
        $classes  = $db->select('id, name_numeric')
                       ->where('branch_id', $branchId)
                       ->get('class')->result_array();
        $sections = $db->select('id')
                       ->where('branch_id', $branchId)
                       ->get('section')->result_array();
        if (empty($subjects) || empty($classes) || empty($sections)) return $out;

        $classByNumeric = [];
        foreach ($classes as $c) $classByNumeric[(string)$c['name_numeric']] = (int)$c['id'];
        $sectionIds = array_map(fn($s) => (int)$s['id'], $sections);

        // Existing subject_assign keys for this branch+session.
        $assigned = [];
        foreach ($db->select('class_id, section_id, subject_id')
                    ->where('branch_id', $branchId)
                    ->where('session_id', $sessionId)
                    ->get('subject_assign')->result_array() as $a) {
            $assigned[(int)$a['class_id'] . '|' . (int)$a['section_id'] . '|' . (int)$a['subject_id']] = true;
        }

        foreach ($subjects as $sub) {
            $key = $this->_key($sub['name'], $sub['subject_code'], $sub['subject_type']);
            if (!isset($catalogIndex[$key])) continue;

            foreach ($catalogIndex[$key] as $classNumeric) {
                $classNumeric = (string)$classNumeric;
                if (!isset($classByNumeric[$classNumeric])) {
                    $out['missing_classes'][$classNumeric] = true;
                    continue;
                }
                $classId = $classByNumeric[$classNumeric];
                foreach ($sectionIds as $sectionId) {
                    $assignKey = $classId . '|' . $sectionId . '|' . (int)$sub['id'];
                    if (isset($assigned[$assignKey])) {
                        $out['skipped']++;
                        continue;
                    }
                    $db->insert('subject_assign', [
                        'class_id'   => $classId,
                        'section_id' => $sectionId,
                        'subject_id' => (int)$sub['id'],
                        'teacher_id' => 0,
                        'branch_id'  => $branchId,
                        'session_id' => $sessionId,
                    ]);
                    $assigned[$assignKey] = true;
                    $out['inserted']++;
                }
            }
        }
        $out['missing_classes'] = array_keys($out['missing_classes']);
        return $out;
    }

    /**
     * Convenience wrapper: seed everything for a branch — classes,
     * default section, subjects, subject_assign, plus the exam result
     * essentials (grades, exam terms, mark distribution categories).
     * Used by the tenant approval flow, the auto-backfill on first
     * visit to /subject, and the "push to all tenants" admin action.
     *
     * @return array{classes:array, section:array, subjects:array, assigns:array, grades:array, exam_terms:array, mark_distributions:array}
     */
    public function seedEverythingForBranch($branchId, array $options = [])
    {
        // Best-effort: make sure the new columns exist before we try to
        // read them on a tenant that pre-dates this patch.
        $this->ensureInstituteTypeColumns();

        // Resolve institute type/subtype:
        // 1. Explicit `institute_type`/`institute_subtype` in $options wins.
        // 2. Else fall back to the columns on the `branch` row (when those
        //    columns exist — added by saas/Saas::approve()).
        // 3. Else fall back to NULL (= full roster, original behaviour).
        $type    = isset($options['institute_type'])    ? (string)$options['institute_type']    : null;
        $subtype = isset($options['institute_subtype']) ? (string)$options['institute_subtype'] : null;
        if (($type === null || $type === '') && (int)$branchId > 0) {
            $db = $this->CI->db;
            try {
                if ($db->table_exists('branch')) {
                    $sel = [];
                    if ($db->field_exists('institute_type', 'branch'))    $sel[] = 'institute_type';
                    if ($db->field_exists('institute_subtype', 'branch')) $sel[] = 'institute_subtype';
                    if (!empty($sel)) {
                        $row = $db->select(implode(',', $sel))
                                  ->where('id', (int)$branchId)
                                  ->get('branch')->row_array();
                        if (!empty($row)) {
                            if (!empty($row['institute_type']))    $type    = (string)$row['institute_type'];
                            if (!empty($row['institute_subtype'])) $subtype = (string)$row['institute_subtype'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'seedEverythingForBranch type lookup: ' . $e->getMessage());
            }
        }

        $classes    = $this->seedClassesForBranch($branchId, $type, $subtype);
        $section    = $this->seedDefaultSectionForBranch($branchId);
        $subjects   = $this->seedForBranch($branchId, $options);
        $assigns    = $this->seedSubjectAssignsForBranch($branchId);
        $exam       = $this->seedExamDefaultsForBranch($branchId, $type, $subtype);
        $exams      = $this->seedStarterExamsForBranch($branchId, $type, $subtype);
        $timetables = $this->seedStarterExamTimetableForBranch($branchId);
        $demo       = $this->seedDemoStudentsForBranch($branchId);
        return [
            'classes'            => $classes,
            'section'            => $section,
            'subjects'           => $subjects,
            'assigns'            => $assigns,
            'grades'             => $exam['grades'],
            'exam_terms'         => $exam['exam_terms'],
            'mark_distributions' => $exam['mark_distributions'],
            'exams'              => $exams,
            'timetable_exam'     => $timetables,
            'demo_students'      => $demo['students'],
            'demo_marks'         => $demo['marks'],
            'institute_type'     => $type,
            'institute_subtype'  => $subtype,
        ];
    }

    /**
     * Make sure `branch.institute_type`, `branch.institute_subtype` and
     * `saas_pending_request.institute_subtype` exist. The original schema
     * already ships `saas_pending_request.institute_type`; the rest are
     * added here on demand so the auto-setup feature works on existing
     * tenants without a separate migration.
     *
     * Idempotent — uses `field_exists()` so repeated calls are no-ops.
     */
    public function ensureInstituteTypeColumns()
    {
        $db = $this->CI->db;
        try {
            if ($db->table_exists('branch')) {
                if (!$db->field_exists('institute_type', 'branch')) {
                    @$db->query("ALTER TABLE `branch` ADD COLUMN `institute_type` VARCHAR(32) NULL");
                }
                if (!$db->field_exists('institute_subtype', 'branch')) {
                    @$db->query("ALTER TABLE `branch` ADD COLUMN `institute_subtype` VARCHAR(32) NULL");
                }
            }
            if ($db->table_exists('saas_pending_request')) {
                if (!$db->field_exists('institute_subtype', 'saas_pending_request')) {
                    @$db->query("ALTER TABLE `saas_pending_request` ADD COLUMN `institute_subtype` VARCHAR(32) NULL AFTER `institute_type`");
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'ensureInstituteTypeColumns: ' . $e->getMessage());
        }
    }

    /**
     * Translate `(institute_type, institute_subtype)` into the key used to
     * look things up in `nctb_institute_type_classes` and
     * `nctb_institute_type_exam_terms`.
     *
     *   ('school', 'primary')     => 'primary'
     *   ('school', 'high_school') => 'high_school'
     *   ('school', 'primary_high')=> 'primary_high'
     *   ('school', null|'')       => 'primary_high'  (most permissive)
     *   (<other>, *)              => <other>          (e.g. 'college')
     *   (null|'', *)              => null             (no filter)
     *
     * Returns NULL when the seeder should fall back to the full NCTB
     * roster — matches the pre-patch behaviour for legacy callers.
     */
    private function _resolveRosterKey($instituteType, $instituteSubtype)
    {
        $type    = strtolower(trim((string)$instituteType));
        $subtype = strtolower(trim((string)$instituteSubtype));
        if ($type === '' || $type === 'other') return null;
        if ($type === 'school') {
            $allowed = ['primary', 'high_school', 'primary_high'];
            if (in_array($subtype, $allowed, true)) return $subtype;
            return 'primary_high';
        }
        $known = [
            'primary'        => true,
            'high_school'    => true,
            'primary_high'   => true,
            'school_college' => true,
            'college'        => true,
            'madrasah'       => true,
            'technical'      => true,
            'kg'             => true,
            'university'     => true,
        ];
        if (isset($known[$type])) return $type;
        return null;
    }

    /**
     * Seed `timetable_exam` rows for every starter exam × every
     * subject_assign row on the branch. This wires the exam schedule
     * grid that the public /<school>/exam_results page joins against
     * (without at least one timetable_exam row, an exam never shows
     * up in the public exam dropdown — Home_model::getExamList does
     * an inner join on timetable_exam) and that the report card
     * picks per-subject mark_distribution from.
     *
     * Idempotent — dedupe key is (exam_id, class_id, section_id,
     * subject_id, session_id, branch_id). Admin can rewrite the
     * timetable from /timetable.
     *
     * Each row stores the exam's mark_distribution JSON, the exam
     * date defaults to today, and time_start / time_end / hall_id
     * are left blank so the schedule shows up but doesn't claim a
     * specific time slot.
     *
     * @return array{inserted:int, skipped:int}
     */
    public function seedStarterExamTimetableForBranch($branchId)
    {
        $out = ['inserted' => 0, 'skipped' => 0];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;
        foreach (['timetable_exam', 'exam', 'subject_assign', 'exam_mark_distribution'] as $t) {
            if (!$db->table_exists($t)) return $out;
        }

        $sessionId = $this->_activeSessionId();
        if ($sessionId <= 0) return $out;

        try {
            $exams = $db->select('id, mark_distribution')
                        ->where('branch_id', $branchId)
                        ->where('session_id', $sessionId)
                        ->get('exam')->result_array();
            if (empty($exams)) return $out;

            $assigns = $db->select('class_id, section_id, subject_id')
                          ->where('branch_id', $branchId)
                          ->where('session_id', $sessionId)
                          ->get('subject_assign')->result_array();
            if (empty($assigns)) return $out;

            $today = date('Y-m-d');
            foreach ($exams as $ex) {
                $examId = (int)$ex['id'];
                $md     = $this->_buildTimetableMarkDistribution($branchId, (string)$ex['mark_distribution']);
                foreach ($assigns as $sa) {
                    $exists = (int)$db->where([
                        'exam_id'    => $examId,
                        'class_id'   => (int)$sa['class_id'],
                        'section_id' => (int)$sa['section_id'],
                        'subject_id' => (int)$sa['subject_id'],
                        'session_id' => $sessionId,
                        'branch_id'  => $branchId,
                    ])->count_all_results('timetable_exam');
                    if ($exists > 0) { $out['skipped']++; continue; }
                    $db->insert('timetable_exam', [
                        'exam_id'           => $examId,
                        'class_id'          => (int)$sa['class_id'],
                        'section_id'        => (int)$sa['section_id'],
                        'subject_id'        => (int)$sa['subject_id'],
                        'time_start'        => '',
                        'time_end'          => '',
                        'mark_distribution' => $md,
                        'hall_id'           => 0,
                        'exam_date'         => $today,
                        'branch_id'         => $branchId,
                        'session_id'        => $sessionId,
                    ]);
                    $out['inserted']++;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'seedStarterExamTimetableForBranch: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Walk every `timetable_exam` row on the branch and rewrite rows
     * whose `mark_distribution` JSON is in the legacy flat id-list
     * shape (`["7","14"]` or `[45,46]`) — the shape the older seeder
     * used to copy straight out of `exam.mark_distribution`. That
     * shape works for the timetable schedule grid but breaks the
     * marks-register form, which expects
     * `{"7":{"full_mark":N,"pass_mark":M},"14":{...}}` and pulls
     * `max_mark_<id>` hidden inputs out of it. Without the nested
     * shape every save fails the `Exam::valid_Mark` callback with
     * "Invalid Marks" (empty max compared against a numeric value).
     *
     * Also flips `exam.publish_result = 1` and `status = 1` on any
     * auto-seeded starter exam (matched by canonical English / Bangla
     * name) that landed unpublished, so all three terms show up on
     * the public exam_results dropdown — not just First Term.
     *
     * Idempotent — already-nested timetable rows are left alone,
     * already-published exams aren't touched.
     *
     * @return array{repaired:int, skipped:int, published:int}
     */
    public function repairExamTimetableForBranch($branchId)
    {
        $out = ['repaired' => 0, 'skipped' => 0, 'published' => 0];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;
        foreach (['timetable_exam', 'exam'] as $t) {
            if (!$db->table_exists($t)) return $out;
        }

        try {
            $rows = $db->select('id, mark_distribution')
                       ->where('branch_id', $branchId)
                       ->get('timetable_exam')->result_array();
            foreach ($rows as $r) {
                $raw = (string)$r['mark_distribution'];
                if (!$this->_isLegacyFlatMarkDistribution($raw)) {
                    $out['skipped']++;
                    continue;
                }
                $fixed = $this->_buildTimetableMarkDistribution($branchId, $raw);
                if ($fixed === $raw) { $out['skipped']++; continue; }
                $db->where('id', (int)$r['id'])
                   ->update('timetable_exam', ['mark_distribution' => $fixed]);
                $out['repaired']++;
            }
        } catch (\Throwable $e) {
            log_message('error', 'repairExamTimetableForBranch timetable_exam: ' . $e->getMessage());
        }

        try {
            $autoNames = [];
            foreach ((array)$this->CI->config->item('nctb_starter_exams') as $ex) {
                $n = strtolower(trim((string)($ex['name'] ?? '')));
                if ($n !== '') $autoNames[$n] = true;
            }
            // Also match the Bangla translations live tenants ended up
            // with on first install.
            foreach (['প্রথম সাময়িক পরীক্ষা', 'অর্ধবার্ষিক পরীক্ষা', 'বার্ষিক পরীক্ষা'] as $bn) {
                $autoNames[strtolower($bn)] = true;
            }
            if (!empty($autoNames)) {
                $exams = $db->select('id, name, publish_result, status')
                            ->where('branch_id', $branchId)
                            ->get('exam')->result_array();
                foreach ($exams as $ex) {
                    if ((int)$ex['publish_result'] === 1 && (int)$ex['status'] === 1) continue;
                    $key = strtolower(trim((string)$ex['name']));
                    if (!isset($autoNames[$key])) continue;
                    $db->where('id', (int)$ex['id'])
                       ->update('exam', ['publish_result' => 1, 'status' => 1]);
                    $out['published']++;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'repairExamTimetableForBranch exam.publish_result: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Translate `exam.mark_distribution` (a flat JSON id list like
     * `["7","14"]`) into the nested JSON shape `timetable_exam` needs:
     *
     *   {"7":{"full_mark":100,"pass_mark":33},
     *    "14":{"full_mark":50,"pass_mark":17}}
     *
     * Defaults come from `$config['nctb_mark_distribution_defaults']`,
     * keyed by the distribution row's name (English or Bangla). If a
     * name isn't in the defaults map, falls back to full=100/pass=33
     * so the mark-entry form still has a working max.
     */
    private function _buildTimetableMarkDistribution($branchId, $rawJson)
    {
        $ids = json_decode((string)$rawJson, true);
        if (!is_array($ids) || empty($ids)) {
            return (string)$rawJson;
        }
        // Already-nested input: every value is itself an array with
        // a full_mark key. Leave it alone.
        $nested = true;
        foreach ($ids as $v) {
            if (!is_array($v) || !array_key_exists('full_mark', $v)) {
                $nested = false; break;
            }
        }
        if ($nested) return (string)$rawJson;

        $db = $this->CI->db;
        $defaults = (array)$this->CI->config->item('nctb_mark_distribution_defaults');
        $defaults = array_change_key_case($defaults, CASE_LOWER);

        $names = [];
        $intIds = array_values(array_unique(array_map('intval', $ids)));
        if (!empty($intIds)) {
            foreach ($db->select('id, name')
                        ->where_in('id', $intIds)
                        ->get('exam_mark_distribution')->result_array() as $r) {
                $names[(int)$r['id']] = (string)$r['name'];
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $intId = (int)$id;
            if ($intId <= 0) continue;
            $name = $names[$intId] ?? '';
            $key  = strtolower(trim($name));
            $def  = $defaults[$key] ?? ['full_mark' => 100, 'pass_mark' => 33];
            $out[(string)$intId] = [
                'full_mark' => (int)$def['full_mark'],
                'pass_mark' => (int)$def['pass_mark'],
            ];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * True when the JSON looks like the legacy flat id list
     * (`["7","14"]`) the old seeder used to write, i.e. NOT the
     * nested `{<id>:{full_mark:...}}` shape the marks-register form
     * needs. Used by `repairExamTimetableForBranch` to decide what
     * to rewrite.
     */
    private function _isLegacyFlatMarkDistribution($raw)
    {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded) || empty($decoded)) return false;
        foreach ($decoded as $v) {
            if (is_array($v) && array_key_exists('full_mark', $v)) {
                return false; // already nested
            }
        }
        return true;
    }

    /**
     * Seed 5 demo students + parents + Class-1 enrollments + First
     * Term exam marks onto a branch — enough for admins to immediately
     * see students, marks register, marksheet and ranking work
     * end-to-end without manually entering rows. Bangla names, fixed
     * IDs/rolls so re-runs are stable.
     *
     * Admins can delete any demo student via /student (cascades to
     * enroll/mark), or wipe every demo row in one click via the
     * "Remove all demo data" action on /student (Student::demo_clear).
     *
     * Idempotent — dedupe keys:
     *   parent  : (branch_id, mobileno = '0170DEMO<n>-<branch_id>')
     *   student : register_no = 'DEMO-<branch_id>-<n>'
     *   enroll  : (student_id, class_id, section_id, session_id, branch_id)
     *   mark    : (student_id, subject_id, exam_id)
     *
     * Wrapped in try/catch so any permission/schema mismatch never
     * breaks the larger seed flow.
     *
     * @return array{students:array{inserted:int,skipped:int}, marks:array{inserted:int,skipped:int}}
     */
    public function seedDemoStudentsForBranch($branchId)
    {
        $out = [
            'students' => ['inserted' => 0, 'skipped' => 0],
            'marks'    => ['inserted' => 0, 'skipped' => 0],
        ];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;
        foreach (['student', 'parent', 'enroll', 'mark', 'class', 'section', 'subject_assign', 'exam', 'exam_mark_distribution'] as $t) {
            if (!$db->table_exists($t)) return $out;
        }

        $sessionId = $this->_activeSessionId();
        if ($sessionId <= 0) return $out;

        $demo = $this->_demoStudentRoster();

        try {
            // ---- 1. parents ----
            foreach ($demo as $d) {
                $mobile = '0170DEMO' . str_pad((string)$d['n'], 2, '0', STR_PAD_LEFT) . '-' . $branchId;
                $exists = (int)$db->where(['branch_id' => $branchId, 'mobileno' => $mobile])
                                  ->count_all_results('parent');
                if ($exists > 0) continue;
                $db->insert('parent', [
                    'name'        => $d['father'],
                    'relation'    => 'Father',
                    'father_name' => $d['father'],
                    'mother_name' => $d['mother'],
                    'occupation'  => '',
                    'income'      => '',
                    'mobileno'    => $mobile,
                    'branch_id'   => $branchId,
                    'photo'       => 'defualt.png',
                    'active'      => 0,
                ]);
            }

            // ---- 2. students ----
            foreach ($demo as $d) {
                $registerNo = 'DEMO-' . $branchId . '-' . $d['n'];
                $mobile     = '0170DEMO' . str_pad((string)$d['n'], 2, '0', STR_PAD_LEFT) . '-' . $branchId;
                $exists = (int)$db->where('register_no', $registerNo)->count_all_results('student');
                if ($exists > 0) { $out['students']['skipped']++; continue; }
                $parent = $db->select('id')
                             ->where(['branch_id' => $branchId, 'mobileno' => $mobile])
                             ->get('parent')->row();
                if (!$parent) continue;
                $db->insert('student', [
                    'register_no'     => $registerNo,
                    'admission_date'  => date('Y-m-d'),
                    'first_name'      => $d['first'],
                    'last_name'       => $d['last'],
                    'gender'          => $d['gender'],
                    'birthday'        => '2014-0' . $d['n'] . '-15',
                    'religion'        => $d['religion'],
                    'parent_id'       => (int)$parent->id,
                    'photo'           => 'defualt.png',
                ]);
                $out['students']['inserted']++;
            }

            // ---- 3. enrollments (Class 1 / first available section) ----
            $class = $db->select('id')
                        ->where(['branch_id' => $branchId, 'name_numeric' => '1'])
                        ->get('class')->row();
            if (!$class) return $out;

            $section = $db->select('id, name')
                          ->where('branch_id', $branchId)
                          ->order_by('CASE WHEN LOWER(name) = "a" THEN 0 WHEN LOWER(name) LIKE "a%" THEN 1 ELSE 2 END, id', '', false)
                          ->limit(1)
                          ->get('section')->row();
            if (!$section) return $out;

            $studentIds = [];
            foreach ($demo as $d) {
                $stu = $db->select('id')
                          ->where('register_no', 'DEMO-' . $branchId . '-' . $d['n'])
                          ->get('student')->row();
                if (!$stu) continue;
                $studentIds[$d['n']] = (int)$stu->id;

                $exists = (int)$db->where([
                    'student_id' => (int)$stu->id,
                    'class_id'   => (int)$class->id,
                    'section_id' => (int)$section->id,
                    'session_id' => $sessionId,
                    'branch_id'  => $branchId,
                ])->count_all_results('enroll');
                if ($exists > 0) continue;
                $db->insert('enroll', [
                    'student_id'    => (int)$stu->id,
                    'class_id'      => (int)$class->id,
                    'section_id'    => (int)$section->id,
                    'roll'          => (int)$d['n'],
                    'session_id'    => $sessionId,
                    'default_login' => 0,
                    'branch_id'     => $branchId,
                    'is_alumni'     => 0,
                ]);
            }

            // ---- 4. marks (First Term exam, every Class 1 subject) ----
            $exam = $db->select('id')
                       ->where('branch_id', $branchId)
                       ->where('session_id', $sessionId)
                       ->group_start()
                       ->where('LOWER(name) =', strtolower('প্রথম সাময়িক পরীক্ষা'))
                       ->or_where('LOWER(name) =', strtolower('First Term Examination'))
                       ->group_end()
                       ->order_by('id', 'ASC')
                       ->limit(1)
                       ->get('exam')->row();
            if (!$exam) return $out;

            $theory = $db->select('id')
                         ->where('branch_id', $branchId)
                         ->group_start()
                         ->where('LOWER(name) =', strtolower('তত্ত্বীয়'))
                         ->or_where('LOWER(name) =', strtolower('Theory'))
                         ->group_end()
                         ->order_by('id', 'ASC')
                         ->limit(1)
                         ->get('exam_mark_distribution')->row();
            if (!$theory) return $out;

            $practical = $db->select('id')
                            ->where('branch_id', $branchId)
                            ->group_start()
                            ->where('LOWER(name) =', strtolower('ব্যবহারিক'))
                            ->or_where('LOWER(name) =', strtolower('Practical'))
                            ->group_end()
                            ->order_by('id', 'ASC')
                            ->limit(1)
                            ->get('exam_mark_distribution')->row();

            $assigns = $db->select('subject_id, class_id, section_id')
                          ->where('branch_id', $branchId)
                          ->where('class_id', (int)$class->id)
                          ->where('section_id', (int)$section->id)
                          ->where('session_id', $sessionId)
                          ->get('subject_assign')->result_array();

            foreach ($demo as $d) {
                if (!isset($studentIds[$d['n']])) continue;
                $stuId = $studentIds[$d['n']];
                foreach ($assigns as $sa) {
                    $exists = (int)$db->where([
                        'student_id' => $stuId,
                        'subject_id' => (int)$sa['subject_id'],
                        'exam_id'    => (int)$exam->id,
                    ])->count_all_results('mark');
                    if ($exists > 0) { $out['marks']['skipped']++; continue; }
                    $tScore = 60 + ((int)$d['n'] * 7 + (int)$sa['subject_id']) % 35;
                    $payload = [(int)$theory->id => (string)$tScore];
                    if ($practical) {
                        $pScore = 18 + ((int)$d['n'] * 5 + (int)$sa['subject_id']) % 12;
                        $payload[(int)$practical->id] = (string)$pScore;
                    }
                    $db->insert('mark', [
                        'student_id' => $stuId,
                        'subject_id' => (int)$sa['subject_id'],
                        'class_id'   => (int)$sa['class_id'],
                        'section_id' => (int)$sa['section_id'],
                        'exam_id'    => (int)$exam->id,
                        'mark'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
                        'absent'     => '',
                        'session_id' => $sessionId,
                        'branch_id'  => $branchId,
                    ]);
                    $out['marks']['inserted']++;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'seedDemoStudentsForBranch: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Sweep every demo row out of one branch — backs the "Remove all
     * demo data" admin button on /student. Touches only rows whose
     * student register_no starts with 'DEMO-<branch_id>-' or whose
     * parent mobileno starts with '0170DEMO…-<branch_id>', so non-demo
     * data is never affected.
     *
     * Cascade order: mark → enroll → student → parent.
     *
     * @return array{marks:int, enrolls:int, students:int, parents:int}
     */
    public function purgeDemoForBranch($branchId)
    {
        $out = ['marks' => 0, 'enrolls' => 0, 'students' => 0, 'parents' => 0];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;
        try {
            $studentIds = array_map(
                fn($r) => (int)$r['id'],
                $db->select('id')
                   ->like('register_no', 'DEMO-' . $branchId . '-', 'after')
                   ->get('student')->result_array()
            );
            if (!empty($studentIds)) {
                if ($db->table_exists('mark')) {
                    $db->where_in('student_id', $studentIds)->delete('mark');
                    $out['marks'] = (int)$db->affected_rows();
                }
                if ($db->table_exists('enroll')) {
                    $db->where_in('student_id', $studentIds)->delete('enroll');
                    $out['enrolls'] = (int)$db->affected_rows();
                }
                $db->where_in('id', $studentIds)->delete('student');
                $out['students'] = (int)$db->affected_rows();
            }
            if ($db->table_exists('parent')) {
                $db->where('branch_id', $branchId)
                   ->like('mobileno', '0170DEMO', 'after')
                   ->delete('parent');
                $out['parents'] = (int)$db->affected_rows();
            }
        } catch (\Throwable $e) {
            log_message('error', 'purgeDemoForBranch: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * Static demo roster used by both seedDemoStudentsForBranch and
     * tests. Kept here (not in config) so the dataset is locked.
     */
    private function _demoStudentRoster()
    {
        return [
            ['n'=>1, 'first'=>'মোঃ আব্দুল্লাহ', 'last'=>'রহমান',  'gender'=>'Male',   'religion'=>'ইসলাম',  'father'=>'মোঃ রফিকুল ইসলাম', 'mother'=>'রাহিমা বেগম'],
            ['n'=>2, 'first'=>'ফাতেমা',         'last'=>'খাতুন',    'gender'=>'Female', 'religion'=>'ইসলাম',  'father'=>'মোঃ আনোয়ার হোসেন', 'mother'=>'সাবিনা ইয়াসমিন'],
            ['n'=>3, 'first'=>'রিয়াদ',          'last'=>'হোসেন',    'gender'=>'Male',   'religion'=>'ইসলাম',  'father'=>'মোঃ সাইফুল ইসলাম',  'mother'=>'নুরজাহান বেগম'],
            ['n'=>4, 'first'=>'সুমাইয়া',        'last'=>'ইসলাম',    'gender'=>'Female', 'religion'=>'ইসলাম',  'father'=>'মোঃ কামরুল হাসান',  'mother'=>'রোকেয়া বেগম'],
            ['n'=>5, 'first'=>'অনিক',           'last'=>'দাশ',      'gender'=>'Male',   'religion'=>'হিন্দু',   'father'=>'প্রদীপ চন্দ্র দাশ', 'mother'=>'শোভা রানী দাশ'],
        ];
    }

    /**
     * Seed the exam result essentials onto a branch:
     *   * `grade` rows (NCTB Bangladesh GPA-5 scale: A+ down to F)
     *   * `exam_term` rows (First Term / Half-Yearly / Final) for the
     *     active session
     *   * `exam_mark_distribution` rows (Theory, Practical, Subjective,
     *     Objective, CT, MCQ)
     *
     * Idempotent — every insert is dedup'ed by (name, branch_id), with
     * an extra session_id check for exam_term. Admin can freely edit /
     * delete / add their own from /grades, /exam_term, /mark_distribution.
     *
     * Wrapped in try/catch so a permission error on any one table only
     * skips that table and never breaks the rest of the seed.
     *
     * @return array{grades:array{inserted:int,skipped:int}, exam_terms:array{inserted:int,skipped:int}, mark_distributions:array{inserted:int,skipped:int}}
     */
    public function seedExamDefaultsForBranch($branchId, $instituteType = null, $instituteSubtype = null)
    {
        $out = [
            'grades'             => ['inserted' => 0, 'skipped' => 0],
            'exam_terms'         => ['inserted' => 0, 'skipped' => 0],
            'mark_distributions' => ['inserted' => 0, 'skipped' => 0],
        ];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;
        $db = $this->CI->db;

        // ---- grade rows (GPA-5 scale) -----------------------------------
        if ($db->table_exists('grade')) {
            try {
                $existing = [];
                foreach ($db->select('LOWER(name) AS name')
                            ->where('branch_id', $branchId)
                            ->get('grade')->result_array() as $r) {
                    $existing[$r['name']] = true;
                }
                foreach ((array)$this->CI->config->item('nctb_grades') as $g) {
                    $name = (string)($g['name'] ?? '');
                    if ($name === '') continue;
                    if (isset($existing[strtolower($name)])) {
                        $out['grades']['skipped']++;
                        continue;
                    }
                    $db->insert('grade', [
                        'name'        => $name,
                        'grade_point' => (string)($g['grade_point'] ?? ''),
                        'lower_mark'  => (int)($g['lower_mark'] ?? 0),
                        'upper_mark'  => (int)($g['upper_mark'] ?? 0),
                        'remark'      => (string)($g['remark'] ?? ''),
                        'branch_id'   => $branchId,
                    ]);
                    $out['grades']['inserted']++;
                }
            } catch (\Throwable $e) {
                log_message('error', 'seedExamDefaultsForBranch grade: ' . $e->getMessage());
            }
        }

        // ---- exam_term rows --------------------------------------------
        if ($db->table_exists('exam_term')) {
            try {
                $sessionId = $this->_activeSessionId();
                if ($sessionId > 0) {
                    $existing = [];
                    foreach ($db->select('LOWER(name) AS name')
                                ->where('branch_id', $branchId)
                                ->where('session_id', $sessionId)
                                ->get('exam_term')->result_array() as $r) {
                        $existing[$r['name']] = true;
                    }
                    // Resolve type-specific exam terms, falling back to
                    // the generic nctb_exam_terms config.
                    $rk = $this->_resolveRosterKey($instituteType, $instituteSubtype);
                    $typeTerms = null;
                    if ($rk !== null) {
                        $ttMap = (array)$this->CI->config->item('nctb_institute_type_exam_terms');
                        if (!empty($ttMap[$rk])) {
                            $typeTerms = $ttMap[$rk];
                        }
                    }
                    if ($typeTerms === null) {
                        $typeTerms = (array)$this->CI->config->item('nctb_exam_terms');
                    }
                    foreach ($typeTerms as $term) {
                        $term = trim((string)$term);
                        if ($term === '') continue;
                        if (isset($existing[strtolower($term)])) {
                            $out['exam_terms']['skipped']++;
                            continue;
                        }
                        $db->insert('exam_term', [
                            'name'       => $term,
                            'branch_id'  => $branchId,
                            'session_id' => $sessionId,
                        ]);
                        $out['exam_terms']['inserted']++;
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'seedExamDefaultsForBranch exam_term: ' . $e->getMessage());
            }
        }

        // ---- exam_mark_distribution rows -------------------------------
        if ($db->table_exists('exam_mark_distribution')) {
            try {
                $existing = [];
                foreach ($db->select('LOWER(name) AS name')
                            ->where('branch_id', $branchId)
                            ->get('exam_mark_distribution')->result_array() as $r) {
                    $existing[$r['name']] = true;
                }
                foreach ((array)$this->CI->config->item('nctb_mark_distributions') as $md) {
                    $md = trim((string)$md);
                    if ($md === '') continue;
                    if (isset($existing[strtolower($md)])) {
                        $out['mark_distributions']['skipped']++;
                        continue;
                    }
                    $db->insert('exam_mark_distribution', [
                        'name'      => $md,
                        'branch_id' => $branchId,
                    ]);
                    $out['mark_distributions']['inserted']++;
                }
            } catch (\Throwable $e) {
                log_message('error', 'seedExamDefaultsForBranch exam_mark_distribution: ' . $e->getMessage());
            }
        }

        return $out;
    }

    /**
     * Seed starter `exam` rows onto a branch — one per default term
     * (First Term / Half-Yearly / Final) with sensible Theory+Practical
     * mark distribution slices. Anchored to the platform's active
     * session_id. Admin can edit / delete / add their own from /exam.
     *
     * Idempotent — keyed by (branch_id, session_id, lower(name)).
     * Wrapped in try/catch so a permission error never breaks the
     * larger seed flow. Requires the exam_term + exam_mark_distribution
     * rows to be present, so always call after seedExamDefaultsForBranch.
     *
     * @return array{inserted:int, skipped:int}
     */
    public function seedStarterExamsForBranch($branchId, $instituteType = null, $instituteSubtype = null)
    {
        $out = ['inserted' => 0, 'skipped' => 0];
        $branchId = (int)$branchId;
        if ($branchId <= 0) return $out;

        $db = $this->CI->db;
        foreach (['exam', 'exam_term', 'exam_mark_distribution'] as $t) {
            if (!$db->table_exists($t)) return $out;
        }

        try {
            $sessionId = $this->_activeSessionId();
            if ($sessionId <= 0) return $out;

            $terms = [];
            foreach ($db->select('id, name')
                        ->where('branch_id', $branchId)
                        ->where('session_id', $sessionId)
                        ->get('exam_term')->result_array() as $r) {
                $terms[strtolower(trim((string)$r['name']))] = (int)$r['id'];
            }

            $marks = [];
            foreach ($db->select('id, name')
                        ->where('branch_id', $branchId)
                        ->get('exam_mark_distribution')->result_array() as $r) {
                $marks[strtolower(trim((string)$r['name']))] = (int)$r['id'];
            }

            $existing = [];
            foreach ($db->select('LOWER(name) AS name')
                        ->where('branch_id', $branchId)
                        ->where('session_id', $sessionId)
                        ->get('exam')->result_array() as $r) {
                $existing[$r['name']] = true;
            }

            // Build starter exam list from type-specific terms if set,
            // otherwise fall back to the generic nctb_starter_exams config.
            $rk = $this->_resolveRosterKey($instituteType, $instituteSubtype);
            $starterExams = null;
            if ($rk !== null) {
                $ttMap = (array)$this->CI->config->item('nctb_institute_type_exam_terms');
                if (!empty($ttMap[$rk])) {
                    $starterExams = [];
                    foreach ($ttMap[$rk] as $termName) {
                        $starterExams[] = [
                            'name'              => $termName . ' পরীক্ষা',
                            'term_name'         => $termName,
                            'type_id'           => 3,
                            'mark_distribution' => ['Theory', 'Practical'],
                            'remark'            => 'স্বয়ংক্রিয় সেটআপ — সম্পাদনা বা প্রতিস্থাপন করুন।',
                            'publish'           => 1,
                        ];
                    }
                }
            }
            if ($starterExams === null) {
                $starterExams = (array)$this->CI->config->item('nctb_starter_exams');
            }

            foreach ($starterExams as $ex) {
                $name = (string)($ex['name'] ?? '');
                if ($name === '') continue;
                if (isset($existing[strtolower($name)])) {
                    $out['skipped']++;
                    continue;
                }

                $termId = $this->_resolveTermId($terms, (string)($ex['term_name'] ?? ''));
                if ($termId <= 0) {
                    $out['skipped']++;
                    continue;
                }

                $mdIds = [];
                foreach ((array)($ex['mark_distribution'] ?? []) as $mdName) {
                    $key = strtolower(trim((string)$mdName));
                    if (isset($marks[$key])) $mdIds[] = $marks[$key];
                    elseif (isset($marks[$this->_bnAlias($key)])) $mdIds[] = $marks[$this->_bnAlias($key)];
                }
                if (empty($mdIds)) {
                    $out['skipped']++;
                    continue;
                }

                $db->insert('exam', [
                    'name'              => $name,
                    'term_id'           => $termId,
                    'type_id'           => (int)($ex['type_id'] ?? 3),
                    'mark_distribution' => json_encode($mdIds),
                    'remark'            => (string)($ex['remark'] ?? ''),
                    'session_id'        => $sessionId,
                    'branch_id'         => $branchId,
                    'status'            => (int)($ex['publish'] ?? 1),
                    'publish_result'    => 1,
                    'rank_generated'    => 0,
                ]);
                $existing[strtolower($name)] = true;
                $out['inserted']++;
            }
        } catch (\Throwable $e) {
            log_message('error', 'seedStarterExamsForBranch: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Resolve an English term name ("First Term") to its id, tolerating
     * Bangla equivalents (প্রথম সাময়িক / অর্ধবার্ষিক / বার্ষিক).
     */
    private function _resolveTermId(array $terms, $name)
    {
        $key = strtolower(trim((string)$name));
        if ($key === '' ) return 0;
        if (isset($terms[$key])) return (int)$terms[$key];

        $aliases = [
            'first term'  => ['প্রথম সাময়িক', 'first term examination', '১ম সাময়িক'],
            'half-yearly' => ['অর্ধবার্ষিক', 'half yearly', '২য় সাময়িক'],
            'final'       => ['বার্ষিক', 'annual', '৩য় সাময়িক'],
        ];
        foreach ($aliases[$key] ?? [] as $alt) {
            $k = strtolower(trim($alt));
            if (isset($terms[$k])) return (int)$terms[$k];
        }

        // Reverse direction — caller passed a Bangla name.
        foreach ($aliases as $eng => $list) {
            if (in_array($key, array_map('strtolower', $list), true)) {
                if (isset($terms[$eng])) return (int)$terms[$eng];
                foreach ($list as $alt) {
                    $k = strtolower($alt);
                    if (isset($terms[$k])) return (int)$terms[$k];
                }
            }
        }
        return 0;
    }

    /**
     * Map an English mark-distribution slug ("theory", "practical") to
     * its Bangla equivalent that the seeder may have created instead.
     * Used when resolving mark_distribution name → id.
     */
    private function _bnAlias($englishLower)
    {
        static $map = [
            'theory'     => 'তত্ত্বীয়',
            'practical'  => 'ব্যবহারিক',
            'subjective' => 'রচনামূলক',
            'objective'  => 'বহুনির্বাচনি',
            'ct'         => 'সিটি',
            'mcq'        => 'এমসিকিউ',
        ];
        return $map[$englishLower] ?? $englishLower;
    }

    /**
     * Hook for Sections::save() — when a new section is created,
     * mirror every existing branch subject onto the new section so
     * the freshly-added section inherits the catalogue automatically.
     *
     * @return array{inserted:int, skipped:int}
     */
    public function mirrorSubjectsOntoNewSection($branchId, $sectionId, $sessionId = null)
    {
        $out = ['inserted' => 0, 'skipped' => 0];
        $branchId  = (int)$branchId;
        $sectionId = (int)$sectionId;
        if ($branchId <= 0 || $sectionId <= 0) return $out;

        $db = $this->CI->db;
        foreach (['subject', 'class', 'subject_assign'] as $t) {
            if (!$db->table_exists($t)) return $out;
        }

        if ($sessionId === null) $sessionId = $this->_activeSessionId();
        $sessionId = (int)$sessionId;
        if ($sessionId <= 0) return $out;

        $catalogIndex = $this->_catalogClassMap();
        if (empty($catalogIndex)) return $out;

        $subjects = $db->select('id, name, subject_code, subject_type')
                       ->where('branch_id', $branchId)
                       ->get('subject')->result_array();
        $classes  = $db->select('id, name_numeric')
                       ->where('branch_id', $branchId)
                       ->get('class')->result_array();
        if (empty($subjects) || empty($classes)) return $out;

        $classByNumeric = [];
        foreach ($classes as $c) $classByNumeric[(string)$c['name_numeric']] = (int)$c['id'];

        // Existing assigns for this branch+session+section.
        $assigned = [];
        foreach ($db->select('class_id, subject_id')
                    ->where('branch_id', $branchId)
                    ->where('section_id', $sectionId)
                    ->where('session_id', $sessionId)
                    ->get('subject_assign')->result_array() as $a) {
            $assigned[(int)$a['class_id'] . '|' . (int)$a['subject_id']] = true;
        }

        foreach ($subjects as $sub) {
            $key = $this->_key($sub['name'], $sub['subject_code'], $sub['subject_type']);
            if (!isset($catalogIndex[$key])) continue;
            foreach ($catalogIndex[$key] as $classNumeric) {
                if (!isset($classByNumeric[(string)$classNumeric])) continue;
                $classId = $classByNumeric[(string)$classNumeric];
                $assignKey = $classId . '|' . (int)$sub['id'];
                if (isset($assigned[$assignKey])) { $out['skipped']++; continue; }
                $db->insert('subject_assign', [
                    'class_id'   => $classId,
                    'section_id' => $sectionId,
                    'subject_id' => (int)$sub['id'],
                    'teacher_id' => 0,
                    'branch_id'  => $branchId,
                    'session_id' => $sessionId,
                ]);
                $assigned[$assignKey] = true;
                $out['inserted']++;
            }
        }
        return $out;
    }

    /**
     * Resolve the active session id from the catalogue table (preferred,
     * if a saas-wide override exists) or `global_settings.session_id`
     * (the legacy active-school-year picker).
     */
    private function _activeSessionId()
    {
        $db = $this->CI->db;
        try {
            if ($db->table_exists('global_settings')) {
                $row = $db->select('session_id')->where('id', 1)->get('global_settings')->row_array();
                if (!empty($row['session_id'])) return (int)$row['session_id'];
            }
        } catch (\Throwable $e) { /* fall through */ }

        // Fallback — newest schoolyear row.
        try {
            if ($db->table_exists('schoolyear')) {
                $row = $db->select('id')->order_by('id', 'DESC')->limit(1)
                          ->get('schoolyear')->row_array();
                if (!empty($row['id'])) return (int)$row['id'];
            }
        } catch (\Throwable $e) { /* fall through */ }

        return 0;
    }

    /**
     * Build `dedupe_key → array<class_numeric>` from the active source
     * (catalogue table preferred, PHP catalogue fallback). The dedupe
     * key is (lower(name), code, type), same key the seeder uses to
     * detect existing rows.
     *
     * @return array<string, array<int, string>>
     */
    private function _catalogClassMap()
    {
        $map = [];

        $merge = function (&$map, $key, array $classes) {
            if (empty($classes)) return;
            if (!isset($map[$key])) { $map[$key] = []; }
            foreach ($classes as $c) {
                $c = (string)$c;
                if ($c === '') continue;
                if (!in_array($c, $map[$key], true)) $map[$key][] = $c;
            }
        };

        if ($this->ensureCatalogTable() && $this->countCatalogRows() > 0) {
            foreach ($this->getCatalogRows(['active_only' => true]) as $r) {
                $key = $this->_key($r['name'], $r['subject_code'], $r['subject_type']);
                $classes = array_map('trim', explode(',', (string)($r['class_numerics'] ?? '')));
                $classes = array_values(array_filter($classes, function ($v) { return $v !== ''; }));
                $merge($map, $key, $classes);
            }
            if (!empty($map)) return $map;
        }

        // PHP fallback. The same subject is intentionally repeated
        // across multiple `levels` (Primary, JSC, …) and we want the
        // UNION of every level's class list, not just the last seen.
        foreach ($this->getCatalog()['levels'] as $level) {
            $classes = array_map('strval', (array)($level['classes'] ?? []));
            foreach (($level['subjects'] ?? []) as $sub) {
                $key = $this->_key($sub['name'] ?? '', $sub['code'] ?? '', $sub['type'] ?? 'Theory');
                $merge($map, $key, $classes);
            }
        }
        return $map;
    }

    /**
     * Build the dedupe key used to detect already-present subjects.
     *
     * The same NCTB subject often appears on a branch in multiple forms:
     * "Bangla (Primary)", "Bangla (JSC)", "বাংলা (প্রাথমিক)", "বাংলা (জেএসসি)"
     * — but conceptually these are all the same subject "বাংলা" assigned
     * to different classes (1–8). To collapse them into a single row,
     * the key is built from a canonical (Bangla, level-qualifier-free)
     * form of the name plus the subject type. The numeric `code` is
     * NOT part of the key because Bangladesh's NCTB overloads codes
     * across levels (e.g. code 109 means "Mathematics" at JSC and
     * "Economics 1st Paper" at HSC) — keying by code would collide.
     *
     * The 1st/2nd-Paper distinction is preserved because the canonical
     * name already includes it ("বাংলা ১ম পত্র" vs "বাংলা ২য় পত্র").
     */
    private function _key($name, $code, $type)
    {
        $type      = trim((string)$type);
        $canonical = $this->_canonicalSubjectName((string)$name);
        if ($canonical === '') $canonical = trim((string)$name);
        $canonical = function_exists('mb_strtolower')
            ? mb_strtolower($canonical, 'UTF-8')
            : strtolower($canonical);
        return $canonical . '|' . $type;
    }

    /**
     * Strip the level/board parenthetical qualifier from a subject
     * name and translate the (now bare) English base to its canonical
     * Bangla equivalent.
     *
     * Examples:
     *   "Bangla (Primary)"              → "বাংলা"
     *   "Bangla 1st Paper (HSC)"        → "বাংলা ১ম পত্র"
     *   "বাংলা (প্রাথমিক)"               → "বাংলা"
     *   "Mathematics — মানবিক"          → "গণিত"
     *   "Custom Local Subject"          → "Custom Local Subject"   (returned as-is)
     */
    public function _canonicalSubjectName($name)
    {
        $n = trim((string)$name);
        if ($n === '') return '';

        // Drop "(...)" parenthetical level/board/section qualifiers.
        $n = preg_replace('/\s*\([^)]*\)/u', '', $n);
        // Drop trailing "— X" / "– X" / "- X" qualifier (stream, practical, etc.)
        $n = preg_replace('/\s*[—–][^—–]*$/u', '', $n);
        $n = trim(preg_replace('/\s+/u', ' ', $n));
        if ($n === '') return '';

        $map = (array)$this->CI->config->item('nctb_subject_canonical_names');
        if (isset($map[$n])) return $map[$n];

        // Case-insensitive fallback so user-typed "bangla 1st paper"
        // resolves the same as the catalogue-cased "Bangla 1st Paper".
        $low = function_exists('mb_strtolower') ? mb_strtolower($n, 'UTF-8') : strtolower($n);
        foreach ($map as $en => $bn) {
            $lowEn = function_exists('mb_strtolower') ? mb_strtolower($en, 'UTF-8') : strtolower($en);
            if ($lowEn === $low) return $bn;
        }

        // No translation entry. If the name is already Bangla, return as-is.
        // If still English, leave the cleaned form so it at least loses
        // the "(Primary)" / "(SSC)" trailer.
        return $n;
    }

    /**
     * Detect and merge duplicate subjects on a branch caused by the
     * Bangla/English co-existence bug. For each (code, type, level,
     * paper) cluster of >1 rows, the row with the most subject_assign
     * rows wins (tie-break: longest name) and the others are merged
     * into it (subject_assign + subject_timetable + mark_distribution +
     * timetable_exam rows re-linked, then the dupes deleted).
     *
     * Returns:
     *   ['clusters' => int, 'rows_merged' => int, 'reassigned' => int]
     */
    public function dedupeBranchSubjects($branchId)
    {
        $branchId = (int)$branchId;
        $result = ['clusters' => 0, 'rows_merged' => 0, 'reassigned' => 0];
        if ($branchId <= 0) return $result;
        $db = $this->CI->db;
        if (!$db->table_exists('subject')) return $result;

        $rows = $db->select('id, name, subject_code, subject_type')
                   ->where('branch_id', $branchId)
                   ->get('subject')->result_array();

        // Group by fingerprint key
        $groups = [];
        foreach ($rows as $r) {
            $key = $this->_key($r['name'], $r['subject_code'], $r['subject_type']);
            $groups[$key][] = $r;
        }

        foreach ($groups as $key => $list) {
            if (count($list) < 2) continue;
            $result['clusters']++;

            // Pick the winner: most subject_assign rows; tie-break = longest name (favours Bangla which is often longer).
            $best   = null;
            $bestN  = -1;
            $bestLen= -1;
            foreach ($list as $r) {
                $cnt = $db->table_exists('subject_assign')
                    ? (int)$db->where('subject_id', (int)$r['id'])->count_all_results('subject_assign')
                    : 0;
                $len = function_exists('mb_strlen') ? mb_strlen($r['name'], 'UTF-8') : strlen($r['name']);
                if ($cnt > $bestN || ($cnt === $bestN && $len > $bestLen)) {
                    $best    = $r;
                    $bestN   = $cnt;
                    $bestLen = $len;
                }
            }
            if (!$best) continue;
            $keepId = (int)$best['id'];

            foreach ($list as $r) {
                if ((int)$r['id'] === $keepId) continue;
                $dupId = (int)$r['id'];

                // Re-link dependent rows to the kept id.
                foreach (['subject_assign','subject_timetable','mark_distribution','timetable_exam'] as $t) {
                    if (!$db->table_exists($t)) continue;
                    if (!$db->field_exists('subject_id', $t)) continue;
                    $db->where('subject_id', $dupId)->update($t, ['subject_id' => $keepId]);
                    $result['reassigned'] += (int)$db->affected_rows();
                }

                $db->where('id', $dupId)->delete('subject');
                $result['rows_merged']++;
            }

            // Rename the kept row to its canonical Bangla form (drops
            // any "(Primary)" / "(এইচএসসি)" trailer, translates English).
            $canonical = $this->_canonicalSubjectName($best['name']);
            if ($canonical !== '' && $canonical !== $best['name']) {
                $db->where('id', $keepId)->update('subject', ['name' => $canonical]);
            }
        }

        // After re-linking, subject_assign may now have exact duplicates
        // (two rows with same class_id/section_id/subject_id/session_id).
        // Collapse them.
        if ($db->table_exists('subject_assign')) {
            $dupCheck = $db->query(
                "SELECT MIN(id) AS keep_id, GROUP_CONCAT(id) AS ids, COUNT(*) AS cnt
                 FROM subject_assign
                 WHERE branch_id = ?
                 GROUP BY class_id, section_id, subject_id, IFNULL(session_id,0)
                 HAVING COUNT(*) > 1",
                [$branchId]
            )->result_array();
            foreach ($dupCheck as $dc) {
                $allIds = array_map('intval', explode(',', (string)$dc['ids']));
                $keep   = (int)$dc['keep_id'];
                foreach ($allIds as $aid) {
                    if ($aid === $keep) continue;
                    $db->where('id', $aid)->delete('subject_assign');
                }
            }
        }

        return $result;
    }
}
