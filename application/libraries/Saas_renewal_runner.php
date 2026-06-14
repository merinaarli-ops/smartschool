<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Saas_renewal_runner — core auto-renewal loop, shared by the CLI cron and the
 * super-admin's "Run renewal cron now" button.
 *
 * Lifted out of Saas_renewals_cli so the web trigger can call the exact same
 * code path without spawning a subprocess.
 *
 * Reads operator-tunable knobs (grace days, email subject/body, from address,
 * billing contact) from saas_setting via Saas_setting_model.
 *
 * Behaviour
 * ---------
 * For every active/trial/past_due subscription whose `current_period_end` is
 * within `renewal_grace_days` of today and which has no open/draft invoice
 * already, create an invoice priced at the package's `price_bdt` and email the
 * school admin a /billing/pay/<id> link.
 *
 * Returns ['created' => int, 'emailed' => int, 'skipped' => int].
 *
 * @author SmartSchool.bd
 */
class Saas_renewal_runner
{
    /** @var CI_Controller */
    protected $CI;

    public function __construct($controller = null)
    {
        $this->CI = $controller ?: get_instance();
        $this->CI->load->model('saas_model');
        $this->CI->load->model('saas_setting_model');
        $this->CI->load->helper('url');
    }

    public function run()
    {
        $grace   = $this->CI->saas_setting_model->getInt('renewal_grace_days', 7, 0, 365);
        $cutoff  = date('Y-m-d', strtotime('+' . $grace . ' days'));
        $today   = date('Y-m-d');
        $created = 0;
        $skipped = 0;
        $emailed = 0;

        // Use LEFT JOIN on saas_package so subscriptions with no package
        // (set to NULL by super-admin at approval time) still surface.
        // We also pull the new override columns so the cron can honour
        // per-subscription pricing / validity / renewal_mode.
        $select  = 's.id AS sub_id, s.school_id, s.package_id, s.current_period_end, s.expire_date, s.status, ';
        $select .= 'b.id AS branch_id, b.name AS branch_name, b.email AS branch_email, ';
        $select .= 'p.price_bdt, p.billing_period, p.name AS package_name';
        if ($this->CI->db->field_exists('override_price_bdt', 'saas_subscriptions')) {
            $select .= ', s.override_price_bdt';
        }
        if ($this->CI->db->field_exists('validity_days', 'saas_subscriptions')) {
            $select .= ', s.validity_days';
        }
        if ($this->CI->db->field_exists('renewal_mode', 'saas_subscriptions')) {
            $select .= ', s.renewal_mode';
        }
        $rows = $this->CI->db
            ->select($select)
            ->from('saas_subscriptions s')
            ->join('branch b',         'b.id = s.school_id')
            ->join('saas_package p',   'p.id = s.package_id', 'left')
            ->where_in('s.status', ['active', 'trial', 'past_due'])
            ->where('s.current_period_end IS NOT NULL', null, false)
            ->where('s.current_period_end <=', $cutoff)
            ->get()->result();

        foreach ($rows as $r) {
            // Renewal mode gate. Anything other than auto_invoice short-circuits
            // here (the tenant subscription view handles the contact_admin case
            // when the period actually rolls over).
            $mode = isset($r->renewal_mode) ? $r->renewal_mode : 'auto_invoice';
            if ($mode !== 'auto_invoice') { $skipped++; continue; }

            // Effective price: subscription override → package price → skip if zero.
            $price = isset($r->override_price_bdt) && $r->override_price_bdt !== null && $r->override_price_bdt !== ''
                ? (float)$r->override_price_bdt
                : (float)$r->price_bdt;
            if ($price <= 0) { $skipped++; continue; }

            $existing = $this->CI->db->where('subscription_id', (int)$r->sub_id)
                ->where_in('status', ['open', 'draft'])
                ->count_all_results('invoice');
            if ($existing > 0) { $skipped++; continue; }

            $periodStart = $r->current_period_end ?: $today;
            if (!empty($r->validity_days)) {
                $periodEnd = date('Y-m-d', strtotime($periodStart . ' +' . (int)$r->validity_days . ' days'));
            } else {
                $months    = ($r->billing_period === 'yearly') ? 12 : 1;
                $periodEnd = date('Y-m-d', strtotime($periodStart . ' +' . $months . ' months'));
            }
            $dueDate = date('Y-m-d', strtotime($periodStart . ' +7 days'));

            $invoiceId = $this->CI->saas_model->createInvoice(
                (int)$r->branch_id,
                (int)$r->sub_id,
                $price,
                $periodStart,
                $periodEnd,
                $dueDate
            );
            $created++;

            if (!empty($r->branch_email) && $invoiceId > 0) {
                // Pass the effective price into the email template via the
                // already-existing price_bdt slot expected by _email_pay_link.
                $r->price_bdt = $price;
                if ($this->_email_pay_link((int)$invoiceId, $r, $dueDate)) $emailed++;
            }
        }

        return ['created' => $created, 'emailed' => $emailed, 'skipped' => $skipped];
    }

    protected function _email_pay_link($invoiceId, $sub, $dueDate)
    {
        $this->CI->load->library('email');
        $this->CI->email->clear(true);

        $subjectTpl = $this->CI->saas_setting_model->get('renewal_email_subject', 'Action required: your SmartSchool.bd subscription invoice');
        $bodyTpl    = $this->CI->saas_setting_model->get('renewal_email_body',    '<p>Pay {invoice_no} for {amount} at {pay_url}.</p>');
        $fromName   = $this->CI->saas_setting_model->get('renewal_email_from_name',  'SmartSchool.bd Billing');
        $fromEmail  = $this->CI->saas_setting_model->get('renewal_email_from_email', 'billing@smartschool.bd');
        $billingTo  = $this->CI->saas_setting_model->get('billing_contact_email',    'billing@smartschool.bd');

        $inv     = $this->CI->saas_model->getInvoiceById((int)$invoiceId);
        $payUrl  = base_url('billing/pay/' . (int)$invoiceId);
        $vars    = [
            '{invoice_no}'             => $inv ? ($inv->invoice_no ?: '#' . $inv->id) : '',
            '{amount}'                 => 'BDT ' . number_format((float)$sub->price_bdt, 2),
            '{pay_url}'                => $payUrl,
            '{school_name}'            => $sub->branch_name,
            '{due_date}'               => $dueDate,
            '{billing_contact_email}'  => $billingTo,
        ];

        $subject = strtr($subjectTpl, $vars);
        $body    = strtr($bodyTpl,    $vars);

        $this->CI->email->from($fromEmail, $fromName);
        $this->CI->email->to($sub->branch_email);
        $this->CI->email->subject($subject);
        $this->CI->email->message($body);
        // Render as HTML; the body template ships with <p> / <a> markup.
        $this->CI->email->set_mailtype('html');
        return @$this->CI->email->send();
    }
}
