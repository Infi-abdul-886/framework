<?php
/************************************************************************
* @Class Name	: PnrBlockingDetails
* @Created on	: Nov 06, 2013 (Wednesday) 10:25:23
* @Created By	: Kalaiselvi
* @Description	: Store the pnr blocking information
* @Modified by	: Ponseha
* @Modified date: Jun 30 2017
**************************************************************************/
fileRequire("pdo/class.commonDB.php");
class pnrBlockingDetails extends commonDB
{
	//common variable
	var $_Oconnection;
	var $_IcountLoop;

	//input data
	var $_IpnrBlockingId;
	var $_IrequestMasterId;
	var $_IrequestApprovedFlightId;
	var $_IviaFlightId;
	var $_Spnr;
	var $_InoOfAdult;
	var $_InoOfChild;
	var $_InoOfInfant;
	var $_IpnrAmount;
	var $_ApriceQuoteAt;
	var $_Sstatus;
	var $_ScreatedDate;
	var $_SdummyPNR;
	//output data
	var $_ApnrBlockingDetails;
	var $_InoOfFoc;
	var $_SupdateEmptyViaValue;
	var $_AupdateCondition;
	//contructor for the PnrBlockingDetails class
	function __construct()
	{
		$this->_Oconnection;
		$this->_IcountLoop=0;
		$this->_IpnrBlockingId = 0;
		$this->_IrequestMasterId = 0;
		$this->_IrequestApprovedFlightId = 0;
		$this->_IviaFlightId = 0;
		$this->_Spnr = '';
		$this->_InoOfAdult = 0;
		$this->_InoOfChild = 0;
		$this->_InoOfInfant = 0;
		$this->_InoOfFoc = 0;
		$this->_IpnrAmount = 0;
		$this->_ApriceQuoteAt='';
		$this->_Sstatus = '';
		$this->_ScreatedDate ='';
		$this->_SdummyPNR ='';
		$this->_ApnrBlockingDetails=array();
		$this->_SstatusPending = '';
		$this->_Dcondition = '';
		$this->_INcondition = '';
		$this->_SselectedField = '';
		$this->_SpnrMultiSelect = 'N';
		$this->_SupdateEmptyValue = 'N';
		$this->_SupdateEmptyViaValue = 'N';
		$this->_AupdateCondition = array("pnr_blocking_id","request_master_id","pnr");
	}
	
