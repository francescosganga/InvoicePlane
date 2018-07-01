<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
 * vat_registryPlane
 *
 * @author      vat_registryPlane Developers & Contributors
 * @copyright   Copyright (c) 2012 - 2018 vat_registryPlane.com
 * @license     https://vat_registryplane.com/license.txt
 * @link        https://vat_registryplane.com
 */

/**
 * Class mdl_vats_registry
 */
class Mdl_Vats_Registry extends Response_Model
{
    public $table = 'ip_vats_registry';
    public $primary_key = 'ip_vats_registry.vat_registry_id';
    public $date_modified_field = 'vat_registry_date_modified';

    /**
     * @return array
     */
    public function statuses()
    {
        return array(
            '1' => array(
                'label' => trans('draft'),
                'class' => 'draft',
                'href' => 'vats_registry/status/draft'
            ),
            '2' => array(
                'label' => trans('ok'),
                'class' => 'ok',
                'href' => 'vats_registry/status/ok'
            )
        );
    }

    public function default_select()
    {
        $this->db->select("
            SQL_CALC_FOUND_ROWS
            ip_vats_registry.*", false);
    }

    public function default_order_by()
    {
        $this->db->order_by('ip_vats_registry.vat_registry_id DESC');
    }

    public function default_join()
    {
        /*$this->db->join('ip_vats_registry', 'ip_clients.client_id = ip_vats_registry.client_id');
        $this->db->join('ip_users', 'ip_users.user_id = ip_vats_registry.user_id');
        $this->db->join('ip_vat_registry_amounts', 'ip_vat_registry_amounts.vat_registry_id = ip_vats_registry.vat_registry_id', 'left');
        $this->db->join('ip_vat_registry_sumex', 'sumex_vat_registry = ip_vats_registry.vat_registry_id', 'left');
        $this->db->join('ip_quotes', 'ip_quotes.vat_registry_id = ip_vats_registry.vat_registry_id', 'left');*/
    }

    /**
     * @return array
     */
    public function validation_rules()
    {
        return array(
            'client_id' => array(
                'field' => 'client_id',
                'label' => trans('client'),
                'rules' => 'required'
            ),
            'vat_registry_date_created' => array(
                'field' => 'vat_registry_date_created',
                'label' => trans('vat_registry_date'),
                'rules' => 'required'
            ),
            'vat_registry_time_created' => array(
                'rules' => 'required'
            ),
            'vat_registry_group_id' => array(
                'field' => 'vat_registry_group_id',
                'label' => trans('vat_registry_group'),
                'rules' => 'required'
            ),
            'vat_registry_password' => array(
                'field' => 'vat_registry_password',
                'label' => trans('vat_registry_password')
            ),
            'user_id' => array(
                'field' => 'user_id',
                'label' => trans('user'),
                'rule' => 'required'
            ),
            'payment_method' => array(
                'field' => 'payment_method',
                'label' => trans('payment_method')
            ),
        );
    }

    /**
     * @return array
     */
    public function validation_rules_save_vat_registry()
    {
        return array(
            'vat_registry_number' => array(
                'field' => 'vat_registry_number',
                'label' => trans('vat_registry') . ' #',
                'rules' => 'is_unique[ip_vats_registry.vat_registry_number' . (($this->id) ? '.vat_registry_id.' . $this->id : '') . ']'
            ),
            'vat_registry_date_created' => array(
                'field' => 'vat_registry_date_created',
                'label' => trans('date'),
                'rules' => 'required'
            ),
            'vat_registry_date_due' => array(
                'field' => 'vat_registry_date_due',
                'label' => trans('due_date'),
                'rules' => 'required'
            ),
            'vat_registry_time_created' => array(
                'rules' => 'required'
            ),
            'vat_registry_password' => array(
                'field' => 'vat_registry_password',
                'label' => trans('vat_registry_password')
            )
        );
    }

    /**
     * @param null $db_array
     * @param bool $include_vat_registry_tax_rates
     * @return int|null
     */
    public function create($db_array = null, $include_vat_registry_tax_rates = true)
    {

        $vat_registry_id = parent::save(null, $db_array);

        $inv = $this->where('ip_vats_registry.vat_registry_id', $vat_registry_id)->get()->row();
        $vat_registry_group = $inv->vat_registry_group_id;

        // Create an vat_registry amount record
        $db_array = array(
            'vat_registry_id' => $vat_registry_id
        );

        $this->db->insert('ip_vat_registry_amounts', $db_array);

        if ($include_vat_registry_tax_rates) {
            // Create the default vat_registry tax record if applicable
            if (get_setting('default_vat_registry_tax_rate')) {
                $db_array = array(
                    'vat_registry_id' => $vat_registry_id,
                    'tax_rate_id' => get_setting('default_vat_registry_tax_rate'),
                    'include_item_tax' => get_setting('default_include_item_tax', 0),
                    'vat_registry_tax_rate_amount' => 0
                );

                $this->db->insert('ip_vat_registry_tax_rates', $db_array);
            }
        }

        if($vat_registry_group !== '0') {
            $this->load->model('vat_registry_groups/mdl_vats_registry_groups');
            $invgroup = $this->mdl_vats_registry_groups->where('vat_registry_group_id', $vat_registry_group)->get()->row();
            if (preg_match("/sumex/i", $invgroup->vat_registry_group_name)) {
                // If the vat_registry Group includes "Sumex", make the vat_registry a Sumex one
                $db_array = array(
                    'sumex_vat_registry' => $vat_registry_id
                );
                $this->db->insert('ip_vat_registry_sumex', $db_array);
            }
        }

        return $vat_registry_id;
    }

    /**
     * Copies vat_registry items, tax rates, etc from source to target
     * @param int $source_id
     * @param int $target_id
     * @param bool $copy_recurring_items_only
     */
    public function copy_vat_registry($source_id, $target_id, $copy_recurring_items_only = false)
    {
        $this->load->model('vats_registry/mdl_items');
        $this->load->model('vats_registry/mdl_vats_registry_tax_rates');

        // Copy the items
        $vat_registry_items = $this->mdl_items->where('vat_registry_id', $source_id)->get()->result();

        foreach ($vat_registry_items as $vat_registry_item) {
            $db_array = array(
                'vat_registry_id' => $target_id,
                'item_tax_rate_id' => $vat_registry_item->item_tax_rate_id,
                'item_product_id' => $vat_registry_item->item_product_id,
                'item_task_id' => $vat_registry_item->item_task_id,
                'item_name' => $vat_registry_item->item_name,
                'item_description' => $vat_registry_item->item_description,
                'item_quantity' => $vat_registry_item->item_quantity,
                'item_price' => $vat_registry_item->item_price,
                'item_discount_amount' => $vat_registry_item->item_discount_amount,
                'item_order' => $vat_registry_item->item_order,
                'item_is_recurring' => $vat_registry_item->item_is_recurring,
                'item_product_unit' => $vat_registry_item->item_product_unit,
                'item_product_unit_id' => $vat_registry_item->item_product_unit_id,
            );

            if (!$copy_recurring_items_only || $vat_registry_item->item_is_recurring) {
                $this->mdl_items->save(null, $db_array);
            }
        }

        // Copy the tax rates
        $vat_registry_tax_rates = $this->mdl_vats_registry_tax_rates->where('vat_registry_id', $source_id)->get()->result();

        foreach ($vat_registry_tax_rates as $vat_registry_tax_rate) {
            $db_array = array(
                'vat_registry_id' => $target_id,
                'tax_rate_id' => $vat_registry_tax_rate->tax_rate_id,
                'include_item_tax' => $vat_registry_tax_rate->include_item_tax,
                'vat_registry_tax_rate_amount' => $vat_registry_tax_rate->vat_registry_tax_rate_amount
            );

            $this->mdl_vats_registry_tax_rates->save(null, $db_array);
        }

        // Copy the custom fields
        $this->load->model('custom_fields/mdl_vats_registry_custom');
        $custom_fields = $this->mdl_vats_registry_custom->where('vat_registry_id', $source_id)->get()->result();

        $form_data = array();
        foreach ($custom_fields as $field) {
            $form_data[$field->vat_registry_custom_fieldid] = $field->vat_registry_custom_fieldvalue;
        }
        $this->mdl_vats_registry_custom->save_custom($target_id, $form_data);
    }

    /**
     * Copies vat_registry items, tax rates, etc from source to target
     * @param int $source_id
     * @param int $target_id
     */
    public function copy_credit_vat_registry($source_id, $target_id)
    {
        $this->load->model('vats_registry/mdl_items');
        $this->load->model('vats_registry/mdl_vats_registry_tax_rates');

        $vat_registry_items = $this->mdl_items->where('vat_registry_id', $source_id)->get()->result();

        foreach ($vat_registry_items as $vat_registry_item) {
            $db_array = array(
                'vat_registry_id' => $target_id,
                'item_tax_rate_id' => $vat_registry_item->item_tax_rate_id,
                'item_product_id' => $vat_registry_item->item_product_id,
                'item_task_id' => $vat_registry_item->item_task_id,
                'item_name' => $vat_registry_item->item_name,
                'item_description' => $vat_registry_item->item_description,
                'item_quantity' => $vat_registry_item->item_quantity * -1,
                'item_price' => $vat_registry_item->item_price,
                'item_discount_amount' => $vat_registry_item->item_discount_amount,
                'item_order' => $vat_registry_item->item_order,
                'item_is_recurring' => $vat_registry_item->item_is_recurring,
                'item_product_unit' => $vat_registry_item->item_product_unit,
                'item_product_unit_id' => $vat_registry_item->item_product_unit_id,
            );

            $this->mdl_items->save(null, $db_array);
        }

        $vat_registry_tax_rates = $this->mdl_vats_registry_tax_rates->where('vat_registry_id', $source_id)->get()->result();

        foreach ($vat_registry_tax_rates as $vat_registry_tax_rate) {
            $db_array = array(
                'vat_registry_id' => $target_id,
                'tax_rate_id' => $vat_registry_tax_rate->tax_rate_id,
                'include_item_tax' => $vat_registry_tax_rate->include_item_tax,
                'vat_registry_tax_rate_amount' => -$vat_registry_tax_rate->vat_registry_tax_rate_amount
            );

            $this->mdl_vats_registry_tax_rates->save(null, $db_array);
        }

        // Copy the custom fields
        $this->load->model('custom_fields/mdl_vats_registry_custom');
        $custom_fields = $this->mdl_vats_registry_custom->where('vat_registry_id', $source_id)->get()->result();

        $form_data = array();
        foreach ($custom_fields as $field) {
            $form_data[$field->vat_registry_custom_fieldid] = $field->vat_registry_custom_fieldvalue;
        }
        $this->mdl_vats_registry_custom->save_custom($target_id, $form_data);
    }

    /**
     * @return array
     */
    public function db_array()
    {
        $db_array = parent::db_array();

        // Get the client id for the submitted vat_registry
        $this->load->model('clients/mdl_clients');

        // Check if is SUMEX
        $this->load->model('vat_registry_groups/mdl_vats_registry_groups');

        $db_array['vat_registry_date_created'] = date_to_mysql($db_array['vat_registry_date_created']);
        $db_array['vat_registry_date_due'] = $this->get_date_due($db_array['vat_registry_date_created']);
        $db_array['vat_registry_terms'] = get_setting('default_vat_registry_terms');

        if (!isset($db_array['vat_registry_status_id'])) {
            $db_array['vat_registry_status_id'] = 1;
        }

        $generate_vat_registry_number = get_setting('generate_vat_registry_number_for_draft');

        if ($db_array['vat_registry_status_id'] === 1 && $generate_vat_registry_number == 1) {
            $db_array['vat_registry_number'] = $this->get_vat_registry_number($db_array['vat_registry_group_id']);
        } elseif ($db_array['vat_registry_status_id'] != 1) {
            $db_array['vat_registry_number'] = $this->get_vat_registry_number($db_array['vat_registry_group_id']);
        } else {
            $db_array['vat_registry_number'] = '';
        }

        // Set default values
        $db_array['payment_method'] = (empty($db_array['payment_method']) ? 0 : $db_array['payment_method']);

        // Generate the unique url key
        $db_array['vat_registry_url_key'] = $this->get_url_key();

        return $db_array;
    }

    /**
     * @param $vat_registry
     * @return mixed
     */
    public function get_payments($vat_registry)
    {
        $this->load->model('payments/mdl_payments');

        $this->db->where('vat_registry_id', $vat_registry->vat_registry_id);
        $payment_results = $this->db->get('ip_payments');

        if ($payment_results->num_rows()) {
            return $vat_registry;
        }

        $vat_registry->payments = $payment_results->result();

        return $vat_registry;
    }

    /**
     * @param string $vat_registry_date_created
     * @return string
     */
    public function get_date_due($vat_registry_date_created)
    {
        $vat_registry_date_due = new DateTime($vat_registry_date_created);
        $vat_registry_date_due->add(new DateInterval('P' . get_setting('vats_registry_due_after') . 'D'));
        return $vat_registry_date_due->format('Y-m-d');
    }

    /**
     * @param $vat_registry_group_id
     * @return mixed
     */
    public function get_vat_registry_number($vat_registry_group_id)
    {
        $this->load->model('vat_registry_groups/mdl_vats_registry_groups');
        return $this->mdl_vats_registry_groups->generate_vat_registry_number($vat_registry_group_id);
    }

    /**
     * @return string
     */
    public function get_url_key()
    {
        $this->load->helper('string');
        return random_string('alnum', 32);
    }

    /**
     * @param $vat_registry_id
     * @return mixed
     */
    public function get_vat_registry_group_id($vat_registry_id)
    {
        $vat_registry = $this->get_by_id($vat_registry_id);
        return $vat_registry->vat_registry_group_id;
    }

    /**
     * @param int $parent_vat_registry_id
     * @return mixed
     */
    public function get_parent_vat_registry_number($parent_vat_registry_id)
    {
        $parent_vat_registry = $this->get_by_id($parent_vat_registry_id);
        return $parent_vat_registry->vat_registry_number;
    }

    /**
     * @return mixed
     */
    public function get_custom_values($id)
    {
        $this->load->module('custom_fields/mdl_vats_registry_custom');
        return $this->vat_registry_custom->get_by_invid($id);
    }


    /**
     * @param int $vat_registry_id
     */
    public function delete($vat_registry_id)
    {
        parent::delete($vat_registry_id);

        $this->load->helper('orphan');
        delete_orphans();
    }

    // Used from the guest module, excludes draft and paid
    public function is_draft()
    {
        $this->filter_where('vat_registry_status_id', 1);
        return $this;
    }

    public function is_ok()
    {
        $this->filter_where('vat_registry_status_id', 2);
        return $this;
    }

    /**
     * @param $vat_registry_id
     */
    public function mark_viewed($vat_registry_id)
    {
        $vat_registry = $this->get_by_id($vat_registry_id);

        if (!empty($vat_registry)) {
            if ($vat_registry->vat_registry_status_id == 2) {
                $this->db->where('vat_registry_id', $vat_registry_id);
                $this->db->where('vat_registry_id', $vat_registry_id);
                $this->db->set('vat_registry_status_id', 3);
                $this->db->update('ip_vats_registry');
            }

            // Set the vat_registry to read-only if feature is not disabled and setting is view
            if ($this->config->item('disable_read_only') == false && get_setting('read_only_toggle') == 3) {
                $this->db->where('vat_registry_id', $vat_registry_id);
                $this->db->set('is_read_only', 1);
                $this->db->update('ip_vats_registry');
            }
        }
    }

    /**
     * @param $vat_registry_id
     */
    public function mark_sent($vat_registry_id)
    {
        $vat_registry = $this->mdl_vats_registry->get_by_id($vat_registry_id);

        if (!empty($vat_registry)) {
            if ($vat_registry->vat_registry_status_id == 1) {
                // Generate new vat_registry number if applicable
                $vat_registry_number = $vat_registry->vat_registry_number;

                // Set new date and save
                $this->db->where('vat_registry_id', $vat_registry_id);
                $this->db->set('vat_registry_status_id', 2);
                $this->db->set('vat_registry_number', $vat_registry_number);
                $this->db->update('ip_vats_registry');
            }

            // Set the vat_registry to read-only if feature is not disabled and setting is sent
            if ($this->config->item('disable_read_only') == false && get_setting('read_only_toggle') == 2) {
                $this->db->where('vat_registry_id', $vat_registry_id);
                $this->db->set('is_read_only', 1);
                $this->db->update('ip_vats_registry');
            }
        }
    }

    /**
     * @param $vat_registry_id
     */
    public function generate_vat_registry_number_if_applicable($vat_registry_id)
    {
        $vat_registry = $this->mdl_vats_registry->get_by_id($vat_registry_id);

        if (!empty($vat_registry)) {
            if ($vat_registry->vat_registry_status_id == 1 && $vat_registry->vat_registry_number == "") {
                // Generate new vat_registry number if applicable
                if (get_setting('generate_vat_registry_number_for_draft') == 0) {
                    $vat_registry_number = $this->get_vat_registry_number($vat_registry->vat_registry_group_id);

                    // Set new vat_registry number and save
                    $this->db->where('vat_registry_id', $vat_registry_id);
                    $this->db->set('vat_registry_number', $vat_registry_number);
                    $this->db->update('ip_vats_registry');
                }
            }
        }
    }

}
