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
class CRM_Extendedreport_Form_Report_Contribute_KeyNumbers extends CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates {
  protected $_temporary = '  ';
  protected $_baseTable = 'civicrm_contact';
  protected $_noFields = TRUE;
  protected $_kpis = array();
  protected $_preConstrain = TRUE;
  protected $_comparisonType = 'prior';

  protected $_kpiDescriptors = array(
  );

  protected $_kpiSpecs = array(
    'donor_number' => array(
      'type' => CRM_Utils_Type::T_INT,
      'title' => 'Total Number of Donors',
      'contact_type_title' => 'Total Number of %1 Donors',
      'link_status' => 'every',
    ),
    'total_amount' => array(
      'type' => CRM_Utils_Type::T_MONEY,
      'title' => 'Amount Raised',
      'contact_type_title' => 'Amount Raised From %1s',
      'link_status' => 'every',
    ),
    'average_donation' => array(
      'type' => CRM_Utils_Type::T_MONEY,
      'title' => 'Average Donation',
      'contact_type_title' => 'Average Donation From %1s',
      'link_status' => 'every',
    ),
    'no_increased_donations' => array(
      'type' => CRM_Utils_Type::T_INT,
      'title' => 'Donors who Increased their donation',
      'contact_type_title' => '%1 Donors who Increased their donation',
      'link_status' => 'increased',
    ),
  );
  /*
   * we'll store these as a property so we only have to calculate once
   */
  protected $_currentYear = NULL;
  protected $_lastYear = NULL;
  protected $_yearBeforeLast = NULL;
  protected $_contributionWhere = '';

  protected $_charts = array(
  );
/**
 *
 * @var array statuses to show on the report
 */
  protected $_statuses = array('increased', 'every');

  /**
   *
   * @var array aggregates to calculate for the report
   */
  protected $_aggregates = array('every');

  public $_drilldownReport = array('contribute/detail' => 'Link to Detail Report');

  function __construct() {
    foreach ($this->_kpiSpecs as $specKey => $specs){
      $contactTypes =  CRM_Contribute_PseudoConstant::contactType();
      $this->_kpiDescriptors[$specKey] = ts($specs['title']);
      foreach ($contactTypes as $contactType => $contactTypeName){
        $this->_kpiDescriptors[$specKey . '__' . strtolower($contactType)] =
          ts( $specs['contact_type_title'],
              array($contactType, 'String')
          );
      }
    }
     $this->_currentYear = date('Y');
     $this->_lastYear = $this->_currentYear - 1;
     $this->_yearBeforeLast = $this->_currentYear - 2;
     $this->_columns =  array('pseudotable' => array(
        'name' => 'civicrm_report_instance',
         'filters' => array(
           'report_options' => array(
             'pseudofield' => TRUE,
             'operatorType' => CRM_Report_Form::OP_MULTISELECT,
             'options' => $this->_kpiDescriptors,
             'title' => ts('Selected Performance Indicators'),
             'default' => array(
               'donor_number',
               'total_amount',
               'total_amount__individual',
               'average_donation__individual',
               'no_increased_donations__individual'
               )
             ),
           )
       ))
       + $this->getContributionColumns(array(
       'fields' => FALSE,
       'order_by' => FALSE,
     ));
   //  unset($this->_columns['civicrm_contribution']['filters']['receive_date']);
     $this->_columns['civicrm_contribution']['filters']['receive_date']['default'] = array(
       'from' => date('m/d/Y', strtotime('first day of January this year')),
       'to' => date('m/d/Y')
     );
     $this->_columns['civicrm_contribution']['filters']['receive_date']['title'] = 'Report Main Date Range';
     $this->_columns['civicrm_contribution']['filters']['receive_date']['pseudofield'] = TRUE;
     $this->_aliases['civicrm_contact']  = 'civicrm_report_contact';
     $this->_tagFilter = TRUE;
     $this->_groupFilter = TRUE;
     parent::__construct();
  }


