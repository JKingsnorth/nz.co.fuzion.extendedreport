<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */
class CRM_Extendedreport_Form_Report_Contribute_AggregateDetails extends CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates {
  protected $_temporary = '  ';
  protected $_baseTable = 'civicrm_contact';
  protected $_baseEntity = 'contribution';
  protected $_noFields = TRUE;
  protected $_preConstrain = TRUE; // generate a temp table of contacts that meet criteria & then build temp tables


  protected $_charts = array(
    '' => 'Tabular',
    'barChart' => 'Bar Chart',
  );

  public $_drilldownReport = array('contribute/detail' => 'Link to Detail Report');

  function __construct() {
    $this->reportFilters = array(
      'civicrm_contribution' => array(
        'filters' => array(
          'receive_date' => array(),// just to make it first
          'catchment_date' => array(
            'title' => ts('Catchment Date Range'),
            'pseudofield' => TRUE,
            'default' => 12,
            'type' => CRM_Report_Form::OP_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
            'required' => TRUE,
          ),
          'behaviour_type' => array(
            'title' => ts('Donor Behavior'),
            'pseudofield' => TRUE,
            'default' => 12,
            'type' => CRM_Report_Form::OP_STRING,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'required' => TRUE,
            'options' => array(
              'renewed' => 'Renewed Donors',
              'new' => 'New Donor',
              'lapsed' => 'Lapsed Donors')
          ),

        )
      ),
    );
    $this->_columns =  array_merge_recursive($this->reportFilters, $this->getContributionColumns(array(
        'fields' => TRUE,
        'order_by' => FALSE,
      )))
    + $this->getContactColumns();
   // $this->_columns['civicrm_contribution']['filters'] ['receive_date']['pseudofield'] = TRUE;
    $this->_aliases['civicrm_contact']  = 'civicrm_report_contact';
    $this->_tagFilter = TRUE;
    $this->_groupFilter = TRUE;
    parent::__construct();
  }

  function preProcess() {
    parent::preProcess();
  }

  function from(){
    parent::from();
  }

  function fromClauses( ) {
    if($this->_preConstrained){
      return $this->constrainedFromClause();
    }
    else{
      return array(
        'contribution_from_contact',
        'entitytag_from_contact',
      ) + $this->constrainedFromClause();
    }
  }

  function constrainedFromClause(){
    $this->_ranges = array(
      'interval_0' => array()
    );
    $dateFields = array('receive_date' => '', 'catchment_date' => 'catchment_');
    foreach ($dateFields as $fieldName => $prefix){
      $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
      $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
      $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);
      $fromTime = CRM_Utils_Array::value("{$fieldName}_from_time", $this->_params);
      $toTime   = CRM_Utils_Array::value("{$fieldName}_to_time", $this->_params);
      list($from, $to) = CRM_Report_Form::getFromTo($relative, $from, $to,  $fromTime, $toTime);
      $this->_ranges['interval_0'][$prefix . 'from_date'] = DATE('Y-m-d', strtotime($from));
      $this->_ranges['interval_0'][$prefix . 'to_date'] = DATE('Y-m-d', strtotime($to));
    }
    $this->_statuses = array($this->_params['behaviour_type_value']);
    return array(
      'single_contribution_comparison_from_contact'
    );
  }

  function select(){
    parent::select();
  }

  function where() {
    parent::where();
  }

  function groupBy() {
    parent::groupBy();
    // not sure why this would be in this function - copy & paste
    $this->assign('chartSupported', TRUE);
  }

  function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    return $statistics;
  }

  function postProcess() {
    parent::postProcess();
  }


}

