<?php
/************************************************************************
* @Class Name	: TransactionMaster
* @Created on	: Sep 01, 2019 (Sunday) 19:39:53
* @Created By	: Dilli Raj P
* @Description	: Airlines Response Details Are Stored In This Table
*
**************************************************************************/
fileRequire("pdo/class.commonDB.php");
class transactionMaster extends commonDB
{
	//common variable
	var $_Oconnection;
	var $_IcountLoop;

	//input data
	var $_ItransactionId;
	var $_IairlinesRequestId;
	var $_IrequestMasterHistoryId;
	var $_IfareAdvised;
	var $_IchildFare;
	var $_IinfantFare;
	var $_IexchangeRate;
	var $_SfareNegotiable;
	var $_SautoApproval;
	var $_StransactionFee;
	var $_IfareValidity;
	var $_IfareValidityTypeId;
	var $_IfareExpiryType;
	var $_IpaymentValidity;
	var $_IpaymentValidityType;
	var $_IpaymentExpiryType;
	var $_IpassengerValidity;
	var $_IpassengerValidityType;
	var $_IpassengerExpiryType;
	var $_StransactionDate;
	var $_SfareExpiryDate;
	var $_SpaymentExpiryDate;
	var $_SpassengerExpiryDate;
	var $_IactiveStatus;
	var $_Iremarks;
	var $_IalternateFlightRemarks;
	var $_SresponseSource;
	var $_IcancelPolicyId;
	var $_ItimeLineId;
	var $_InegotiationPolicyId;
	var $_SsalesPromoStatus;
	var $_SpaymentInPercent;
	var $_StimelimitRemarks;

	//output data
	var $_AtransactionMaster;

	//contructor for the TransactionMaster class
	function __construct()
	{
		$this->_Oconnection;
		$this->_IcountLoop=0;
		$this->_ItransactionId = 0;
		$this->_IairlinesRequestId = 0;
		$this->_IrequestMasterHistoryId = 0;
		$this->_IfareAdvised = 0;
		$this->_IchildFare = 0;
		$this->_IinfantFare = 0;
		$this->_IexchangeRate = 0;
		$this->_SfareNegotiable = '';
		$this->_SautoApproval = '';
		$this->_StransactionFee = '';
		$this->_IfareValidity = 0;
		$this->_IfareValidityTypeId = 0;
		$this->_IfareExpiryType = 0;
		$this->_IpaymentValidity = 0;
		$this->_IpaymentValidityType = 0;
		$this->_IpaymentExpiryType = 0;
		$this->_IpassengerValidity = 0;
		$this->_IpassengerValidityType = 0;
		$this->_IpassengerExpiryType = 0;
		$this->_StransactionDate = '';
		$this->_SfareExpiryDate = '';
		$this->_SpaymentExpiryDate = '';
		$this->_SpassengerExpiryDate = '';
		$this->_IactiveStatus = 0;
		$this->_Iremarks = 0;
		$this->_IalternateFlightRemarks = 0;
		$this->_SresponseSource = '';
		$this->_IcancelPolicyId = 0;
		$this->_ItimeLineId = 0;
		$this->_InegotiationPolicyId = 0;
		$this->_SsalesPromoStatus = '';
		$this->_SpaymentInPercent = '';
		$this->_AtransactionMaster=array();
		$this->_AupdateCondition = array("transaction_id","airlines_request_id");
		$this->_SselectedField = '';
		$this->_INcondition = '';
		$this->_Dcondition = '';
		$this->_StimelimitRemarks = '';
	}
	