	//insert the details into PnrBlockingDetails table
	function _insertPnrBlockingDetails()
	{
		global $CFG;
		//Table Name 
		$_StableName = $CFG['db']['tbl']['pnr_blocking_details'];
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
			$this->_IpnrBlockingId = $this->_Oconnection->lastInsertId();
			return TRUE;
		}
	}

	//get the details from PnrBlockingDetails table
	function _selectPnrBlockingDetails()
	{
		global $CFG;

		$_StableName = $CFG['db']['tbl']['pnr_blocking_details'];
		$_AtableField = array(
							"pnr_blocking_id",
							"request_master_id",
							"request_approved_flight_id",
							"via_flight_id",
							"pnr",
							"no_of_adult",
							"no_of_child",
							"no_of_infant",
							"no_of_foc",
							"pnr_amount",
							"price_quote_at",
							"status",
							"created_date"
						);
		
		if($this->_SselectedField != "")
			$_AtableField = explode(',',$this->_SselectedField);	
		$_AconditionValue = array();
		$_AselectField = array_combine($_AtableField,$_AtableField);
		$_AconditionValue = $this->_prepareColumn($_StableName,$this,"SELECT");
		
		/* Setting custom conditons other than equal to*/
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_IrequestApprovedFlightId))
		{	
			$_AconditionValue['request_approved_flight_id'] = array();
			$_AconditionValue['request_approved_flight_id']['condition'] = $this->_INcondition;
			$_AconditionValue['request_approved_flight_id']['value'] = explode(',',$this->_IrequestApprovedFlightId);
		}
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_IrequestMasterId))
		{	
			$_AconditionValue['request_master_id'] = array();
			$_AconditionValue['request_master_id']['condition'] = $this->_INcondition;
			$_AconditionValue['request_master_id']['value'] = explode(',',$this->_IrequestMasterId);
		}
		
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_Spnr))
		{
			$_AconditionValue['pnr'] = array();
			$_AconditionValue['pnr']['condition'] = $this->_INcondition;
			$_AconditionValue['pnr']['value'] = explode(',',$this->_Spnr);
		}	
		
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_Sstatus))
		{
			$_AconditionValue['status'] = array();
			$_AconditionValue['status']['condition'] = $this->_INcondition;
			$_AconditionValue['status']['value'] = explode(',',$this->_Sstatus);
		}
		
		if(strtoupper(trim($this->_Dcondition)) == "BETWEEN")
		{
			$_AconditionValue['created_date'] = array();
			$_AconditionValue['created_date']['condition'] = 'between';
			$_AconditionValue['created_date']['value'] = explode('AND', $this->_ScreatedDate);
		}
		if($this->_SdummyPNR != '' && $this->_SdummyPNR == 'N')
		{
			$_AconditionValue['pnr'] = array();
			$_AconditionValue['pnr']['condition'] = ' NOT LIKE ';
			$_AconditionValue['pnr']['value'] = "%GROUP%";
		}
		
		if($this->_SstatusPending != '')
		{
			$_AconditionValue['UPPER(status)'] = array();
			$_AconditionValue['UPPER(status)']['condition'] = '!=';
			$_AconditionValue['UPPER(status)']['value'] = strtoupper($this->_SstatusPending);
		}
		if($this->_IrequestMasterId == 0 && $this->_Spnr != '' && $this->_SpnrMultiSelect=='N' && strstr($this->_Spnr,"GROUP")===FALSE)
		{
			fileWrite(print_r($this->_Spnr,1),"PnrSelectedWithoutRequestMasterId","a+");
			$_Apnr = array();
			/*If pnr condition value is created then pass that array as parameter for _getMaxRequestMasterId.
				Otherwise the pnr is pass as the argument for the _getMaxRequestMasterId */
			if(isset($_AconditionValue['pnr']))
				$_Apnr=$_AconditionValue['pnr'];
			else
				$_Apnr['pnr']=$this->_Spnr;
			$_AconditionValue['request_master_id'] = $this->_getMaxRequestMasterId($_Apnr);
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
			$this->_ApnrBlockingDetails=$result->fetchAll(DB_FETCHMODE_ASSOC);
			$_AlastIndexed = array();
			$_AlastIndexed = end($this->_ApnrBlockingDetails);
			$this->_IpnrBlockingId = $_AlastIndexed['pnr_blocking_id'];
			$this->_IrequestMasterId = $_AlastIndexed['request_master_id'];
			$this->_IrequestApprovedFlightId = $_AlastIndexed['request_approved_flight_id'];
			$this->_IviaFlightId = $_AlastIndexed['via_flight_id'];
			$this->_Spnr = $_AlastIndexed['pnr'];
			$this->_InoOfAdult = $_AlastIndexed['no_of_adult'];
			$this->_InoOfChild = $_AlastIndexed['no_of_child'];
			$this->_InoOfInfant = $_AlastIndexed['no_of_infant'];
			$this->_IpnrAmount = $_AlastIndexed['pnr_amount'];
			$this->_ApriceQuoteAt = $_AlastIndexed['price_quote_at'];
			$this->_Sstatus = $_AlastIndexed['status'];
			$this->_ScreatedDate = $_AlastIndexed['created_date'];
		}
		return $this->_ApnrBlockingDetails;
		
	}

	//change the details in PnrBlockingDetails table
	function _updatePnrBlockingDetails()
	{
		global $CFG;
		//Table Name 
		$_StableName = $CFG['db']['tbl']['pnr_blocking_details'];
		//Update field
		$_AupdateField = $this->_prepareColumn($_StableName,$this,"UPDATE");
		$_AconditionValue = array();
		$this->_AupdateCondition = array_flip($this->_AupdateCondition);
		$_AconditionValue = array_intersect_key($_AupdateField,$this->_AupdateCondition);
		$_AupdateField = array_diff_key($_AupdateField,$this->_AupdateCondition);
		
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_Spnr))
		{
			$_AconditionValue['pnr'] = array();
			$_AconditionValue['pnr']['condition'] = $this->_INcondition;
			$_AconditionValue['pnr']['value'] = explode(',',$this->_Spnr);
		}	
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_IrequestApprovedFlightId))
		{	
			$_AconditionValue['request_approved_flight_id'] = array();
			$_AconditionValue['request_approved_flight_id']['condition'] = $this->_INcondition;
			$_AconditionValue['request_approved_flight_id']['value'] = explode(',',$this->_IrequestApprovedFlightId);
		}
		if($this->_SupdateEmptyViaValue == 'Y'){
			$_AupdateField['via_flight_id'] = $this->_IviaFlightId;
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
	
	//delete the details in PnrBlockingDetails table
	function _deletePnrBlockingDetails()
	{
		global $CFG;
		$_AconditionValue = array();
		//Table Name 
		$_StableName = $CFG['db']['tbl']['pnr_blocking_details'];
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

	private function _getMaxRequestMasterId($_Apnr)
	{
		global $CFG;
		$_Aresult = array();
		$_StableName = $CFG['db']['tbl']['pnr_blocking_details'];
		$_AtableField = array('MAX(request_master_id) as request_master_id');
		$_AselectField = array_combine($_AtableField,$_AtableField);
		$_AconditionValue = array();
		/*Prepare the pnr condition value from argument to get the request master id */
		if(isset($_Apnr['pnr']))
			$_AconditionValue['pnr'] = $_Apnr['pnr'];
		else
			$_AconditionValue['pnr'] = $_Apnr;

		if(DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AselectField,'DB_AUTOQUERY_SELECT',$_AconditionValue)))
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return false;
		}
		$_Aresult=$result->fetchRow(DB_FETCHMODE_ASSOC);
		return $_Aresult['request_master_id'];
	}
	function _copyPnrBlockingDetails()
	{
		global $CFG;
		if(!empty($this->_ApnrBlockingDetails))
		{
			//Table Name 
			$_StableName = $CFG['db']['tbl']['pnr_blocking_details'];
			//Insert values 
			$_AtableValue = array();
			$_AtableValue = $this->_ApnrBlockingDetails;
			if ( DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AtableValue,'DB_AUTOQUERY_INSERT')) )
			{
				fileWrite($result,"SqlError","a+");
				fileWrite($this->_Oconnection->last_query,"SqlError","a+");
				return FALSE;
			}
			else
			{
				$this->_IpnrBlockingId = $this->_Oconnection->lastInsertId();
				return TRUE;
			}
		}
		else
		{
			fileWrite("Primary key value is zero in request approved flight history","SqlError","a+");
			return false;
		}
	}
}
?>
