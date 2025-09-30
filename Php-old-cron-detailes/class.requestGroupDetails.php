<?php
/************************************************************************
* @Class Name	: RequestGroupDetails
* @Created on	: Sep 07, 2019 (Saturday) 09:59:17
* @Created By	: Sabaresan M
* @Description	: Maintaining Status At Group Level
*
**************************************************************************/
fileRequire("pdo/class.commonDB.php");
class requestGroupDetails extends commonDB
{
	//common variable
	var $_Oconnection;
	var $_IcountLoop;

	//input data
	var $_IrequestGroupId;
	var $_IairlinesRequestId;
	var $_ItransactionMasterId;
	var $_IrequestMasterHistoryId;
	var $_IseriesGroupId;
	var $_Smaterialization;
	var $_Spolicy;
	var $_SresponseFare;
	var $_SthesholdPolicyId;
	var $_SthresholdFare;
	var $_Sremarks;
	var $_SgroupStatus;
	var $_SgroupContract;
	var $_AgroupStatus;

	//output data
	var $_ArequestGroupDetails;

	//contructor for the RequestGroupDetails class
	function __construct($_Oconnection = null,$_IcountLoop = 0,$_IreqGoupId = 0,$_IairlineReqId = 0,$_ItransMastId = 0,$_IreqMastHistId = 0
						,$_IseriesGroupId = 0,$_Smaterialization = '',$_Spolicy = '',$_SresponseFare = 0,$_IthresholdPolicyId = 0
						,$_IthresholdFare = 0,$_Sremarks = '',$_SgroupStatus = '',$_SorderBy = '',$_ArequestGroupDetails = [])
	{
		$this->_Oconnection = $_Oconnection;
		$this->_IcountLoop= $_IcountLoop;
		$this->_IrequestGroupId = $_IreqGoupId;
		$this->_IairlinesRequestId = $_IairlineReqId;
		$this->_ItransactionMasterId = $_ItransMastId;
		$this->_IrequestMasterHistoryId = $_IreqMastHistId;
		$this->_IseriesGroupId = $_IseriesGroupId;
		$this->_Smaterialization = $_Smaterialization;
		$this->_Spolicy = $_Spolicy;
		$this->_SresponseFare = $_SresponseFare;
		$this->_IthesholdPolicyId = $_IthresholdPolicyId;
		$this->_IthresholdFare = $_IthresholdFare;
		$this->_Sremarks = $_Sremarks;
		$this->_SgroupStatus = $_SgroupStatus;
		$this->_SorderBy = $_SorderBy;
		$this->_ArequestGroupDetails= $_ArequestGroupDetails;
		$this->_AupdateCondition = array("request_group_id","transaction_master_id","airlines_request_id","series_group_id");
		$this->_IgroupStatus = 0;
		$this->_INcondition = '';
		$this->_SgroupContract = '';
		$this->_AgroupStatus = array();
	}
	