  function preProcess() {
    parent::preProcess();
  }
  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_ExtendedReport::getAvailableJoins()
   */
  function getAvailableJoins() {
    return parent::getAvailableJoins() + array(
      'compile_key_stats' => array(
        'callback' => 'compileKeyStats'
      ),
    );
  }

  function from(){
    parent::from();
  }

  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_ExtendedReport::beginPostProcess()
   */
  function beginPostProcess() {
    parent::beginPostProcess();
    $this->_kpiDescriptors = array_intersect_key($this->_kpiDescriptors, array_flip($this->_params['report_options_value']));
    if(!empty($this->_params['receive_date_relative'])){
      //render out relative dates here
      $rels = explode('.', $this->_params['receive_date_relative']);
      $fromTo = (CRM_Utils_Date::relativeToAbsolute($rels[0], $rels[1]));
      $this->_params['receive_date_from'] = $this->_params['receive_date_from_stash'] =  $fromTo['from'];
      $this->_params['receive_date_to'] =  $fromTo['to'];
     // unset($this->_params['receive_date_relative']);
    }
    // receive date from & to get unset in parent class. I'm a bit scared to change that right
    // now so will hack around it by stashing them for a bit
    $this->_params['receive_date_from_stash']  = $this->_params['receive_date_from'];
    $this->_params['receive_date_to_stash']  = $this->_params['receive_date_to'];
    $this->constructRanges(array(
      'primary_from_date' => 'receive_date_from',
      'primary_to_date' => 'receive_date_to',
      'offset_unit' => 'year',
      'offset' => 1,
      'comparison_offset' => '1',
      'comparison_offset_unit' => 'year',
      'no_periods' => 2,
      'statuses' => array('increased'),
    )
    );
  }
  /**
   * As per last function the ContributionAggregate class is unsetting 'receive_date_from, receive_date_to
   * I think the introduction of 'pseudofield' may have made that obsolete but would need to test
   * making a change (test all child reports) so for now lets just reset & call parent
   *
   * @param array $statistics
   */
  function filterStat(&$statistics) {
    $this->_params['receive_date_from'] = $this->_params['receive_date_from_stash'];
    $this->_params['receive_date_to'] = $this->_params['receive_date_to_stash'];
    parent::filterStat($statistics);
  }
  /**
   * Call the functions that compose our KPI results
   */
  function compileKeyStats(){
    $tempTable = $this->generateSummaryTable();
    $this->calcDonorNumber();
    $this->calcDonationTotal();
    $this->calcContactTypeDonationTotal();
    $this->calcContactTypeDonationNumber();
    $this->calcIncreasedGivers();
    $this->calcContactTypeIncreasedGivers();
    $this->_from = " FROM $tempTable";
    $this->stashValuesInTable($tempTable);
  }

  /**
   * Specify the fromClauses required in this report
   * @return
   *
   */
  function fromClauses( ) {
    if($this->_preConstrained){
      return $this->constrainedFromClause();
    }
    else{
      return array(
        'contribution_from_contact',
        'entitytag_from_contact',
      );
    }
  }
  /**
   * We need to calculate increases using the parent
   * @return
   */
  function constrainedFromClause(){
    return array(
      'timebased_contribution_from_contact' => array('',
        array('extra_fields' => array('contact_type' => 'contact_type VARCHAR(50) NULL,')
        )
      ),
      'compile_key_stats' => array(array()),
    );
  }

