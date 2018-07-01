<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * vat_registryPlane
 *
 * @author		vat_registryPlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 vat_registryPlane.com
 * @license		https://vat_registryplane.com/license.txt
 * @link		https://vat_registryplane.com
 */

/**
 * Class vats_registry
 */
class Vats_Registry extends Admin_Controller
{

    /**
     * vats_registry constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('mdl_vats_registry');
    }

    public function index()
    {
        // Display all vats_registry by default
        redirect('vats_registry/status/all');
    }

    /**
     * @param string $status
     * @param int $page
     */
    public function status($status = 'all', $page = 0)
    {
        // Determine which group of vats_registry to load
        switch ($status) {
            case 'draft':
                $this->mdl_vats_registry->is_draft();
                break;
            case 'ok':
                $this->mdl_vats_registry->is_ok();
                break;
        }

        $this->mdl_vats_registry->paginate(site_url('vats_registry/status/' . $status), $page);
        $vats_registry = $this->mdl_vats_registry->result();

        $this->layout->set(
            [
                'vats_registry' => $vats_registry,
                'status' => $status,
                'filter_display' => true,
                'filter_placeholder' => trans('filter_vats_registry'),
                'filter_method' => 'filter_vats_registry',
                'vat_registry_statuses' => $this->mdl_vats_registry->statuses(),
            ]
        );

        $this->layout->buffer('content', 'vats_registry/index');
        $this->layout->render();
    }

    public function archive()
    {
        $vat_registry_array = [];

        if (isset($_POST['vat_registry_number'])) {
            $vat_registryNumber = $_POST['vat_registry_number'];
            $vat_registry_array = glob(UPLOADS_ARCHIVE_FOLDER . '*' . '_' . $vat_registryNumber . '.pdf');
            $this->layout->set(
                [
                    'vats_registry_archive' => $vat_registry_array,
                ]);
            $this->layout->buffer('content', 'vats_registry/archive');
            $this->layout->render();

        } else {
            foreach (glob(UPLOADS_ARCHIVE_FOLDER . '*.pdf') as $file) {
                array_push($vat_registry_array, $file);
            }

            rsort($vat_registry_array);
            $this->layout->set(
                [
                    'vats_registry_archive' => $vat_registry_array,
                ]);
            $this->layout->buffer('content', 'vats_registry/archive');
            $this->layout->render();
        }
    }

    /**
     * @param $vat_registry
     */
    public function download($vat_registry)
    {
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="' . urldecode($vat_registry) . '"');
        readfile(UPLOADS_ARCHIVE_FOLDER . urldecode($vat_registry));
    }

    /**
     * @param $vat_registry_id
     */
    public function view($vat_registry_id)
    {
        $this->load->model(
            [
                'mdl_items',
                'tax_rates/mdl_tax_rates',
                'payment_methods/mdl_payment_methods',
                'mdl_vat_registry_tax_rates',
                'custom_fields/mdl_custom_fields',
            ]
        );

        $this->load->helper("custom_values");
        $this->load->helper("client");
        $this->load->model('units/mdl_units');
        $this->load->module('payments');

        $this->load->model('custom_values/mdl_custom_values');
        $this->load->model('custom_fields/mdl_vat_registry_custom');

        $this->db->reset_query();

        /*$vat_registry_custom = $this->mdl_vat_registry_custom->where('vat_registry_id', $vat_registry_id)->get();

        if ($vat_registry_custom->num_rows()) {
            $vat_registry_custom = $vat_registry_custom->row();

            unset($vat_registry_custom->vat_registry_id, $vat_registry_custom->vat_registry_custom_id);

            foreach ($vat_registry_custom as $key => $val) {
                $this->mdl_vats_registry->set_form_value('custom[' . $key . ']', $val);
            }
        }*/

        $fields = $this->mdl_vat_registry_custom->by_id($vat_registry_id)->get()->result();
        $vat_registry = $this->mdl_vats_registry->get_by_id($vat_registry_id);

        if (!$vat_registry) {
            show_404();
        }

        $custom_fields = $this->mdl_custom_fields->by_table('ip_vat_registry_custom')->get()->result();
        $custom_values = [];
        foreach ($custom_fields as $custom_field) {
            if (in_array($custom_field->custom_field_type, $this->mdl_custom_values->custom_value_fields())) {
                $values = $this->mdl_custom_values->get_by_fid($custom_field->custom_field_id)->result();
                $custom_values[$custom_field->custom_field_id] = $values;
            }
        }

        foreach ($custom_fields as $cfield) {
            foreach ($fields as $fvalue) {
                if ($fvalue->vat_registry_custom_fieldid == $cfield->custom_field_id) {
                    // TODO: Hackish, may need a better optimization
                    $this->mdl_vats_registry->set_form_value(
                        'custom[' . $cfield->custom_field_id . ']',
                        $fvalue->vat_registry_custom_fieldvalue
                    );
                    break;
                }
            }
        }

        $this->layout->set(
            [
                'vat_registry' => $vat_registry,
                'items' => $this->mdl_items->where('vat_registry_id', $vat_registry_id)->get()->result(),
                'vat_registry_id' => $vat_registry_id,
                'tax_rates' => $this->mdl_tax_rates->get()->result(),
                'vat_registry_tax_rates' => $this->mdl_vat_registry_tax_rates->where('vat_registry_id', $vat_registry_id)->get()->result(),
                'units' => $this->mdl_units->get()->result(),
                'payment_methods' => $this->mdl_payment_methods->get()->result(),
                'custom_fields' => $custom_fields,
                'custom_values' => $custom_values,
                'custom_js_vars' => [
                    'currency_symbol' => get_setting('currency_symbol'),
                    'currency_symbol_placement' => get_setting('currency_symbol_placement'),
                    'decimal_point' => get_setting('decimal_point'),
                ],
                'vat_registry_statuses' => $this->mdl_vats_registry->statuses(),
            ]
        );

        if ($vat_registry->sumex_id != null) {
            $this->layout->buffer(
                [
                    ['modal_delete_vat_registry', 'vats_registry/modal_delete_vat_registry'],
                    ['modal_add_vat_registry_tax', 'vats_registry/modal_add_vat_registry_tax'],
                    ['modal_add_payment', 'payments/modal_add_payment'],
                    ['content', 'vats_registry/view_sumex'],
                ]
            );
        } else {
            $this->layout->buffer(
                [
                    ['modal_delete_vat_registry', 'vats_registry/modal_delete_vat_registry'],
                    ['modal_add_vat_registry_tax', 'vats_registry/modal_add_vat_registry_tax'],
                    ['modal_add_payment', 'payments/modal_add_payment'],
                    ['content', 'vats_registry/view'],
                ]
            );
        }

        $this->layout->render();
    }

