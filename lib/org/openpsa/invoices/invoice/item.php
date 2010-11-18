<?php
/**
 * @package org.openpsa.invoices
 * @copyright
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped base class
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_invoice_item_dba extends midcom_core_dbaobject
{
    var $__midcom_class_name__ = __CLASS__;
    var $__mgdschema_class_name__ = 'org_openpsa_invoice_item';
    var $skip_invoice_update = false;

    function __construct($id = null)
    {
        $this->_use_rcs = false;
        $this->_use_activitystream = false;
        return parent::__construct($id);
    }

    static function new_query_builder()
    {
        return midcom::dbfactory()->new_query_builder(__CLASS__);
    }

    static function new_collector($domain, $value)
    {
        return midcom::dbfactory()->new_collector(__CLASS__, $domain, $value);
    }

    function _on_created()
    {
        parent::_on_created();

        return $this;
    }

    /**
     *
     */
    function _on_deleted()
    {
        if(!$this->skip_invoice_update)
        {
            //update the invoice-sum so it will always contain the actual sum
            $invoice = new org_openpsa_invoices_invoice_dba($this->invoice);
            $invoice->sum = $invoice->get_invoice_sum();
            $invoice->update();
        }
        parent::_on_deleted();
    }

    /**
     *
     */
    function _on_updated()
    {
        if(!$this->skip_invoice_update)
        {
            //update the invoice-sum so it will always contain the actual sum
            $invoice = new org_openpsa_invoices_invoice_dba($this->invoice);
            $invoice->sum = $invoice->get_invoice_sum();
            $invoice->update();
        }

        return true;
    }
}
?>