	public static function _getObjReqGroupDetails($_Oconnection,$_IairlinesRequestId,$_ItransactionMasterId){
		return new requestGroupDetails($_Oconnection, 0,0,$_IairlinesRequestId,$_ItransactionMasterId,0,0,'','',0,0,0,'','','',[]);
		
	}

	
	//insert the details into RequestGroupDetails table
	function _insertRequestGroupDetails()
	{
		global $CFG;
		$this->_IgroupStatus = $this->_SgroupStatus;
		$this->_IairlinesRequestId = (isset($this->_IairlineRequestId) && $this->_IairlineRequestId!=0) ? $this->_IairlineRequestId : $this->_IairlinesRequestId ;

		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_group_details'];
		//Insert values 
		$_AtableValue = array();
		$_AtableValue = $this->_prepareColumn($_StableName,$this,"INSERT");
		
		if ( DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AtableValue,'DB_AUTOQUERY_INSERT')) )
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return FALSE;
		}
		else
		{
			$this->_IrequestGroupId = $this->_Oconnection->lastInsertId();
			return TRUE;
		}
	}

	//get the details from RequestGroupDetails table
	function _selectRequestGroupDetails()
	{
		global $CFG;
		$this->_IgroupStatus = $this->_SgroupStatus;
		$this->_IairlinesRequestId = (isset($this->_IairlineRequestId) && $this->_IairlineRequestId!=0) ? $this->_IairlineRequestId : $this->_IairlinesRequestId ;

		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_group_details'];
		$_AtableField = array(
								"request_group_id",
								"airlines_request_id",
								"transaction_master_id",
								"request_master_history_id",
								"series_group_id",
								"materialization",
								"policy",
								"response_fare",
								"theshold_policy_id",
								"threshold_fare",
								"remarks",
								"group_status",
								"group_contract"
							);
		if($this->_SselectedField != "")
			$_AtableField = explode(',',$this->_SselectedField);	
		$_AconditionValue = array();
		$_AselectField = array_combine($_AtableField,$_AtableField);
		$_AconditionValue = $this->_prepareColumn($_StableName,$this,"SELECT");
		$this->_Sremarks=htmlentities($this->_Sremarks, ENT_QUOTES);
		if(($this->_INcondition == 'IN') && !empty($this->_IseriesGroupId))
		{
			$_AconditionValue['series_group_id'] = array();
			$_AconditionValue['series_group_id']['condition']= 'IN';
			$_AconditionValue['series_group_id']['value'] = explode(',',$this->_IseriesGroupId);
		}
		if(in_array(strtoupper($this->_INcondition), array("IN","NOT IN")) && !empty($this->_IgroupStatus))
		{
			$_AconditionValue['group_status'] = array();
			$_AconditionValue['group_status']['condition'] = $this->_INcondition;
			$_AconditionValue['group_status']['value'] = explode(',',$this->_IgroupStatus);
		}
		if(in_array(strtoupper($this->_INcondition), array("IN","NOT IN")) && !empty($this->_AgroupStatus))
		{
			$_AconditionValue['group_status'] = array();
			$_AconditionValue['group_status']['condition'] = $this->_INcondition;
			$_AconditionValue['group_status']['value'] = explode(',',$this->_AgroupStatus);
		}
		if(($this->_INcondition == 'IN') && !empty($this->_IairlineRequestId))
		{
			$_AconditionValue['airlines_request_id'] = array();
			$_AconditionValue['airlines_request_id']['condition']= 'IN';
			$_AconditionValue['airlines_request_id']['value'] = explode(',',$this->_IairlineRequestId);
		}		
		if(DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AselectField,'DB_AUTOQUERY_SELECT',$_AconditionValue,'',array(),$this->_SorderBy)))
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return false;
		}
		$this->_IcountLoop=$result->numRows();
	
		if ($this->_IcountLoop > 0)
		{
			$this->_ArequestGroupDetails=$result->fetchAll(DB_FETCHMODE_ASSOC);
			$_AlastIndexed = array();
			$_AlastIndexed = end($this->_ArequestGroupDetails);
			$this->_IrequestGroupId = $_AlastIndexed['request_group_id'];
			$this->_IairlinesRequestId = $_AlastIndexed['airlines_request_id'];
			$this->_ItransactionMasterId = $_AlastIndexed['transaction_master_id'];
			$this->_IrequestMasterHistoryId = $_AlastIndexed['request_master_history_id'];
			$this->_IseriesGroupId = $_AlastIndexed['series_group_id'];
			$this->_Smaterialization = $_AlastIndexed['materialization'];
			$this->_Spolicy = $_AlastIndexed['policy'];
			$this->_SresponseFare = $_AlastIndexed['response_fare'];
			$this->_SthesholdPolicyId = $_AlastIndexed['theshold_policy_id'];
			$this->_SthresholdFare = $_AlastIndexed['threshold_fare'];
			$this->_Sremarks = $_AlastIndexed['remarks'];
			$this->_SgroupStatus = $_AlastIndexed['group_status'];
			$this->_IgroupStatus = $_AlastIndexed['group_status'];
		}
		return $this->_ArequestGroupDetails;
	}

	//change the details in RequestGroupDetails table
	function _updateRequestGroupDetails()
	{
		global $CFG;
		$this->_IgroupStatus = $this->_SgroupStatus;
		$this->_IairlinesRequestId = (isset($this->_IairlineRequestId) && $this->_IairlineRequestId!=0) ? $this->_IairlineRequestId : $this->_IairlinesRequestId ;

		$this->_Sremarks=htmlentities($this->_Sremarks, ENT_QUOTES);
		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_group_details'];
		//Update field
		$_AupdateField = $this->_prepareColumn($_StableName,$this,"UPDATE");
		$_AconditionValue = array();
		$this->_AupdateCondition = array_flip($this->_AupdateCondition);
		$_AconditionValue = array_intersect_key($_AupdateField,$this->_AupdateCondition);
		$_AupdateField = array_diff_key($_AupdateField,$this->_AupdateCondition);
		/* Setting custom conditons other than equal to*/
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_IrequestGroupId))
		{
			$_AconditionValue['request_group_id'] = array();
			$_AconditionValue['request_group_id']['condition'] = $this->_INcondition;
			$_AconditionValue['request_group_id']['value'] = explode(',',$this->_IrequestGroupId);
		}
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
	
	//delete the details in RequestGroupDetails table
	function _deleteRequestGroupDetails()
	{
		global $CFG;
		$this->_IgroupStatus = $this->_SgroupStatus;
		$this->_IairlinesRequestId = (isset($this->_IairlineRequestId) && $this->_IairlineRequestId!=0) ? $this->_IairlineRequestId : $this->_IairlinesRequestId ;

		$_AconditionValue = array();
		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_group_details'];
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
	//copy the fetched details into request time line table
	function _copyRequestGroupDetails()
	{
		global $CFG;
		$this->_IgroupStatus = $this->_SgroupStatus;
		$this->_IairlinesRequestId = (isset($this->_IairlineRequestId) && $this->_IairlineRequestId!=0) ? $this->_IairlineRequestId : $this->_IairlinesRequestId ;

		//Table Name 
		$_StableName = $CFG['db']['tbl']['request_group_details'];
		//Insert values 
		$_AtableValue = array();
		$_AtableValue = $this->_ArequestGroupDetails;
		foreach($this->_ArequestGroupDetails as $_Ikey => $_AtableValue)
		{
			if (DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AtableValue,'DB_AUTOQUERY_INSERT')))
			{
				fileWrite($result,"SqlError","a+");
				fileWrite($this->_Oconnection->last_query,"SqlError","a+");
				return FALSE;
			}
			else
			{
				$this->_IrequestGroupId = $this->_Oconnection->lastInsertId();
				if(isset($this->_AcontractData) && !empty($this->_AcontractData) && $this->_AcontractData[$_Ikey]['seriesGroupId'] == $_AtableValue['series_group_id'])
					$this->_AcontractData[$_Ikey]['newRequestGroupId']=$this->_IrequestGroupId;

				$this->_copyGroupContractDetails($_AtableValue['request_group_id'], $this->_IrequestGroupId);
			}
		}
		return true;
	}

	#copy group contract details for creating child request or resubmitted requests.
	public function _copyGroupContractDetails($_IrequestGroupId, $_InewRequestGroupId,$_ScloneStatus = 'N')
	{
		if(!$_IrequestGroupId) return false;

		global $CFG;
		$_AconditionValue = [];
		$_StableName = $CFG['db']['tbl']['group_contract_details'];
		$_AconditionValue['request_group_id'] = $_IrequestGroupId;
		$_AgroupContract = $this->_Oconnection->_performQuery($_StableName, [], 'DB_AUTOQUERY_SELECT', $_AconditionValue);
		foreach($_AgroupContract as $_AcontractDetails)
		{
			#insert the updated contract for partially resubmit and partial negotiate
			if($_ScloneStatus == 'Y'){
				if(!empty($_AcontractDetails['updated_contract']))
				{
					$_AcontractDetails['updated_contract'] =  array_diff_key(json_decode($_AcontractDetails["updated_contract"], 1), array_flip(["time_line_matrix"]));
					if(array_key_exists("days_to_departure", is_array($_AcontractDetails['updated_contract']) ? $_AcontractDetails['updated_contract'] : []))  unset($_AcontractDetails['updated_contract']["days_to_departure"]);
					$_AcontractDetails && $_AcontractDetails['updated_contract'] =  json_encode($_AcontractDetails['updated_contract']);
				}
			}
			$_InewRequestGroupId && $_AcontractDetails['request_group_id'] = $_InewRequestGroupId;
			if(DB::isError($result = $this->_Oconnection->autoExecute($_StableName, $_AcontractDetails, 'DB_AUTOQUERY_INSERT')) )
			{
				fileWrite($result,"SqlError","a+");
				fileWrite($this->_Oconnection->last_query,"SqlError","a+");
				return false;
			}
		}
		return true;
	}
}
?>

