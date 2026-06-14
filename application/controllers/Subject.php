<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 6.5
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Subject.php
 * @copyright : Reserved RamomCoder Team
 */

class Subject extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('subject_model');
    }

    public function index()
    {
        if (!get_permission('subject', 'is_view')) {
            access_denied();
        }

        // Self-healing auto-backfill: if this branch is brand new
        // (zero subjects) OR was provisioned before the NCTB seeder
        // shipped, silently run the full seeder so the user lands
        // on a fully-wired page on first visit. Best-effort — any
        // failure is logged and the page still renders.
        if (get_permission('subject', 'is_add')) {
            $branchId = (int)$this->application_model->get_branch_id();
            if ($branchId > 0) {
                $hasSubjects = (int)$this->db->where('branch_id', $branchId)->count_all_results('subject');
                if ($hasSubjects === 0) {
                    try {
                        $this->load->library('nctb_subject_seeder');
                        $this->nctb_subject_seeder->seedEverythingForBranch($branchId);
                    } catch (Exception $e) {
                        log_message('error', 'NCTB auto-backfill failed for branch ' . $branchId . ': ' . $e->getMessage());
                    }
                }
            }
        }

        $this->data['subjectlist'] = $this->app_lib->getTable('subject');
        $this->data['title'] = translate('subject');
        $this->data['sub_page'] = 'subject/index';
        $this->data['main_menu'] = 'subject';

        // NCTB import tab — surface the catalogue + a preview of how
        // many subjects are still missing for the current branch.
        $this->data['nctb_catalog'] = ['levels' => []];
        $this->data['nctb_missing_count'] = 0;
        $this->data['nctb_total_count']   = 0;
        if (get_permission('subject', 'is_add')) {
            $this->load->library('nctb_subject_seeder');
            $this->data['nctb_catalog']     = $this->nctb_subject_seeder->getCatalog();
            $this->data['nctb_total_count'] = count($this->nctb_subject_seeder->flatList());
            $branchId = (int)$this->application_model->get_branch_id();
            if ($branchId > 0) {
                $this->data['nctb_missing_count'] = $this->_nctb_missing_count($branchId);
            }
        }

        $this->load->view('layout/index', $this->data);
    }

    /**
     * Count how many NCTB subjects are not yet present for the given
     * branch — drives the "X subjects missing" hint on the import tab.
     */
    private function _nctb_missing_count($branchId)
    {
        if (!$this->db->table_exists('subject')) return 0;
        $rows = $this->db->select('name, subject_code, subject_type')
                         ->where('branch_id', (int)$branchId)
                         ->get('subject')
                         ->result_array();
        $existing = [];
        foreach ($rows as $r) {
            $k = strtolower(trim($r['name'])) . '|' . trim($r['subject_code']) . '|' . trim($r['subject_type']);
            $existing[$k] = true;
        }
        $missing = 0;
        foreach ($this->nctb_subject_seeder->flatList() as $s) {
            $k = strtolower(trim($s['name'])) . '|' . trim($s['code']) . '|' . trim($s['type']);
            if (!isset($existing[$k])) $missing++;
        }
        return $missing;
    }

    /**
     * Run the NCTB Bangladesh subject seeder for the current branch.
     * Idempotent — repeated calls only insert subjects that aren't
     * already present.
     *
     *   POST /subject/import_nctb
     *   Body: (optional) levels[]=primary&levels[]=jsc ...
     */
    public function import_nctb()
    {
        if (!get_permission('subject', 'is_add')) {
            access_denied();
        }

        $branchId = (int)$this->application_model->get_branch_id();
        if ($branchId <= 0) {
            set_alert('error', 'Please pick a branch before importing NCTB subjects.');
            redirect(base_url('subject/index'));
            return;
        }

        $options = [];
        $levels  = $this->input->post('levels');
        if (is_array($levels) && !empty($levels)) {
            $options['levels'] = array_values(array_filter(array_map('strval', $levels)));
        }

        $this->load->library('nctb_subject_seeder');
        $result = $this->nctb_subject_seeder->seedEverythingForBranch($branchId, $options);

        $subjIns = (int)($result['subjects']['inserted'] ?? 0);
        $subjSkp = (int)($result['subjects']['skipped'] ?? 0);
        $clsIns  = (int)($result['classes']['inserted']  ?? 0);
        $secIns  = (int)($result['section']['inserted']  ?? 0);
        $asgIns  = (int)($result['assigns']['inserted']  ?? 0);
        $asgSkp  = (int)($result['assigns']['skipped']   ?? 0);
        $grdIns  = (int)($result['grades']['inserted']             ?? 0);
        $trmIns  = (int)($result['exam_terms']['inserted']         ?? 0);
        $mdIns   = (int)($result['mark_distributions']['inserted'] ?? 0);
        $exmIns  = (int)($result['exams']['inserted']              ?? 0);
        $ttIns   = (int)($result['timetable_exam']['inserted']     ?? 0);
        $demoSIns= (int)($result['demo_students']['inserted']      ?? 0);
        $demoMIns= (int)($result['demo_marks']['inserted']         ?? 0);

        if ($subjIns + $clsIns + $secIns + $asgIns + $grdIns + $trmIns + $mdIns + $exmIns + $ttIns + $demoSIns + $demoMIns > 0) {
            $bits = [];
            if ($clsIns)  $bits[] = sprintf('%d new class%s',          $clsIns, $clsIns === 1 ? '' : 'es');
            if ($secIns)  $bits[] = 'default Section A';
            if ($subjIns) $bits[] = sprintf('%d subject%s',            $subjIns, $subjIns === 1 ? '' : 's');
            if ($asgIns)  $bits[] = sprintf('%d class-subject link%s', $asgIns, $asgIns === 1 ? '' : 's');
            if ($grdIns)  $bits[] = sprintf('%d GPA-5 grade%s',        $grdIns, $grdIns === 1 ? '' : 's');
            if ($trmIns)  $bits[] = sprintf('%d exam term%s',          $trmIns, $trmIns === 1 ? '' : 's');
            if ($mdIns)   $bits[] = sprintf('%d mark distribution%s',  $mdIns, $mdIns === 1 ? '' : 's');
            if ($exmIns)  $bits[] = sprintf('%d starter exam%s',       $exmIns, $exmIns === 1 ? '' : 's');
            if ($ttIns)   $bits[] = sprintf('%d timetable row%s',      $ttIns, $ttIns === 1 ? '' : 's');
            if ($demoSIns) $bits[] = sprintf('%d demo student%s',      $demoSIns, $demoSIns === 1 ? '' : 's');
            if ($demoMIns) $bits[] = sprintf('%d demo mark%s',         $demoMIns, $demoMIns === 1 ? '' : 's');
            $msg = 'NCTB import: added ' . implode(' · ', $bits)
                . '. Skipped ' . ($subjSkp + $asgSkp) . ' already-present rows.';
            set_alert('success', $msg);
        } else {
            set_alert('info', 'NCTB import: nothing to add — the branch is already in sync with the platform catalogue.');
        }
        redirect(base_url('subject/index'));
    }

    /**
     * One-click flavour of import_nctb — always seeds the entire
     * NCTB catalogue (no level filter) and wires every class ×
     * section. Backs the big primary button on /subject/index.
     */
    public function import_nctb_all()
    {
        $_POST['levels'] = null;
        $this->import_nctb();
    }

    /**
     * Type-aware one-click auto-setup — backs the
     * "Auto-setup Class, Subject & Exam" / "স্বয়ংক্রিয় সেটআপ"
     * card on the admin dashboard.
     *
     * Reads `branch.institute_type` + `branch.institute_subtype` and
     * runs the NCTB seeder filtered to the matching subset of classes
     * + Bangla exam terms. Idempotent — repeated clicks are a no-op
     * once everything is already wired.
     *
     *   POST /subject/auto_setup
     */
    public function auto_setup()
    {
        if (!get_permission('subject', 'is_add')) {
            access_denied();
        }

        $branchId = (int)$this->application_model->get_branch_id();
        if ($branchId <= 0) {
            set_alert('error', 'Please pick a branch before running the auto-setup.');
            redirect(base_url('dashboard'));
            return;
        }

        $this->load->library('nctb_subject_seeder');

        // First sweep: merge legacy Bangla/English duplicates created by
        // earlier seeder runs (when the dedupe key still included the
        // raw name). Has no effect on a fresh branch.
        $dup = $this->nctb_subject_seeder->dedupeBranchSubjects($branchId);

        $result = $this->nctb_subject_seeder->seedEverythingForBranch($branchId);

        $clsIns  = (int)($result['classes']['inserted']  ?? 0);
        $clsRen  = (int)($result['classes']['renamed']   ?? 0);
        $secIns  = (int)($result['section']['inserted']  ?? 0);
        $subjIns = (int)($result['subjects']['inserted'] ?? 0);
        $subjSkp = (int)($result['subjects']['skipped']  ?? 0);
        $asgIns  = (int)($result['assigns']['inserted']  ?? 0);
        $asgSkp  = (int)($result['assigns']['skipped']   ?? 0);
        $grdIns  = (int)($result['grades']['inserted']             ?? 0);
        $trmIns  = (int)($result['exam_terms']['inserted']         ?? 0);
        $mdIns   = (int)($result['mark_distributions']['inserted'] ?? 0);
        $exmIns  = (int)($result['exams']['inserted']              ?? 0);
        $dupMrg  = (int)($dup['rows_merged'] ?? 0);

        $totalIns = $clsIns + $clsRen + $secIns + $subjIns + $asgIns + $grdIns + $trmIns + $mdIns + $exmIns + $dupMrg;

        if ($totalIns > 0) {
            $bits = [];
            if ($clsIns)  $bits[] = $clsIns  . ' ক্লাস';
            if ($clsRen)  $bits[] = $clsRen  . ' ক্লাস বাংলায় রূপান্তর';
            if ($secIns)  $bits[] = 'ডিফল্ট শাখা A';
            if ($subjIns) $bits[] = $subjIns . ' বিষয়';
            if ($asgIns)  $bits[] = $asgIns  . ' ক্লাস-বিষয় সংযোগ';
            if ($grdIns)  $bits[] = $grdIns  . ' গ্রেড';
            if ($trmIns)  $bits[] = $trmIns  . ' পরীক্ষার মেয়াদ';
            if ($mdIns)   $bits[] = $mdIns   . ' নম্বর বিভাজন';
            if ($exmIns)  $bits[] = $exmIns  . ' পরীক্ষা';
            set_alert('success',
                'স্বয়ংক্রিয় সেটআপ সম্পন্ন — যোগ হয়েছে: ' . implode(' · ', $bits)
                . '। ইতিমধ্যে বিদ্যমান ' . ($subjSkp + $asgSkp) . ' টি সারি বাদ দেওয়া হয়েছে।'
            );
        } else {
            set_alert('info', 'স্বয়ংক্রিয় সেটআপ — কিছুই যোগ করার নেই, ক্লাস ও বিষয় ইতিমধ্যে প্ল্যাটফর্ম ক্যাটালগের সাথে মিল আছে।');
        }
        redirect(base_url('dashboard'));
    }

    // subject edit page
    public function edit($id = '')
    {
        if (!get_permission('subject', 'is_edit')) {
            access_denied();
        }

        $this->data['subject'] = $this->app_lib->getTable('subject', array('t.id' => $id), true);
        $this->data['title'] = translate('subject');
        $this->data['sub_page'] = 'subject/edit';
        $this->data['main_menu'] = 'subject';
        $this->load->view('layout/index', $this->data);
    }

    // moderator subject all information
    public function save()
    {
        if ($_POST) {
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('name', translate('subject_name'), 'trim|required');
            $this->form_validation->set_rules('subject_code', translate('subject_code'), 'trim|required');
            $this->form_validation->set_rules('subject_type', translate('subject_type'), 'trim|required');
            if ($this->form_validation->run() !== false) {
                $arraySubject = array(
                    'name' => $this->input->post('name'),
                    'subject_code' => $this->input->post('subject_code'),
                    'subject_type' => $this->input->post('subject_type'),
                    'subject_author' => $this->input->post('subject_author'),
                    'branch_id' => $this->application_model->get_branch_id(),
                );
                $subjectID = $this->input->post('subject_id');
                if (empty($subjectID)) {
                    if (get_permission('subject', 'is_add')) {
                        $this->db->insert('subject', $arraySubject);
                    }
                    set_alert('success', translate('information_has_been_saved_successfully'));
                } else {
                    if (get_permission('subject', 'is_edit')) {
                        if (!is_superadmin_loggedin()) {
                            $this->db->where('branch_id', get_loggedin_branch_id());
                        }
                        $this->db->where('id', $subjectID);
                        $this->db->update('subject', $arraySubject);
                    }
                    set_alert('success', translate('information_has_been_updated_successfully'));
                }
                $url = base_url('subject/index');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function delete($id = '')
    {
        if (get_permission('subject', 'is_delete')) {
            $this->app_lib->check_branch_restrictions('subject', $id);
            $this->db->where('id', $id);
            $this->db->delete('subject');
            $this->db->where('subject_id', $id);
            $this->db->delete('subject_assign');
        }
    }

    // add subject assign information and delete
    public function class_assign()
    {
        if (!get_permission('subject_class_assign', 'is_view')) {
            access_denied();
        }

        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['assignlist'] = $this->subject_model->getAssignList();
        $this->data['title'] = translate('class_assign');
        $this->data['sub_page'] = 'subject/class_assign';
        $this->data['main_menu'] = 'subject';
        $this->load->view('layout/index', $this->data);
    }

    // moderator class assign save all information
    public function class_assign_save()
    {
        if ($_POST) {
            if (get_permission('subject_class_assign', 'is_add')) {
                if (is_superadmin_loggedin()) {
                    $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
                }
                $this->form_validation->set_rules('class_id', translate('class'), 'trim|required|callback_unique_subject_assign');
                $this->form_validation->set_rules('section_id', translate('section'), 'trim|required');
                $this->form_validation->set_rules('subjects[]', translate('subject'), 'trim|required');
                if ($this->form_validation->run() !== false) {
                    $branchID = $this->application_model->get_branch_id();
                    $arraySubject = array(
                        'class_id' => $this->input->post('class_id'),
                        'section_id' => $this->input->post('section_id'),
                        'session_id' => get_session_id(),
                        'branch_id' => $branchID,
                    );

                    // get class teacher details
                    $get_teacher = $this->subject_model->get('teacher_allocation', $arraySubject, true);
                    $subjects = $this->input->post('subjects');
                    foreach ($subjects as $subject) {
                        $arraySubject['subject_id'] = $subject;
                        $query = $this->db->get_where("subject_assign", $arraySubject);
                        if ($query->num_rows() == 0) {
                            $arraySubject['teacher_id'] = empty($get_teacher) ? 0 : $get_teacher['teacher_id'];
                            $this->db->insert('subject_assign', $arraySubject);
                        }
                    }
                    set_alert('success', translate('information_has_been_saved_successfully'));
                    $url = base_url('subject/class_assign');
                    $array = array('status' => 'success', 'url' => $url, 'error' => '');
                } else {
                    $error = $this->form_validation->error_array();
                    $array = array('status' => 'fail', 'url' => '', 'error' => $error);
                }
                echo json_encode($array);
            }
        }
    }

    // subject assign information edit
    public function class_assign_edit()
    {
        if ($_POST) {
            if (get_permission('subject_class_assign', 'is_edit')) {
                $this->form_validation->set_rules('subjects[]', translate('subject'), 'trim|required');
                if ($this->form_validation->run() !== false) {
                    $sessionID = get_session_id();
                    $classID = $this->input->post('class_id');
                    $sectionID = $this->input->post('section_id');
                    $branchID = $this->application_model->get_branch_id();
                    $arraySubject = array(
                        'class_id' => $classID,
                        'section_id' => $sectionID,
                        'session_id' => $sessionID,
                        'branch_id' => $branchID,
                    );
                    // get class teacher details
                    $get_teacher = $this->subject_model->get('teacher_allocation', $arraySubject, true);

                    $subjects = $this->input->post('subjects');
                    foreach ($subjects as $subject) {
                        $arraySubject['subject_id'] = $subject;
                        $query = $this->db->get_where("subject_assign", $arraySubject);
                        if ($query->num_rows() == 0) {
                            $arraySubject['teacher_id'] = empty($get_teacher) ? 0 : $get_teacher['teacher_id'];
                            $this->db->insert('subject_assign', $arraySubject);
                        }
                    }
                    $this->db->where_not_in('subject_id', $subjects);
                    $this->db->where('class_id', $classID);
                    $this->db->where('section_id', $sectionID);
                    $this->db->where('session_id', $sessionID);
                    $this->db->where('branch_id', $branchID);
                    $this->db->delete('subject_assign');
                    set_alert('success', translate('information_has_been_updated_successfully'));
                    $url = base_url('subject/class_assign');
                    $array = array('status' => 'success', 'url' => $url, 'error' => '');
                } else {
                    $error = $this->form_validation->error_array();
                    $array = array('status' => 'fail', 'url' => '', 'error' => $error);
                }
                echo json_encode($array);
            }
        }
    }

    public function class_assign_delete($class_id = '', $section_id = '')
    {
        if (!get_permission('subject_class_assign', 'is_delete')) {
            access_denied();
        }
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('class_id', $class_id);
        $this->db->where('section_id', $section_id);
        $this->db->where('session_id', get_session_id());
        $this->db->delete('subject_assign');
    }

    // validate here, if the check class assign
    public function unique_subject_assign($class_id)
    {
        $where = array(
            'class_id' => $class_id,
            'section_id' => $this->input->post('section_id'),
            'session_id' => get_session_id(),
        );
        $q = $this->db->get_where('subject_assign', $where)->num_rows();
        if ($q == 0) {
            return true;
        } else {
            $this->form_validation->set_message('unique_subject_assign', 'This class and section is already assigned.');
            return false;
        }
    }

    // teacher assign view page
    public function teacher_assign()
    {
        if (!get_permission('subject_teacher_assign', 'is_view')) {
            access_denied();
        }
        if ($_POST) {
            if (get_permission('subject_teacher_assign', 'is_add')) {
                if (is_superadmin_loggedin()) {
                    $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
                }
                $this->form_validation->set_rules('staff_id', translate('teacher'), 'trim|required');
                $this->form_validation->set_rules('class_id', translate('class'), 'trim|required');
                $this->form_validation->set_rules('section_id', translate('section'), 'trim|required');
                $this->form_validation->set_rules('subject_id', translate('subject'), 'trim|required');
                if ($this->form_validation->run() !== false) {
                    $sessionID = get_session_id();
                    $branchID = $this->application_model->get_branch_id();
                    $classID = $this->input->post('class_id');
                    $sectionID = $this->input->post('section_id');
                    $subjectID = $this->input->post('subject_id');
                    $teacherID = $this->input->post('staff_id');
                    $query = $this->db->get_where("subject_assign", array(
                        'class_id' => $classID,
                        'section_id' => $sectionID,
                        'subject_id' => $subjectID,
                        'session_id' => $sessionID,
                        'branch_id' => $branchID,
                    ));
                    if ($query->num_rows() != 0) {
                        $this->db->where('id', $query->row()->id);
                        $this->db->update('subject_assign', array('teacher_id' => $teacherID));
                    }
                    set_alert('success', translate('information_has_been_updated_successfully'));
                    $url = base_url('subject/teacher_assign');
                    $array = array('status' => 'success', 'url' => $url, 'error' => '');
                } else {
                    $error = $this->form_validation->error_array();
                    $array = array('status' => 'fail', 'url' => '', 'error' => $error);
                }
                echo json_encode($array);
                exit();
            }
        }

        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['assignlist'] = $this->subject_model->getTeacherAssignList();
        $this->data['title'] = translate('teacher_assign');
        $this->data['sub_page'] = 'subject/teacher_assign';
        $this->data['main_menu'] = 'subject';
        $this->load->view('layout/index', $this->data);
    }

    // teacher assign information moderator
    public function teacher_assign_delete($id = '')
    {
        if (get_permission('subject_teacher_assign', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->update('subject_assign', array('teacher_id' => 0));
        }
    }

    // get subject list based on class section
    public function getByClassSection()
    {
        $html = '';
        $classID = $this->input->post('classID');
        $sectionID = $this->input->post('sectionID');
        if (!empty($classID)) {
            $query = $this->subject_model->getSubjectByClassSection($classID, $sectionID);
            if ($query->num_rows() > 0) {
                $html .= '<option value="">' . translate('select') . '</option>';
                $subjects = $query->result_array();
                foreach ($subjects as $row) {
                    $html .= '<option value="' . $row['subject_id'] . '">' . $row['subjectname'] . " (" . $row['subject_code'] . ')</option>';
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select') . '</option>';
        }
        echo $html;
    }
}