  function select(){
    if(!$this->_preConstrained){
      parent::select();
    }
    else{
      $thisYear = FALSE;
      if(strtotime($this->_params['receive_date_from']) >= strtotime('last day of december last year')){
        $thisYear = TRUE;
      }
      $columns = array(
        'description' => ts(''),
        'this_year' => $thisYear ? ts('This Year') : ts('Main Date Range'),
        'percent_change' => ts('Percent Change'),
        'last_year' => $thisYear ? ts ('Last Year') : ts('One year Prior Range'),
      );
      foreach ($columns as $column => $title){
        $select[]= " $column ";
        $this->_columnHeaders[$column] = array('title' => $title);
      }
      $this->_select = " SELECT " . implode(', ', $select);
    }
  }

/**
 * Generate empty temp table
 * (non-PHPdoc)
 * @see CRM_Extendedreport_Form_Report_ExtendedReport::generateTempTable()
 */
  function generateSummaryTable(){
    $tempTable = 'civicrm_report_temp_kpi' . date('d_H_I') . rand(1, 10000);
    $sql = " CREATE {$this->_temporary} TABLE $tempTable (
      description  VARCHAR(50) NULL,
      this_year INT(10) NULL,
      last_year INT(10) NULL,
      percent_change INT(10) NULL
    )";
    CRM_Core_DAO::executeQuery($sql);
    return $tempTable;
  }

 /**
  * Add data about number of donors
  */
  function calcDonorNumber(){
    $sql = "
      SELECT every, to_date
      {$this->_from}
      ;
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $year = date('Y', strtotime($result->to_date));
      $this->_kpis[$year]['donor_number'] = $result->every;
    }
  }

  /**
   * Add data about number of donors
   */
  function calcDonationTotal(){
      $sql = "
      SELECT every_total, to_date
      {$this->_from}
      ;
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $year = date('Y', strtotime($result->to_date));
      $this->_kpis[$year]['total_amount'] = $result->every_total;
    }
  }

  /**
   * Add data about number of donors
   */
  function calcContactTypeDonationTotal(){
    $sql = "
      SELECT
        contact_type,
        COALESCE(sum(interval_0_amount),0) as this_year,
        COALESCE(sum(interval_1_amount),0) as last_year
      FROM {$this->_tempTables['civicrm_contribution_multi']} cont
      INNER JOIN civicrm_contact c ON c.id = cont.cid
      GROUP BY contact_type
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $this->_kpis[$this->_currentYear]['total_amount__' . strtolower($result->contact_type)] = $result->this_year;
      $this->_kpis[$this->_lastYear]['total_amount__' . strtolower($result->contact_type)] = $result->last_year;
    }
  }

  /**
   * Add data about number of donors
   */
  function calcContactTypeDonationNumber(){
    $sql = "
      SELECT
        contact_type,
        COALESCE(sum(interval_0_every),0) as this_year,
        COALESCE(sum(interval_1_every),0) as last_year
      FROM {$this->_tempTables['civicrm_contribution_multi']} cont
      INNER JOIN civicrm_contact c ON c.id = cont.cid
      GROUP BY contact_type
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $contactType = strtolower($result->contact_type);
      $this->_kpis[$this->_currentYear]['donor_number__' . $contactType] = $result->this_year;
      $this->_kpis[$this->_lastYear]['donor_number__' . $contactType] = $result->last_year;
      $this->calcAverageValues($contactType);
    }
    $this->calcAverageValues();
  }
/**
 * Calculate averges per contact type
 *
 */
  function calcAverageValues($contactType = ''){
    if(!empty($contactType)){
      $contactType = '__' . $contactType;
    }
    $years = array($this->_currentYear, $this->_lastYear);
    foreach($years as $year){
      if(empty($this->_kpis[$year]['donor_number' . $contactType])){
        $this->_kpis[$year]['average_donation' . $contactType] = 0;
      }
      else{
        $this->_kpis[$year]['average_donation' . $contactType]
          = $this->_kpis[$year]['total_amount' . $contactType]
          / $this->_kpis[$year]['donor_number' . $contactType];
      }
    }
  }
  /**
   * Add data about number of donors
   */
  function calcIncreasedGivers(){
    $sql = "
    SELECT increased, to_date
    {$this->_from}
    ;
    ";
    $result = CRM_Core_DAO::executeQuery($sql);
    while($result->fetch()){
      $year = date('Y', strtotime($result->to_date));
      $this->_kpis[$year]['no_increased_donations'] = $result->increased;
    }
  }


  /**
   * Add data about number of donors
   */
  function calcContactTypeIncreasedGivers(){
    $sql = "
      SELECT
        contact_type,
        COALESCE(sum(interval_0_increased),0) as this_year,
        COALESCE(sum(interval_1_increased),0) as last_year
      FROM {$this->_tempTables['civicrm_contribution_multi']} cont
      INNER JOIN civicrm_contact c ON c.id = cont.cid
      GROUP BY contact_type
    ";
        $result = CRM_Core_DAO::executeQuery($sql);
        while($result->fetch()){
        $this->_kpis[$this->_currentYear]['no_increased_donations__' . strtolower($result->contact_type)] = $result->this_year;
        $this->_kpis[$this->_lastYear]['no_increased_donations__' . strtolower($result->contact_type)] = $result->last_year;
      }
    }