    /**
     * @param $vat_registry_id
     */
    public function delete($vat_registry_id)
    {
        // Get the status of the vat_registry
        $vat_registry = $this->mdl_vats_registry->get_by_id($vat_registry_id);
        $vat_registry_status = $vat_registry->vat_registry_status_id;

        if ($vat_registry_status == 1 || $this->config->item('enable_vat_registry_deletion') === true) {
            // If vat_registry refers to tasks, mark those tasks back to 'Complete'
            $this->load->model('tasks/mdl_tasks');
            $tasks = $this->mdl_tasks->update_on_vat_registry_delete($vat_registry_id);

            // Delete the vat_registry
            $this->mdl_vats_registry->delete($vat_registry_id);
        } else {
            // Add alert that vats_registry can't be deleted
            $this->session->set_flashdata('alert_error', trans('vat_registry_deletion_forbidden'));
        }

        // Redirect to vat_registry index
        redirect('vats_registry/index');
    }

    /**
     * @param $vat_registry_id
     * @param bool $stream
     * @param null $vat_registry_template
     */
    public function generate_pdf($vat_registry_id, $stream = true, $vat_registry_template = null)
    {
        $this->load->helper('pdf');

        if (get_setting('mark_vats_registry_sent_pdf') == 1) {
            $this->mdl_vats_registry->mark_sent($vat_registry_id);
        }

        generate_vat_registry_pdf($vat_registry_id, $stream, $vat_registry_template, null);
    }

    /**
     * @param $vat_registry_id
     */
    public function generate_zugferd_xml($vat_registry_id)
    {
        $this->load->model('vats_registry/mdl_items');
        $this->load->library('ZugferdXml', [
            'vat_registry' => $this->mdl_vats_registry->get_by_id($vat_registry_id),
            'items' => $this->mdl_items->where('vat_registry_id', $vat_registry_id)->get()->result(),
        ]);

        $this->output->set_content_type('text/xml');
        $this->output->set_output($this->zugferdxml->xml());
    }

    public function generate_sumex_pdf($vat_registry_id)
    {
        $this->load->helper('pdf');

        generate_vat_registry_sumex($vat_registry_id);
    }

    public function generate_sumex_copy($vat_registry_id)
    {


        $this->load->model('vats_registry/mdl_items');
        $this->load->library('Sumex', [
            'vat_registry' => $this->mdl_vats_registry->get_by_id($vat_registry_id),
            'items' => $this->mdl_items->where('vat_registry_id', $vat_registry_id)->get()->result(),
            'options' => [
                'copy' => "1",
                'storno' => "0",
            ],
        ]);

        $this->output->set_content_type('application/pdf');
        $this->output->set_output($this->sumex->pdf());
    }

    /**
     * @param $vat_registry_id
     * @param $vat_registry_tax_rate_id
     */
    public function delete_vat_registry_tax($vat_registry_id, $vat_registry_tax_rate_id)
    {
        $this->load->model('mdl_vat_registry_tax_rates');
        $this->mdl_vat_registry_tax_rates->delete($vat_registry_tax_rate_id);

        $this->load->model('mdl_vat_registry_amounts');
        $this->mdl_vat_registry_amounts->calculate($vat_registry_id);

        redirect('vats_registry/view/' . $vat_registry_id);
    }

    public function recalculate_all_vats_registry()
    {
        $this->db->select('vat_registry_id');
        $vat_registry_ids = $this->db->get('ip_vats_registry')->result();

        $this->load->model('mdl_vat_registry_amounts');

        foreach ($vat_registry_ids as $vat_registry_id) {
            $this->mdl_vat_registry_amounts->calculate($vat_registry_id->vat_registry_id);
        }
    }

}
