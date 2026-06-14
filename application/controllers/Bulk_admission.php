<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Quick Bulk Import
 *
 * One page (Admission -> Quick Bulk Import) that accepts a CSV, TXT
 * (tab-separated), or Excel .xlsx file and bulk-creates rows for the
 * selected entity (students, teachers, staff, subjects, or classes).
 *
 * Each entity has a minimal header set -- the same fields you'd type into
 * the matching Quick* form. Everything else is auto-populated with safe
 * defaults; the user can edit the rest later through the regular profile/edit
 * screen for that entity.
 *
 * Permission: reuses `multiple_import.is_add` so any role that can already
 * use the existing CSV student importer can use this page too.
 */
class Bulk_admission extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('student_model');
    }

    /* -----------------------------------------------------------------
     * Routes
     * --------------------------------------------------------------- */

    /** GET: render the form. POST: parse uploaded file and import rows. */
    public function index()
    {
        if (!get_permission('multiple_import', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        $report   = null;
        $entity   = $this->input->post('entity');
        $entityList = $this->_entity_list();

        if ($this->input->post('submit') === 'bulk_import') {
            $this->form_validation->set_rules('entity', translate('type'), 'trim|required');
            if (isset($_FILES['userfile']) && empty($_FILES['userfile']['name'])) {
                $this->form_validation->set_rules('userfile', 'File', 'required');
            }

            if ($this->form_validation->run() === true && isset($entityList[$entity])) {
                $report = $this->_run_import($entity, $_FILES['userfile'], $branchID);
                if (!empty($report['flash_success'])) {
                    set_alert('success', $report['flash_success']);
                }
                if (!empty($report['flash_error'])) {
                    set_alert('error', $report['flash_error']);
                }
            }
        }

        $this->data['title']        = translate('quick_bulk_import');
        $this->data['sub_page']     = 'bulk_admission/index';
        $this->data['main_menu']    = 'admission';
        $this->data['branch_id']    = $branchID;
        $this->data['entity_list']  = $entityList;
        $this->data['selected']     = $entity;
        $this->data['report']       = $report;
        $this->data['headerelements'] = array(
            'css' => array('vendor/dropify/css/dropify.min.css'),
            'js'  => array('vendor/dropify/js/dropify.min.js'),
        );
        $this->load->view('layout/index', $this->data);
    }

    /**
     * Download a sample file for the given entity. Format defaults to CSV.
     *
     *   /bulk_admission/sample/students         -> students-sample.csv
     *   /bulk_admission/sample/teachers/txt     -> teachers-sample.txt
     */
    public function sample($entity = '', $format = 'csv')
    {
        if (!get_permission('multiple_import', 'is_add')) {
            access_denied();
        }

        $entityList = $this->_entity_list();
        if (!isset($entityList[$entity])) {
            show_404();
        }
        $spec = $this->_get_spec($entity);
        if ($spec === null) {
            show_404();
        }

        $format    = ($format === 'txt') ? 'txt' : 'csv';
        $delimiter = ($format === 'txt') ? "\t" : ',';
        $filename  = $entity . '-sample.' . $format;

        $headers = $spec['headers'];
        $rows    = $spec['samples'];

        header('Content-Type: ' . ($format === 'txt' ? 'text/plain; charset=utf-8' : 'text/csv; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');

        $out = fopen('php://output', 'w');
        // BOM so Excel opens UTF-8 files cleanly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, $delimiter);
        foreach ($rows as $row) {
            fputcsv($out, $row, $delimiter);
        }
        fclose($out);
        exit;
    }

    /* -----------------------------------------------------------------
     * Internals: entity registry + spec
     * --------------------------------------------------------------- */

    private function _entity_list()
    {
        // Order shapes the dropdown in the view.
        return array(
            'students' => translate('students'),
            'teachers' => translate('teachers'),
            'staff'    => translate('staff'),
            'subjects' => translate('subjects'),
            'classes'  => translate('classes'),
        );
    }

    /**
     * Spec for an entity -- the columns we accept, plus 1-3 sample rows
     * embedded in the downloadable sample file.
     */
    private function _get_spec($entity)
    {
        switch ($entity) {
            case 'students':
                return array(
                    'headers' => array('class', 'section', 'roll', 'full_name'),
                    'samples' => array(
                        array('Class 1', 'A', '1', 'Ayesha Rahman'),
                        array('Class 1', 'A', '2', 'Md. Rakib Hasan'),
                        array('Class 1', 'B', '1', 'Sumaiya Akter'),
                    ),
                );
            case 'teachers':
                return array(
                    'headers' => array('full_name', 'email', 'mobile'),
                    'samples' => array(
                        array('Mahmuda Khatun', 'mahmuda@example.com', '01700000001'),
                        array('Md. Karim',      'karim@example.com',   '01700000002'),
                    ),
                );
            case 'staff':
                return array(
                    'headers' => array('full_name', 'email', 'mobile', 'role'),
                    'samples' => array(
                        array('Nadia Islam',  'nadia@example.com', '01711000001', 'Accountant'),
                        array('Hasan Mahmud', 'hasan@example.com', '01711000002', 'Librarian'),
                    ),
                );
            case 'subjects':
                return array(
                    'headers' => array('name', 'code'),
                    'samples' => array(
                        array('Bangla',  'BAN101'),
                        array('English', 'ENG101'),
                        array('Math',    ''),
                    ),
                );
            case 'classes':
                return array(
                    'headers' => array('name', 'name_numeric', 'sections'),
                    'samples' => array(
                        array('Class 6',  '6',  'A;B;C'),
                        array('Class 7',  '7',  'A;B'),
                        array('Play',     '0',  'A'),
                    ),
                );
        }
        return null;
    }

    /* -----------------------------------------------------------------
     * File parsing
     * --------------------------------------------------------------- */

    /** Returns array of associative rows keyed by header, or array() on parse failure. */
    private function _parse_upload($fileInfo, array $expectedHeaders)
    {
        if (empty($fileInfo['tmp_name']) || !is_file($fileInfo['tmp_name'])) {
            return array();
        }
        $name = strtolower($fileInfo['name']);
        $ext  = pathinfo($name, PATHINFO_EXTENSION);

        $matrix = array();
        if ($ext === 'xlsx') {
            $this->load->library('quick_xlsx_reader');
            $matrix = $this->quick_xlsx_reader->get_array($fileInfo['tmp_name']);
            if ($matrix === false) {
                return array();
            }
        } else {
            $delimiter = ($ext === 'txt' || $ext === 'tsv') ? "\t" : ',';
            $matrix = $this->_read_delimited_file($fileInfo['tmp_name'], $delimiter);
        }
        if (!is_array($matrix) || count($matrix) < 2) {
            return array();
        }

        // First row = header. Normalize to lowercase + trimmed.
        $headerRow = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $matrix[0]);

        // Verify every expected header is present (extra columns are ignored).
        foreach ($expectedHeaders as $h) {
            if (!in_array($h, $headerRow, true)) {
                return array();
            }
        }

        $rows = array();
        $count = count($matrix);
        for ($i = 1; $i < $count; $i++) {
            $cells = $matrix[$i];
            $assoc = array();
            $hasAny = false;
            foreach ($headerRow as $idx => $key) {
                if ($key === '') {
                    continue;
                }
                $val = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
                $assoc[$key] = $val;
                if ($val !== '') {
                    $hasAny = true;
                }
            }
            if ($hasAny) {
                $rows[] = $assoc;
            }
        }
        return $rows;
    }

    private function _read_delimited_file($filepath, $delimiter)
    {
        $matrix = array();
        $fh = fopen($filepath, 'r');
        if ($fh === false) {
            return $matrix;
        }
        // Strip UTF-8 BOM if present so the first header isn't garbled.
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            // fgetcsv returns array(NULL) for blank lines -- skip.
            if ($row === array(null)) {
                continue;
            }
            $matrix[] = $row;
        }
        fclose($fh);
        return $matrix;
    }

    /* -----------------------------------------------------------------
     * Importers
     * --------------------------------------------------------------- */

    private function _run_import($entity, $fileInfo, $branchID)
    {
        $spec = $this->_get_spec($entity);
        if ($spec === null) {
            return array(
                'flash_error' => translate('invalid_csv_file'),
            );
        }
        $rows = $this->_parse_upload($fileInfo, $spec['headers']);
        if (empty($rows)) {
            return array(
                'flash_error' => translate('invalid_csv_file'),
                'entity'      => $entity,
                'expected'    => $spec['headers'],
            );
        }

        $created = 0;
        $skipped = array();

        switch ($entity) {
            case 'students':
                $this->_import_students($rows, $branchID, $created, $skipped);
                break;
            case 'teachers':
                $this->_import_employees($rows, $branchID, 3 /* teacher */, $created, $skipped);
                break;
            case 'staff':
                $this->_import_employees($rows, $branchID, null /* role from row */, $created, $skipped);
                break;
            case 'subjects':
                $this->_import_subjects($rows, $branchID, $created, $skipped);
                break;
            case 'classes':
                $this->_import_classes($rows, $branchID, $created, $skipped);
                break;
        }

        $msg = sprintf(translate('rows_created_count_message'), $created, count($rows));

        return array(
            'entity'        => $entity,
            'total'         => count($rows),
            'created'       => $created,
            'skipped'       => $skipped,
            'flash_success' => ($created > 0) ? $msg : null,
            'flash_error'   => ($created === 0) ? translate('no_information_available') : null,
        );
    }

    /* ---------------- Students ---------------- */
    private function _import_students(array $rows, $branchID, &$created, array &$skipped)
    {
        $getBranch = $this->_get_branch_details($branchID);
        $sessionID = get_session_id();
        $classCache   = array();
        $sectionCache = array();

        foreach ($rows as $i => $row) {
            $rowNo = $i + 2; // +1 for header row, +1 because spreadsheets are 1-indexed
            $className   = isset($row['class'])     ? $row['class']     : '';
            $sectionName = isset($row['section'])   ? $row['section']   : '';
            $rollRaw     = isset($row['roll'])      ? $row['roll']      : '';
            $fullName    = isset($row['full_name']) ? $row['full_name'] : '';

            if ($className === '' || $sectionName === '' || $fullName === '') {
                $skipped[] = array('row' => $rowNo, 'name' => $fullName, 'reason' => translate('required'));
                continue;
            }

            $classID = $this->_lookup_class_id($className, $branchID, $classCache);
            if ($classID === 0) {
                $skipped[] = array('row' => $rowNo, 'name' => $fullName, 'reason' => translate('class') . ': ' . translate('not_found') . ' - ' . $className);
                continue;
            }
            $sectionID = $this->_lookup_section_id($sectionName, $classID, $branchID, $sectionCache);
            if ($sectionID === 0) {
                $skipped[] = array('row' => $rowNo, 'name' => $fullName, 'reason' => translate('section') . ': ' . translate('not_found') . ' - ' . $sectionName);
                continue;
            }

            $roll = ($rollRaw === '' ? 0 : (int) $rollRaw);
            // Honor the branch's `unique_roll` setting (0 = off, 1 = unique in
            // class, 2 = unique in section).
            $uniqueRoll = isset($getBranch['unique_roll']) ? (int) $getBranch['unique_roll'] : 0;
            if ($roll > 0 && $uniqueRoll > 0) {
                $check = $this->db->where(array(
                    'roll'       => $roll,
                    'class_id'   => $classID,
                    'branch_id'  => $branchID,
                    'session_id' => $sessionID,
                ));
                if ($uniqueRoll == 2) {
                    $check = $check->where('section_id', $sectionID);
                }
                $exists = $check->count_all_results('enroll');
                if ($exists > 0) {
                    $skipped[] = array('row' => $rowNo, 'name' => $fullName, 'reason' => translate('roll') . ' ' . $roll . ': ' . translate('already_taken'));
                    continue;
                }
            }

            $parts     = preg_split('/\s+/', $fullName, 2);
            $firstName = $parts[0];
            $lastName  = isset($parts[1]) ? $parts[1] : '';

            $registerNo = $this->student_model->regSerNumber($branchID);
            if (empty($registerNo)) {
                $maxRow     = $this->db->select('MAX(id) as id')->get('student')->row();
                $nextId     = (isset($maxRow->id) ? (int) $maxRow->id : 0) + 1;
                $registerNo = 'QA-' . $branchID . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            }

            $this->db->insert('student', array(
                'register_no'    => $registerNo,
                'admission_date' => date('Y-m-d'),
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'category_id'    => 0,
                'parent_id'      => 0,
                'route_id'       => 0,
                'vehicle_id'     => 0,
                'hostel_id'      => 0,
                'room_id'        => 0,
                'photo'          => 'defualt.png',
            ));
            $studentID = $this->db->insert_id();

            if (!empty($getBranch['stu_generate']) && $getBranch['stu_generate'] == 1) {
                $username = $getBranch['stu_username_prefix'] . $studentID;
                $password = $getBranch['stu_default_password'];
            } else {
                $username = 'stu' . $studentID;
                $password = $registerNo;
            }
            $this->db->insert('login_credential', array(
                'user_id'  => $studentID,
                'username' => $username,
                'role'     => 7,
                'password' => $this->app_lib->pass_hashed($password),
            ));

            $this->db->insert('enroll', array(
                'student_id' => $studentID,
                'class_id'   => $classID,
                'section_id' => $sectionID,
                'roll'       => $roll,
                'session_id' => $sessionID,
                'branch_id'  => $branchID,
            ));
            $created++;
        }
    }

    /* ---------------- Teachers / Staff ---------------- */
    private function _import_employees(array $rows, $branchID, $fixedRoleId, &$created, array &$skipped)
    {
        $roleCache = $this->_role_cache();

        foreach ($rows as $i => $row) {
            $rowNo    = $i + 2;
            $fullName = isset($row['full_name']) ? $row['full_name'] : '';
            $email    = isset($row['email'])     ? $row['email']     : '';
            $mobile   = isset($row['mobile'])    ? $row['mobile']    : '';
            $roleName = isset($row['role'])      ? $row['role']      : '';

            if ($fullName === '') {
                $skipped[] = array('row' => $rowNo, 'name' => $fullName, 'reason' => translate('required'));
                continue;
            }

            // Resolve role id.
            if ($fixedRoleId !== null) {
                $roleId = (int) $fixedRoleId;
            } else {
                $roleId = $this->_resolve_role_id($roleName, $roleCache);
                if ($roleId === 0) {
                    $skipped[] = array('row' => $rowNo, 'name' => $fullName, 'reason' => translate('role') . ': ' . translate('not_found') . ' - ' . $roleName);
                    continue;
                }
            }

            // Username -- derive from email local-part if present, otherwise
            // first_name + a short staff_id suffix added after insert.
            if ($email !== '') {
                $username = $this->_username_from_email($email);
            } else {
                $username = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $fullName));
                if ($username === '') {
                    $username = 'staff';
                }
            }
            $username = $this->_ensure_unique_username($username);

            // Default password is the username -- staff can change it from
            // their profile screen later.
            $password = $username;

            $staffID = substr(app_generate_hash(), 3, 7);
            $this->db->insert('staff', array(
                'branch_id'      => $branchID,
                'staff_id'       => $staffID,
                'name'           => $fullName,
                'email'          => $email,
                'mobileno'       => $mobile,
                'joining_date'   => date('Y-m-d'),
            ));
            $newID = $this->db->insert_id();

            $this->db->insert('login_credential', array(
                'user_id'  => $newID,
                'username' => $username,
                'role'     => $roleId,
                'password' => $this->app_lib->pass_hashed($password),
                'active'   => 1,
            ));
            $created++;
        }
    }

    /* ---------------- Subjects ---------------- */
    private function _import_subjects(array $rows, $branchID, &$created, array &$skipped)
    {
        foreach ($rows as $i => $row) {
            $rowNo = $i + 2;
            $name  = isset($row['name']) ? $row['name'] : '';
            $code  = isset($row['code']) ? $row['code'] : '';
            if ($name === '') {
                $skipped[] = array('row' => $rowNo, 'name' => $name, 'reason' => translate('required'));
                continue;
            }

            // Skip duplicates (same name + code in the branch).
            $exists = $this->db->where(array(
                'name'         => $name,
                'subject_code' => $code,
                'branch_id'    => $branchID,
            ))->count_all_results('subject');
            if ($exists > 0) {
                $skipped[] = array('row' => $rowNo, 'name' => $name, 'reason' => translate('already_taken'));
                continue;
            }

            $this->db->insert('subject', array(
                'name'         => $name,
                'subject_code' => $code,
                'subject_type' => 'Theory',
                'branch_id'    => $branchID,
            ));
            $created++;
        }
    }

    /* ---------------- Classes ---------------- */
    private function _import_classes(array $rows, $branchID, &$created, array &$skipped)
    {
        foreach ($rows as $i => $row) {
            $rowNo       = $i + 2;
            $name        = isset($row['name'])         ? $row['name']         : '';
            $nameNumeric = isset($row['name_numeric']) ? $row['name_numeric'] : '0';
            $sectionsCsv = isset($row['sections'])     ? $row['sections']     : '';

            if ($name === '') {
                $skipped[] = array('row' => $rowNo, 'name' => $name, 'reason' => translate('required'));
                continue;
            }

            // De-dupe per branch.
            $exists = $this->db->where(array(
                'name'      => $name,
                'branch_id' => $branchID,
            ))->count_all_results('class');
            if ($exists > 0) {
                $skipped[] = array('row' => $rowNo, 'name' => $name, 'reason' => translate('already_taken'));
                continue;
            }

            $this->db->insert('class', array(
                'name'         => $name,
                'name_numeric' => $nameNumeric,
                'branch_id'    => $branchID,
            ));
            $classID = $this->db->insert_id();

            // Sections list: ";"-separated to play nicely with CSV.
            $sectionNames = array_filter(array_map('trim', preg_split('/[;,]/', $sectionsCsv)));
            $sectionCache = array();
            foreach ($sectionNames as $sectionName) {
                if ($sectionName === '') {
                    continue;
                }
                $sectionID = $this->_lookup_section_id($sectionName, $classID, $branchID, $sectionCache, true);
                $assigned = $this->db->where(array(
                    'class_id'   => $classID,
                    'section_id' => $sectionID,
                ))->count_all_results('sections_allocation');
                if ($assigned === 0) {
                    $this->db->insert('sections_allocation', array(
                        'class_id'   => $classID,
                        'section_id' => $sectionID,
                    ));
                }
            }
            $created++;
        }
    }

    /* -----------------------------------------------------------------
     * Helpers
     * --------------------------------------------------------------- */

    private function _get_branch_details($branchID)
    {
        $row = $this->db->where('id', $branchID)->get('branch')->row_array();
        return is_array($row) ? $row : array();
    }

    private function _lookup_class_id($className, $branchID, array &$cache)
    {
        $key = strtolower(trim((string) $className));
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $row = $this->db->select('id')
            ->where('LOWER(name)', $key)
            ->where('branch_id', $branchID)
            ->get('class')
            ->row_array();
        $id = is_array($row) && isset($row['id']) ? (int) $row['id'] : 0;
        $cache[$key] = $id;
        return $id;
    }

    /**
     * Look up section by name within the given class.
     *
     * @param  bool $createIfMissing if true, create a new section row and
     *                                wire it into sections_allocation when
     *                                no match exists (used by the classes
     *                                importer).
     */
    private function _lookup_section_id($sectionName, $classID, $branchID, array &$cache, $createIfMissing = false)
    {
        $key = $classID . '::' . strtolower(trim((string) $sectionName));
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $row = $this->db->select('section.id')
            ->from('section')
            ->join('sections_allocation', 'sections_allocation.section_id = section.id', 'inner')
            ->where('sections_allocation.class_id', $classID)
            ->where('LOWER(section.name)', strtolower($sectionName))
            ->get()
            ->row_array();
        if (is_array($row) && isset($row['id'])) {
            $cache[$key] = (int) $row['id'];
            return $cache[$key];
        }
        if (!$createIfMissing) {
            $cache[$key] = 0;
            return 0;
        }
        // Re-use a same-named section in the branch if it exists; otherwise create one.
        $existing = $this->db->select('id')
            ->where('LOWER(name)', strtolower($sectionName))
            ->where('branch_id', $branchID)
            ->get('section')
            ->row_array();
        if (is_array($existing) && isset($existing['id'])) {
            $sectionID = (int) $existing['id'];
        } else {
            $this->db->insert('section', array(
                'name'      => $sectionName,
                'branch_id' => $branchID,
            ));
            $sectionID = (int) $this->db->insert_id();
        }
        $cache[$key] = $sectionID;
        return $sectionID;
    }

    /** Map role-name strings (case-insensitive) to role.id. */
    private function _role_cache()
    {
        $cache = array();
        $rows = $this->db->select('id,name')->get('roles')->result_array();
        foreach ($rows as $r) {
            $cache[strtolower(trim($r['name']))] = (int) $r['id'];
        }
        return $cache;
    }

    private function _resolve_role_id($roleName, array $cache)
    {
        $key = strtolower(trim((string) $roleName));
        if ($key === '') {
            return 0;
        }
        return isset($cache[$key]) ? $cache[$key] : 0;
    }

    private function _username_from_email($email)
    {
        $email = strtolower(trim((string) $email));
        $at    = strpos($email, '@');
        $local = ($at === false) ? $email : substr($email, 0, $at);
        $local = preg_replace('/[^a-z0-9._-]/', '', $local);
        return ($local === '') ? 'user' : $local;
    }

    private function _ensure_unique_username($base)
    {
        $candidate = $base;
        $suffix    = 1;
        while ($this->db->where('username', $candidate)->count_all_results('login_credential') > 0) {
            $suffix++;
            $candidate = $base . $suffix;
            if ($suffix > 9999) {
                // bail out with a high-entropy fallback rather than loop forever
                $candidate = $base . '-' . substr(app_generate_hash(), 0, 6);
                break;
            }
        }
        return $candidate;
    }
}