/**
 * We are just stashing our array of values into a table here - we could potentially render without a table
 * but this seems simple.
 */
  function stashValuesInTable($temptable){
    foreach ($this->_kpiDescriptors as $key => $description){
      $lastYearValue = empty($this->_kpis[$this->_lastYear][$key]) ? 0 : $this->_kpis[$this->_lastYear][$key];
      $thisYearValue = empty($this->_kpis[$this->_currentYear][$key]) ? 0 : $this->_kpis[$this->_currentYear][$key];
      if($lastYearValue && $thisYearValue){
        $percent = ($this->_kpis[$this->_currentYear][$key]-  $this->_kpis[$this->_lastYear][$key])/ $this->_kpis[$this->_lastYear][$key] * 100;
      }
      else{
        $percent = 0;
      }
      $insert[] = "
        ('{$description}'
        , $thisYearValue
        , $lastYearValue
        , $percent
        )";
    }
    $insertClause = implode(',', $insert);
    $sql = "
        INSERT INTO $temptable VALUES $insertClause
      ";
    CRM_Core_DAO::executeQuery($sql);
  }
  /**
   * (non-PHPdoc)
   * @see CRM_Extendedreport_Form_Report_Contribute_ContributionAggregates::alterDisplay()
   */
  function alterDisplay(&$rows){
    foreach ($rows as &$row){
      $availableKpi = array_flip($this->_kpiDescriptors);
      $kpiName = $availableKpi[$row['description']];
      $kpiDetails = explode('__', $kpiName);
      if($this->_kpiSpecs[$kpiDetails[0]]['type'] == CRM_Utils_Type::T_MONEY){
        $row['this_year'] = CRM_Utils_Money::format($row['this_year']);
        $row['last_year'] = CRM_Utils_Money::format($row['last_year']);
      }
      if($row['percent_change'] == 0){
        $row['percent_change'] = 'n/a';
      }
      else{
        $row['percent_change'] = $row['percent_change'] . '%';
      }
      // we are dealing with rows not columns so this differs from parent approach
      $queryURL = "reset=1&force=1";
      foreach ($this->_potentialCriteria as $criterion){
        if(empty($this->_params[$criterion])){
          continue;
        }
        $criterionValue = is_array($this->_params[$criterion]) ? implode(',', $this->_params[$criterion]) : $this->_params[$criterion];
        $queryURL .= "&{$criterion}=" . $criterionValue;
      }
      if(!empty($kpiDetails[1])){
        $queryURL .= "&contact_type_value=" . $kpiDetails[1];
      }
      $years = array(
        0 => 'this_year',
        1 => 'last_year',
      );
      foreach($years as $interval => $year){
        $queryURLYear ="&comparison_date_from=". date('YmdHis', strtotime($this->_ranges['interval_' . $interval]['comparison_from_date']))
        . "&comparison_date_to=". date('YmdHis', strtotime($this->_ranges['interval_' . $interval]['comparison_to_date']))
        . "&receive_date_from=" . date('YmdHis', strtotime($this->_ranges['interval_' . $interval]['from_date']))
        . "&receive_date_to=" . date('YmdHis', strtotime($this->_ranges['interval_' . $interval]['to_date']));
        ;
        $url = CRM_Report_Utils_Report::getNextUrl(
          'contribute/aggregatedetails',
          $queryURL
          . "&behaviour_type_value=" . $this->_kpiSpecs[$kpiDetails[0]]['link_status']
          . $queryURLYear,
          $this->_absoluteUrl,
          NULL,
          $this->_drilldownReport
        );
        $row[$year .'_link'] = $url;
      }
      }
    parent::alterDisplay($rows);
  }


}


