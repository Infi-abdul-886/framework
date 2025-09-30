<?php
/************************************************************************
* @Class Name	: RequestMasterHistory
* @Created on	: Sep 06, 2019 (Friday) 19:24:16
* @Created By	: Sabaresan M
* @Description	: 
*
**************************************************************************/
fileRequire("pdo/class.commonDB.php");
class requestMasterHistory extends commonDB
{
	//common variable
	var $_Oconnection;
	var $_IcountLoop;

	//input data
	var $_IrequestMasterHistoryId;
	var $_IrequestMasterId;
	var $_StripType;
	var $_SuserCurrency;
	var $_IrequestFare;
	var $_IexchangeRate;
	var $_SrequestedDate;
	var $_Sremarks;
	var $_IrequestRaisedBy;
	var $_Scabin;
	var $_SseriesWeekdays;
	var $_IgroupCategoryId;
	var $_SflexibleOnDates;
	var $_ImodifyStatus;
	var $_IactualRequestStatus;
	var $_Dcondition;
	var $_INcondition;

	//output data
	var $_ArequestMasterHistory;

	//contructor for the RequestMasterHistory class
	function __construct()
	{
		$this->_Oconnection;
		$this->_IcountLoop=0;
		$this->_IrequestMasterHistoryId = 0;
		$this->_IrequestMasterId = 0;
		$this->_StripType = '';
		$this->_SuserCurrency = '';
		$this->_IrequestFare = 0;
		$this->_IexchangeRate = 0;
		$this->_SrequestedDate = '';
		$this->_Sremarks = '';
		$this->_IrequestRaisedBy = 0;
		$this->_Scabin = '';
		$this->_SseriesWeekdays = '';
		$this->_IgroupCategoryId = 0;
		$this->_SflexibleOnDates = '';
		$this->_ImodifyStatus = 0;
		$this->_IactualRequestStatus = 0;
		$this->_Dcondition = '';
		$this->_SselectedField = '';
		$this->_ArequestMasterHistory=array();
		$this->_AupdateCondition = array("request_master_history_id");
		$this->_INcondition ='';
	}
	
	//insert the details into RequestMasterHistory table
	function _insertRequestMasterHistory()
	{
		global $CFG;
		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_master_history'];
		//Insert values 
		$_AtableValue = array();
		$_AtableValue = $this->_prepareColumn($_StableName,$this,"INSERT");
		$this->_Sremarks=htmlentities($this->_Sremarks, ENT_QUOTES);
		if ( DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AtableValue,'DB_AUTOQUERY_INSERT')) )
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return FALSE;
		}
		else
		{
			$this->_IrequestMasterHistoryId = $this->_Oconnection->lastInsertId();
			return TRUE;
		}
	}

	//get the details from RequestMasterHistory table
	function _selectRequestMasterHistory()
	{
		global $CFG;
		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_master_history'];
		$_AtableField = array(
								"request_master_history_id",
								"request_master_id",
								"trip_type",
								"user_currency",
								"request_fare",
								"exchange_rate",
								"requested_date",
								"remarks",
								"request_raised_by",
								"cabin",
								"series_weekdays",
								"group_category_id",
								"flexible_on_dates",
								"modify_status",
								"actual_request_status"
							);
		$_AconditionValue = array();
		if($this->_SselectedField != "")
			$_AtableField = explode(',',$this->_SselectedField);
		$_AselectField = array_combine($_AtableField,$_AtableField);	
		$_AconditionValue = $this->_prepareColumn($_StableName,$this,"SELECT");
		/* Setting custom conditons other than equal to*/
		if(strtoupper(trim($this->_Dcondition)) == "BETWEEN")
		{
			$_AconditionValue['requested_date'] = array();
			$_AconditionValue['requested_date']['condition'] = 'between';
			$_AconditionValue['requested_date']['value'] = explode('AND', $this->_SrequestedDate);
		}
		//modify status restrict reject and decline
		if(!empty($this->_INcondition) && in_array(strtoupper($this->_INcondition), array("IN","NOT IN")) && !empty($this->_ImodifyStatus))
		{
			$_AconditionValue['modify_status'] = array();
			$_AconditionValue['modify_status']['condition'] = $this->_INcondition;
			$_AconditionValue['modify_status']['value'] = explode(',',$this->_ImodifyStatus);
		}
		if(DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AselectField,'DB_AUTOQUERY_SELECT',$_AconditionValue)))
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return false;
		}
		$this->_IcountLoop=$result->numRows();
		
		if ($this->_IcountLoop > 0)
		{
			$this->_ArequestMasterHistory=$result->fetchAll(DB_FETCHMODE_ASSOC);
			$_AlastIndexed = array();
			$_AlastIndexed = end($this->_ArequestMasterHistory);
			$this->_IrequestMasterHistoryId = $_AlastIndexed['request_master_history_id'];
			$this->_IrequestMasterId = $_AlastIndexed['request_master_id'];
			$this->_StripType = $_AlastIndexed['trip_type'];
			$this->_SuserCurrency = $_AlastIndexed['user_currency'];
			$this->_IrequestFare = $_AlastIndexed['request_fare'];
			$this->_IexchangeRate = $_AlastIndexed['exchange_rate'];
			$this->_SrequestedDate = $_AlastIndexed['requested_date'];
			$this->_Sremarks = $_AlastIndexed['remarks'];
			$this->_IrequestRaisedBy = $_AlastIndexed['request_raised_by'];
			$this->_Scabin = $_AlastIndexed['cabin'];
			$this->_SseriesWeekdays = $_AlastIndexed['series_weekdays'];
			$this->_IgroupCategoryId = $_AlastIndexed['group_category_id'];
			$this->_SflexibleOnDates = $_AlastIndexed['flexible_on_dates'];
			$this->_ImodifyStatus = $_AlastIndexed['modify_status'];
			$this->_IactualRequestStatus = $_AlastIndexed['actual_request_status'];
		}
		return $this->_ArequestMasterHistory;
	}

	//change the details in RequestMasterHistory table
	function _updateRequestMasterHistory()
	{
		global $CFG;
		$this->_Sremarks=htmlentities($this->_Sremarks, ENT_QUOTES);
		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_master_history'];
		//Update field
		$_AupdateField = $this->_prepareColumn($_StableName,$this,"UPDATE");
		$_AconditionValue = array();
		$this->_AupdateCondition = array_flip($this->_AupdateCondition);
		$_AconditionValue = array_intersect_key($_AupdateField,$this->_AupdateCondition);
		$_AupdateField = array_diff_key($_AupdateField,$this->_AupdateCondition);
		if (DB::isError($result= $this->_Oconnection->autoExecute($_StableName,$_AupdateField,'DB_AUTOQUERY_UPDATE',$_AconditionValue)))
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return false;
		}
		else
		{
			return true;
		}
	}
	
	//delete the details in RequestMasterHistory table
	function _deleteRequestMasterHistory()
	{
		global $CFG;
		$_AconditionValue = array();
		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_master_history'];
		$_AconditionValue = $this->_prepareColumn($_StableName,$this,"DELETE");
		if (DB::isError($result= $this->_Oconnection->autoExecute($_StableName,array(),'DB_AUTOQUERY_DELETE',$_AconditionValue)))
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return false;
		}
		else
		{
			return true;
		}
	}
	
}
?>