	//insert the details into TransactionMaster table
	function _insertTransactionMaster()
	{
		global $CFG;
		//Table Name 
		$_StableName = $CFG['db']['tbl']['transaction_master'];
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
			$this->_ItransactionId = $this->_Oconnection->lastInsertId();
			return TRUE;
		}
	}

	//get the details from TransactionMaster table
	function _selectTransactionMaster()
	{
		global $CFG;
		//Table Name 
		$_StableName = $CFG['db']['tbl']['transaction_master'];
		$_AtableField = array(
									"transaction_id",
									"airlines_request_id",
									"request_master_history_id",
									"fare_advised",
									"child_fare",
									"infant_fare",
									"exchange_rate",
									"fare_negotiable",
									"auto_approval",
									"transaction_fee",
									"fare_validity",
									"fare_validity_type_id",
									"fare_expiry_type",
									"payment_validity",
									"payment_validity_type",
									"payment_expiry_type",
									"passenger_validity",
									"passenger_validity_type",
									"passenger_expiry_type",
									"transaction_date",
									"fare_expiry_date",
									"payment_expiry_date",
									"passenger_expiry_date",
									"active_status",
									"remarks",
									"alternate_flight_remarks",
									"timelimit_remarks",
									"response_source",
									"cancel_policy_id",
									"time_line_id",
									"negotiation_policy_id",
									"sales_promo_status",
									"payment_in_percent"
										);

		if($this->_SselectedField != "")
		$_AtableField = explode(',',$this->_SselectedField);
		$_AconditionValue = array();
		$_AselectField = array_combine($_AtableField,$_AtableField);
		$_AconditionValue = $this->_prepareColumn($_StableName,$this,"SELECT");

		/* Setting custom conditons other than equal to*/
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_ItransactionId))
		{
			$_AconditionValue['transaction_id']=array();
			$_AconditionValue['transaction_id']['condition'] = $this->_INcondition;
			$_AconditionValue['transaction_id']['value'] = explode(',',$this->_ItransactionId);
		}
		if(in_array(strtoupper($this->_INcondition), array("IN","NOTIN")) && !empty($this->_IairlinesRequestId))
		{
			$_AconditionValue['airlines_request_id']=array();
			$_AconditionValue['airlines_request_id']['condition'] = $this->_INcondition;
			$_AconditionValue['airlines_request_id']['value'] = explode(',',$this->_IairlinesRequestId);
		}
		if(strtoupper(trim($this->_Dcondition)) == "BETWEEN" && !empty($this->_StransactionDate))
		{
			$_AconditionValue['transaction_date']=array();
			$_AconditionValue['transaction_date']['condition'] = 'between';
			$_AconditionValue['transaction_date']['value'] = explode('AND', $this->_StransactionDate);
		}
		$_AselectField = array_combine($_AtableField,$_AtableField);
		if(DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AselectField,'DB_AUTOQUERY_SELECT',$_AconditionValue)))
		{
			fileWrite($result,"SqlError","a+");
			fileWrite($this->_Oconnection->last_query,"SqlError","a+");
			return false;
		}
		$this->_IcountLoop=$result->numRows();
	
		if ($this->_IcountLoop > 0)
		{
			$this->_AtransactionMaster=$result->fetchAll(DB_FETCHMODE_ASSOC);
			$_AlastIndexed = array();
			$_AlastIndexed = end($this->_AtransactionMaster);
			$this->_ItransactionId = $_AlastIndexed['transaction_id'];
			$this->_IairlinesRequestId = $_AlastIndexed['airlines_request_id'];
			$this->_IrequestMasterHistoryId = $_AlastIndexed['request_master_history_id'];
			$this->_IfareAdvised = $_AlastIndexed['fare_advised'];
			$this->_IchildFare = $_AlastIndexed['child_fare'];
			$this->_IinfantFare = $_AlastIndexed['infant_fare'];
			$this->_IexchangeRate = $_AlastIndexed['exchange_rate'];
			$this->_SfareNegotiable = $_AlastIndexed['fare_negotiable'];
			$this->_SautoApproval = $_AlastIndexed['auto_approval'];
			$this->_StransactionFee = $_AlastIndexed['transaction_fee'];
			$this->_IfareValidity = $_AlastIndexed['fare_validity'];
			$this->_IfareValidityTypeId = $_AlastIndexed['fare_validity_type_id'];
			$this->_IfareExpiryType = $_AlastIndexed['fare_expiry_type'];
			$this->_IpaymentValidity = $_AlastIndexed['payment_validity'];
			$this->_IpaymentValidityType = $_AlastIndexed['payment_validity_type'];
			$this->_IpaymentExpiryType = $_AlastIndexed['payment_expiry_type'];
			$this->_IpassengerValidity = $_AlastIndexed['passenger_validity'];
			$this->_IpassengerValidityType = $_AlastIndexed['passenger_validity_type'];
			$this->_IpassengerExpiryType = $_AlastIndexed['passenger_expiry_type'];
			$this->_StransactionDate = $_AlastIndexed['transaction_date'];
			$this->_SfareExpiryDate = $_AlastIndexed['fare_expiry_date'];
			$this->_SpaymentExpiryDate = $_AlastIndexed['payment_expiry_date'];
			$this->_SpassengerExpiryDate = $_AlastIndexed['passenger_expiry_date'];
			$this->_IactiveStatus = $_AlastIndexed['active_status'];
			$this->_Iremarks = $_AlastIndexed['remarks'];
			$this->_IalternateFlightRemarks = $_AlastIndexed['alternate_flight_remarks'];
			$this->_StimelimitRemarks = $_AlastIndexed['timelimit_remarks'];
			$this->_SresponseSource = $_AlastIndexed['response_source'];
			$this->_IcancelPolicyId = $_AlastIndexed['cancel_policy_id'];
			$this->_ItimeLineId = $_AlastIndexed['time_line_id'];
			$this->_InegotiationPolicyId = $_AlastIndexed['negotiation_policy_id'];
			$this->_SsalesPromoStatus = $_AlastIndexed['sales_promo_status'];
			$this->_SpaymentInPercent = $_AlastIndexed['payment_in_percent'];
				
			return $this->_AtransactionMaster;
		}
	}

	//change the details in TransactionMaster table
	function _updateTransactionMaster()
	{
		global $CFG;

		//Table Name 
		$_StableName = $CFG['db']['tbl']['transaction_master'];
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
	
	//delete the details in TransactionMaster table
	function _deleteTransactionMaster()
	{
		global $CFG;
		$_AconditionValue = array();
		//Table Name 
		$_StableName = $CFG['db']['tbl']['transaction_master'];
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

	//Copy the fetched details into Transaction master table
	function _copyTransactionMaster()
	{
		global $CFG;
		
		if($this->_AtransactionMaster[0]['transaction_id']!=0)
		{

			//Table Name 
			$_StableName = $CFG['db']['tbl']['transaction_master'];
			//Insert values 
			$_AtableValue = array();
			$_AtableValue = $this->_AtransactionMaster;
			if ( DB::isError($result = $this->_Oconnection->autoExecute($_StableName,$_AtableValue,'DB_AUTOQUERY_INSERT')) )
			{
				fileWrite($result,"SqlError","a+");
				fileWrite($this->_Oconnection->last_query,"SqlError","a+");
				return FALSE;
			}
			else
			{
				$this->_ItransactionId = $this->_Oconnection->lastInsertId();
				return TRUE;
			}
		}
		else
		{
			fileWrite("Primary key value is zero in transaction master","SqlError","a+");
			return false;
		}
	}
	
}
?>
