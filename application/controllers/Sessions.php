<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 5.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Sessions.php
 * @copyright : Reserved RamomCoder Team
 */

class Sessions extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    /* form validation rules */
    protected function rules()
    {
        $rules = array(
            array(
                'field' => 'session',
                'label' => 'Session',
                'rules' => 'trim|required|callback_unique_name',
            ),
        );
        return $rules;
    }

    public function index()
    {
        if (is_superadmin_loggedin()) {
            if (isset($_POST['save'])) {
                $this->form_validation->set_rules($this->rules());
                if ($this->form_validation->run() == true) {
                    $this->save($this->input->post());
                    set_alert('success', translate('information_has_been_saved_successfully'));
                    redirect(base_url('sessions'));
                }
            }
            $this->data['title'] = translate('session_settings');
            $this->data['sub_page'] = 'sessions/index';
            $this->data['main_menu'] = 'settings';
            $this->load->view('layout/index', $this->data);
        } else {
            $this->session->set_userdata('last_page', current_url());
            redirect(base_url(), 'refresh');
        }
    }

    public function set_academic($action = '')
    {
        if (is_loggedin()) {
            $this->session->set_userdata('set_session_id', $action);
            if (!empty($_SERVER['HTTP_REFERER'])) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                redirect(base_url('dashboard'), 'refresh');
            }
        } else {
            redirect(base_url(), 'refresh');
        }
    }

    /* academic sessions information are prepared and stored in the database here */
    public function edit()
    {
        if ($_POST) {
            if (!is_superadmin_loggedin()) {
               ajax_access_denied(); 
            }
            $this->form_validation->set_rules($this->rules());
            if ($this->form_validation->run() == true) {
                $this->save($this->input->post());
                set_alert('success', translate('information_has_been_updated_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'url' => '', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function delete($id = '')
    {
        if (is_superadmin_loggedin())
        {
            $id = (int) $id;
            $this->db->where('id', $id);
            $this->db->delete('schoolyear');

            // SS-FIX: keep `global_settings.session_id` pointing at a
            // real schoolyear row. If we leave it pointing at a row
            // we just deleted, every downstream INSERT that FKs to
            // schoolyear (exam, subject_assign, ...) silently fails
            // on a 1452 FK constraint while the controllers still
            // tell the user "saved successfully".
            $currentRow = $this->db->select('session_id')
                                   ->where('id', 1)
                                   ->get('global_settings')
                                   ->row();
            $current = $currentRow ? (int) $currentRow->session_id : 0;
            if ($current === $id || $current === 0
                || (int) $this->db->where('id', $current)
                                  ->count_all_results('schoolyear') === 0) {
                $latest = $this->db->select('id')
                                   ->order_by('id', 'DESC')
                                   ->limit(1)
                                   ->get('schoolyear')
                                   ->row();
                if (!empty($latest) && !empty($latest->id)) {
                    $this->db->where('id', 1)
                             ->update('global_settings', array('session_id' => (int) $latest->id));
                }
            }
        }
    }

    /* unique academic sessions name verification is done here */
    public function unique_name($year)
    {
        $schoolyearID = $this->input->post('schoolyear_id');
        if (!empty($schoolyearID)) {
            $this->db->where_not_in('id', $schoolyearID);
        }
        $this->db->where(array('school_year' => $year));
        $uniform_row = $this->db->get('schoolyear')->num_rows();
        if ($uniform_row == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_name", translate('already_taken'));
            return false;
        }
    }

    protected function save($data)
    {
        $arrayYear = array(
            'school_year' => $data['session'],
            'created_by' => get_loggedin_user_id(),
        );
        if (!isset($data['schoolyear_id'])) {
            $this->db->insert('schoolyear', $arrayYear);
        } else {
            $this->db->where('id', $data['schoolyear_id']);
            $this->db->update('schoolyear', $arrayYear);
        }
    }
}
