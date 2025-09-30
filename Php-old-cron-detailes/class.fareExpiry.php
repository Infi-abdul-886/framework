<?php
/* * *****************************************************************************
 * @class           :fareExpiry
 * @author          :A.kaviyarasan
 * @created date    :2020-02-25
 * @Description     :This class is used to maintain the same code for fare expiry and resend mail interface   
 * **************************************************************************** */

class fareExpiry
{
	 var $_OcommonObj;
	 var $_OobjPnr;
	 var $_OsendEmail;
	 var $_OrequestDetails;
	 var $_SstartDate;
	 var $_SendDate;

	 /**
	 * @type Array
	 * @desc 
	 */
	private $_AexpiryAlertTimings = array();
	 
	function __construct($_Oconnection,$_Osmarty,$_SinitializeResources="Y")
	{
		global $CFG;
		$this->_Oconnection=$_Oconnection;
		$this->_Osmarty=$_Osmarty;
		
		fileRequire("classes/class.common.php");
		$this->_OcommonObj=new common();
		$this->_OcommonObj->_Oconnection=$this->_Oconnection;
		$this->_OcommonObj->_Osmarty=$this->_Osmarty;
		
		fileRequire("classes/class.getPNRDetails.php");
		$this->_OobjPnr= new getPNRDetails();
		$this->_OobjPnr->_Oconnection=$this->_Oconnection;
		
		fileRequire("classes/class.sendEmail.php");
		$this->_OsendEmail =new sendEmail();
		$this->_OsendEmail->_Oconnection=$this->_Oconnection;
		$this->_OsendEmail->_Osmarty=$this->_Osmarty;
		
		fileRequire("dataModels/class.requestDetails.php");
		$this->_OrequestDetails=new requestDetails();
		$this->_OrequestDetails->_Oconnection=$this->_Oconnection;
		
		fileRequire("dataModels/class.cronEmailDetails.php");
		$this->_OcronEmailDetails = new cronEmailDetails;
		$this->_OcronEmailDetails->_Oconnection=$this->_Oconnection;
		//based on the flag initializing needed resources
		if($_SinitializeResources=="Y")
			$this->_initalizeCronDependentResources();
	}
		
	/*
	*@inputs         :no param
 	*@description    :This function will do the resource initialization for cron expiry process
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _initalizeCronDependentResources()
	{	
		
		//initializing the start date and end date
		$this->_initializeDates();
	}

		/**
	 * @desc Time frame calculation for calculating time for based on custom configuration
	 * @param
	 * @return
	 */
	 function _getTimeFrame($_AtimeLimts = array(), $_Stype = '') {
		if(!is_array($_AtimeLimts)) 
			$_AtimeLimts = array($_AtimeLimts);

		#Send the Fare expiry alert when the validity is 1 day, 3 days and 7 days before expiry
		$_ImaxTime = max($_AtimeLimts);
		sort($_AtimeLimts);

		$_DendTime = $this->_OcommonObj->addDateTime($this->_OcommonObj->_getUTCDateValue(), $_ImaxTime, "hour");
		$_Scondition = " (" . $_Stype . " BETWEEN '" . $this->_OcommonObj->_getUTCDateValue() . "' AND '" . $_DendTime . "')";

		$this->_AexpiryAlertTimings = array(
			'condition' => $_Scondition,
			'responseValidity' => $_AtimeLimts,
			'maxTime' => $_ImaxTime,
		);
		fileWrite("Time frame: " .$_Scondition, "fareExpiry", "a+");
		fileWrite(print_r($this->_AexpiryAlertTimings,1), "_AexpiryAlertTimings", "a+");
		return $this->_AexpiryAlertTimings;
	}
	
	/*
	*@inputs         :no param
 	*@description    :This function will initialize the start expiry date and end expiry date into the $this variable.
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _initializeDates()
	{	
		global $CFG;
		if($this->_SstartDate=="" && $this->_SendDate=="")
		{
			$this->_SstartDate=$this->_OcommonObj->_getUTCDateValue();
			$this->_SendDate=$this->_OcommonObj->addDateTime($this->_SstartDate,$CFG['cornEmail']['responseValidity'][0],"hour");
			//For tracking purpose
			filewrite('Time: '.$this->_SstartDate.' -- '.$this->_SendDate,'fareExpiry','a+');
		}
	}
	
	
	function _doPreProcess()
	{

		//update the expired requests status
		$this->_offerExpireUpdate();
			
	}
	//Changing the request status as fare expiry	
	 function _offerExpireUpdate()
	 {	
	 	$this->_OcommonObj->_fareExpiredStatusUpdate();
	 }
	 
	 
	/*
	*@inputs         :no param
 	*@description    :This function will send the expiry mails.
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _startSendingExpiryMails()
	{	
		global $CFG;
		foreach ($CFG["site"]["expiryMailSending"] as $_Ikey => $_Svalue) 
		{
			$_SfuncitonName="_".strtolower($_Svalue)."Expiry";
			$this->$_SfuncitonName();	
		}		
	}
	
   /*
	*@inputs         :null
 	*@description    :This function will send the payment expiry mail.
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _paymentExpiry()
 	{

 		//passing the request master id and getting the result object
 		$_OresultPayment=$this->_getPaymentQueryObject();
 		if(!empty($_OresultPayment)){
 			while($_Ainput=$_OresultPayment->fetchRow(DB_FETCHMODE_ASSOC))
	 		{
	 			
	 			if($this->_isValidityValidToSendEmail($_Ainput['validity']))
	 			{
					$_Ainput["emailName"]="Payment expiry alert";
					$_Ainput["cronCall"]="Y";
					$_Ainput["currentDate"]=$this->_SstartDate;
	 				$this->_OsendEmail->_setInput($_Ainput);
					$this->_OsendEmail->_sendMessage();
	 			}
	 		}
 		}
	}

		/*
	*@inputs         :null
 	*@description    :This function will send the fare expiry mail.
 	*@return         :null
	*@author         :A.kathirvelu
 	*@created date   :2022-10-03
	*/
	function _sendfareExpiryAlert()
 	{
 		//passing the request master id and getting the result object
 		$_Oresult=$this->_sendFareExpiryObject();
 		if($_Oresult!=''){
			while($_Ainput=$_Oresult->fetchRow(DB_FETCHMODE_ASSOC))
			{
			if($this->_isValidityValidToSendEmail($_Ainput['fare_validity']))
			{
			$_Ainput["emailName"]="Fare expiry alert";
			$_Ainput["cronCall"]="Y";
			$_Ainput["currentDate"]=$this->_SstartDate;
			$this->_OsendEmail->_setInput($_Ainput);
			$this->_OsendEmail->_sendMessage();
			}
			}	
 		}

	}


		/*
	*@inputs         :null
 	*@description    :This function will send the passenger expiry mail.
 	*@return         :null
	*@author         :A.kathirvelu
 	*@created date   :2022-10-03
	*/
	function _passengerExpiry()
 	{
 		//passing the request master id and getting the result object
 		$_OresultPassenger=$this->_sendNameListSubmissionObject();
 		if($_OresultPassenger!=''){
		while($_Ainput=$_OresultPassenger->fetchRow(DB_FETCHMODE_ASSOC))
 		{
 			if($this->_isValidityValidToSendEmail($_Ainput['passenger_validity']))
 			{
				$_Ainput["emailName"]="Passenger expiry alert";
				$_Ainput["cronCall"]="Y";
				$_Ainput["currentDate"]=$this->_SstartDate;
 				$this->_OsendEmail->_setInput($_Ainput);
				$this->_OsendEmail->_sendMessage();
 			}
 		}
 	}
}

		/**
	 * @desc
	 * @param
	 * @return
	 */
	public function _sendNameListSubmissionObject() {

		global $CFG;
		$_AtimeframeArray = $this->_getTimeFrame($CFG['cornEmail']['responseValidity'], 'pm.time_validity');

		if (!$_AtimeframeArray['condition']) {
			return "Set the Passenger expiry validity config";
		}
		$_Scondition = $_AtimeframeArray['condition'];

		$_AstatusDetails = $this->_OcommonObj->_getStatusDetails('PR');
		$_SremoveDummyRows = '';
		//For not considering dummy rows
		if (isset($CFG['nameUpdate']['insertDummyRowsForSeatSelection']) && $CFG['nameUpdate']['insertDummyRowsForSeatSelection'] == 'Y') {
			$_SremoveDummyRows = " AND additional_details NOT LIKE '%insertedDummyRow\":\"Y%'";
		}

		$sqlPax = "SELECT
						rm.user_id,
						rm.request_master_id,
						rm.request_group_name,
						rm.number_of_passenger,
						rm.number_of_adult,
						rm.number_of_child,
						rm.number_of_infant,
						rm.fare_acceptance_transaction_id,
						rm.user_currency,
						rm.request_group_name,
						pm.pnr,
						pm.time_validity as passenger_validity,
						paym.payment_master_id,
						paym.payment_percentage,
						paym.percentage_amount,
						paym.payment_status,
						paym.payment_validity_date,
						tm.transaction_id,
                        tm.cancel_policy_id
					FROM
						" . $CFG['db']['tbl']['airlines_request_mapping'] . " arm,
						" . $CFG['db']['tbl']['request_master'] . " rm,
						" . $CFG['db']['tbl']['passenger_master'] . " pm,
						" . $CFG['db']['tbl']['payment_master'] . " paym,
						" . $CFG['db']['tbl']['transaction_master'] . " tm
					WHERE
						arm.request_master_id=rm.request_master_id AND
						pm.airlines_request_id=arm.airlines_request_id AND
						tm.airlines_request_id=arm.airlines_request_id AND
						paym.airlines_request_id=arm.airlines_request_id AND
						paym.payment_status != " . $_AstatusDetails['status_id'] . " AND
						arm.current_status IN (5,9,10,12,13,15) AND
						pm.passenger_status =13 AND
						pm.pnr NOT LIKE 'GROUP%' AND
						rm.fare_acceptance_transaction_id <> 0 AND
						(SELECT IF(count(passenger_id) IS NULL,0,count(passenger_id)) as submitedPaxCount FROM passenger_details WHERE airlines_request_id=arm.airlines_request_id " . $_SremoveDummyRows . " ) < rm.number_of_passenger AND
						( " . $_Scondition . ") 

					ORDER BY
						pm.passenger_master_id DESC";						
		if (DB::isError($resultPax = $this->_Oconnection->query($sqlPax))) {
			fileWrite($sqlPax, "SqlError", "a+");
			return false;
		}
		if($resultPax->numRows()>0)
			return $resultPax;

	}

		/**
	 * @desc
	 * @param
	 * @return
	 */
	private function _sendFareExpiryObject() {

		global $CFG;
		$_AtimeFrame = $this->_getTimeFrame($CFG['cornEmail']['responseValidity'], 'tm.fare_expiry_date');
		if (!$_AtimeFrame['condition']) {
			fileWrite("Set the fare expiry validity config", "fareExpiry", "a+");
			return false;
		}

		$_Scondition = $_AtimeFrame['condition'];

		$sql = "SELECT
				rm.user_id,
				arm.request_master_id,
				rm.user_currency,
				rm.request_group_name,
				rm.number_of_passenger,
				rm.number_of_adult,
				rm.number_of_child,
				rm.number_of_infant,
				tm.transaction_id,
				tm.fare_expiry_date AS fare_validity,
				tm.fare_advised,
				tm.child_fare,
				tm.infant_fare,
				tm.fare_validity AS fareValidity,
				tm.cancel_policy_id,
				tm.fare_validity_type_id,
				(SELECT MAX(transaction_id)
				FROM " . $CFG['db']['tbl']['transaction_master'] . " tms
				WHERE arm.airlines_request_id=tms.airlines_request_id) AS last_transaction_id
			FROM
				" . $CFG['db']['tbl']['airlines_request_mapping'] . " arm INNER JOIN
				" . $CFG['db']['tbl']['request_master'] . " rm ON arm.request_master_id=rm.request_master_id INNER JOIN
				" . $CFG['db']['tbl']['transaction_master'] . " tm ON tm.airlines_request_id=arm.airlines_request_id
			WHERE
				arm.current_status = 3 AND
				" . $_Scondition . " AND
				rm.queue_no=0
			HAVING
				tm.transaction_id=last_transaction_id
			ORDER BY
				rm.request_master_id";
	
		if (DB::isError($_Oresult = $this->_Oconnection->query($sql))) {
			fileWrite($sql, "SqlError", "a+");
			return false;
		}

		//returning the result object
		if($_Oresult->numRows()>0)
			return $_Oresult;
	}
	
   /*
	*@inputs         :null
 	*@description    :This function will verify the validity to send email
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _isValidityValidToSendEmail($_SvalidityDate)
	{
		$_AdateDiff=$this->_OcommonObj->_dateDifference($this->_SstartDate,$_SvalidityDate);
 		$_AcurrentIntervalInfo=fareExpiry::_getFallingIntervalInfo($_AdateDiff['hours'],"Y");
		if($_AcurrentIntervalInfo["equalOrLessThanOne"]=="Y")
			return true;
		else
			return false;
	}
	
   /* 
	*@inputs         :$_IrequestMasterId(Integer)
 	*@description    :This function will execute the query and return the result object.
 	*@return         :$_OresultPayment(mysql query object)
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _getPaymentQueryObject()
	{
		global $CFG;
		$_AtimeFrame = $this->_getTimeFrame($CFG['cornEmail']['responseValidity'], 'ppd.pnr_payment_validity_date');
		if (!$_AtimeFrame['condition']) {
			fileWrite("Set the payment expiry validity config", "fareExpiry", "a+");
			return false;
		}
		//resendMail process
		if($this->_Ainput['request_master_id'])
			$_Scondition="rm.request_master_id=".$this->_Ainput['request_master_id']." AND ppd.pnr='".$this->_Ainput['pnr']."'";
		else
			$_Scondition=$_AtimeFrame['condition'];
		
		//Payment Expiry
		$sqlPayment = "SELECT 
						rm.user_id,
						arm.request_master_id,
						arm.airlines_request_id,
						rm.user_currency,
						rm.request_group_name,
						ppd.pnr_payment_validity_date as validity,
						ppd.pnr_payment_id,
						ppd.pnr,
						ppd.paid_amount,
						pm.payment_master_id,
						pm.payment_percentage,
						pm.percentage_amount,
						pm.payment_status,
						pm.payment_validity_date,
						rm.fare_acceptance_transaction_id
					FROM 
						".$CFG['db']['tbl']['airlines_request_mapping']." arm,
						".$CFG['db']['tbl']['request_master']." rm,
						".$CFG['db']['tbl']['payment_master']." pm,
						".$CFG['db']['tbl']['pnr_payment_details']." ppd
					WHERE
						arm.request_master_id=rm.request_master_id AND
						pm.airlines_request_id=arm.airlines_request_id AND
						pm.payment_master_id=ppd.payment_master_id AND
						ppd.payment_status<>'Cancel' AND
						arm.current_status =9 AND
						pm.payment_status =9 AND
						rm.fare_acceptance_transaction_id >0 AND
						ppd.payment_status='PENDING' AND
							".$_Scondition."  							
						ORDER BY arm.request_master_id,ppd.pnr";

		if(DB::isError($_OresultPayment= $this->_Oconnection->query($sqlPayment)))
		{
			fileWrite($sqlPayment,"SqlError","a+");
			return false;
		}

		//returning the result object
		if($_OresultPayment->numRows()>0)
			return $_OresultPayment;
	}
	/* 
	*@inputs         :$_OresultPayment(mysql object)
 	*@description    :This function will process the payment expiry result object and send the mail to appropriate users in PNR
 	                  level  (cron process)
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _processDataAndSendPaymentExpiryMail($_OresultPayment)
	{
		$restrictedPos=array("FRA","MUC","ZRH");
		while($rowPayment = $_OresultPayment->fetchRow(DB_FETCHMODE_ASSOC))
		{
			//getting the user informations
			$_AuserInformations=$this->_fetchUserInformationsForMail($rowPayment['user_id'],$rowPayment['request_master_id']);
			foreach ($_AuserInformations as $_Ikey => $_Auser)
			{
				if(in_array($_Auser['pos_code'],$restrictedPos))
					continue;
				//forming the inputs to check eligibility weather to send mail
				$_Ainput=array();
				$_Ainput["validity"]=$rowPayment['payment_validity'];
				$_Ainput["request_master_id"]=$rowPayment['request_master_id'];
				$_Ainput["subject"]="Payment Expiry";
				$_Ainput["email_id"]=$_Auser["email_id"];
				//checking the user info,validity info before sending the mail
				if($this->_isEmailIdAndValidityEligibleToSendMail($_Ainput))
				{
					$rowPayment["userName"]=$_Auser['title'].". ".$_Auser['first_name']." ".$_Auser['last_name'];
					$rowPayment["email_id"]=$_Auser['email_id'];
					$rowPayment["userTimeZoneInterval"] = $this->_OcommonObj->_getTimeZoneInterval($rowPayment['user_id']);
					$rowPayment["status"]="payment";
					$_ApaymentInfoWithValidity=$this->_getPaymentInfoWithValidity($rowPayment);
					$rowPayment=array_merge($rowPayment,$_Auser,$_ApaymentInfoWithValidity);
					//forming the specific arguments for payment expiry
					$rowPayment["alertType"]="paymentExpiryAlert";
					$rowPayment["headerTitleName"]=$rowPayment["subject"]='COMMON_EMAIL_SUBJECT_PAYMENT_EXPIRY_ALERT';
					$rowPayment['emailType']=$this->_OsendEmail->_getMailType("Payment expiry alert");
					$this->_assingDataAndSendMail($rowPayment);
			   }
		    }
	    }
	}
	
	/* 
	*@inputs         :$_OresultPayment(mysql object)
 	*@description    :This function will process the payment expiry result object and send the mail to appropriate users in  
 	                  request level(Resend mail process)  
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _processDataAndReSendPaymentExpiryMail($_OresultPayment,$_SuserType,$_Sprocess)
	{
		$restrictedPos=array("FRA","MUC","ZRH");
		$_AuserTypeArray=array("USER"=>array(11,12,13),"ANALYST"=>array(15));
		$_AuserBasedExpiryArray=array();
		while($rowPayment = $_OresultPayment->fetchRow(DB_FETCHMODE_ASSOC))
		{
			//getting the user informations
			$_AuserInformations=$this->_fetchUserInformationsForMail($rowPayment['user_id'],$rowPayment['request_master_id']);
			foreach ($_AuserInformations as $_Ikey => $_Auser)
			{
				if(!($_SuserType=="ALL" || in_array($_Auser["group_id"],$_AuserTypeArray[$_SuserType])))
					return false;
				
				if(in_array($_Auser['pos_code'],$restrictedPos))
					continue;
				
				$_AeachPnrValidity=array();
				$_AeachPnrValidity["pnr"]=$rowPayment["pnr"];
				$rowPayment["userTimeZoneInterval"] = $this->_OcommonObj->_getTimeZoneInterval($rowPayment['user_id']);
				$_ApaymentInfoWithValidity=$this->_getPaymentInfoWithValidity($rowPayment);	
				//based on the payment status storing the deposit and full payment amount and validity
				if($_ApaymentInfoWithValidity["statusDetails"]=="deposite")
				{
					$_AeachPnrValidity["depositePaymentDate"]=$_ApaymentInfoWithValidity["paymentExpiryDate"];
					$_AeachPnrValidity["depositePaymentAmount"]=$_ApaymentInfoWithValidity["paymentRequestDetails"][0]["payment_amount"];
					$_AeachPnrValidity["fullPaymentDate"]=$_ApaymentInfoWithValidity["paymentExpiryDateForFullPayment"];
				}
				else
				{
					$_AeachPnrValidity["depositePaymentDate"]=$_ApaymentInfoWithValidity["paymentExpiryDate"];
					$_AeachPnrValidity["depositePaymentAmount"]=$_ApaymentInfoWithValidity["totalAmount"];
				}

				$_AresultExpiryArray[$_Auser['user_id']]["validityDetails"][]=$_AeachPnrValidity;
								
				$_AresultExpiryArray[$_Auser['user_id']]["userName"]=$_Auser['title'].". ".$_Auser['first_name']." ".$_Auser['last_name'];
				$_AresultExpiryArray[$_Auser['user_id']]["email_id"]=$_Auser['email_id'];
				$_AresultExpiryArray[$_Auser['user_id']]["pnrs"][]=$rowPayment["pnr"];
				$_AresultExpiryArray[$_Auser['user_id']]["user_currency"]=$rowPayment["user_currency"];
				$_AresultExpiryArray[$_Auser['user_id']]["request_master_id"]=$rowPayment["request_master_id"];
				$_AresultExpiryArray[$_Auser['user_id']]["user_id"]=$rowPayment["user_id"];
				$_AresultExpiryArray[$_Auser['user_id']]["emailType"]=$this->_OsendEmail->_getMailType("Payment expiry alert");
				$_AresultExpiryArray[$_Auser['user_id']]["status"]="allPnrPaymentExpiry";
				$_AresultExpiryArray[$_Auser['user_id']]["alertType"]="paymentExpiryAlert";
				//forming the specific arguments for payment expiry
				$_AresultExpiryArray[$_Auser['user_id']]["headerTitleName"]=$_AresultExpiryArray[$_Auser['user_id']]["subject"]='COMMON_EMAIL_SUBJECT_PAYMENT_EXPIRY_ALERT';
				$_AresultExpiryArray[$_Auser['user_id']]["passengerExpiryDate"]=$_ApaymentInfoWithValidity["passengerExpiryDate"];	
				$_AresultExpiryArray[$_Auser['user_id']]["pnrDetails"][]=$_ApaymentInfoWithValidity["pnrDetails"][0];
				$_AresultExpiryArray[$_Auser['user_id']]["cancelPolicyName"]=$_ApaymentInfoWithValidity["cancelPolicyName"];
				$_AresultExpiryArray[$_Auser['user_id']]["termsAndConditions"]=$_ApaymentInfoWithValidity["termsAndConditions"];
		    }
	    }
	    $this->_sendMailOnRequestLevel($_AresultExpiryArray,$_Sprocess);
	}


	/* 
	*@inputs         :$_AexpiryArray(Array),$_Sprocess(String)
 	*@description    :This function will assign the resources and send the mail based on request level instead of PNR level   
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _sendMailOnRequestLevel($_AexpiryArray,$_Sprocess)
	{
		//looping the expiry array to assign data and send mail
		foreach ($_AexpiryArray as  $_Avalue) 
		{
			$this->_assingDataAndSendMail($_Avalue,$_Sprocess);
		}
	}
	
	/* 
	*@inputs         :$_SalertType(String)
 	*@description    :This function will give keys that will get assign into smarty with appropriate values    
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _getSmartyAssignIndex($_SalertType="")
	{
		
		$_AassignData=array();
		$_ApaymentIndex=array(
								"requestGroupName"=>"request_group_name",
								"status"=>"status",
								"paymentDetails"=>"payment_details",
								"pnrDetails"=>"pnrDetails",
								"passengerExpiryDate"=>"passengerExpiryDate",
								"paymentExpiryDate"=>"paymentExpiryDate",
								"paymentExpiryDateForFullPayment"=>"paymentExpiryDateForFullPayment",
								"statusDetails"=>"statusDetails",
								"ApaymentRequestDetails"=>"paymentRequestDetails",
								"ItotalAmount"=>"totalAmount",
								"AuserName"=>"userName",
								"groupRequestId"=>"groupRequestId",
							 );
		if($_SalertType=="paymentExpiryAlert")
			$_AassignData=$_ApaymentIndex;
		if($_SalertType=="passengerExpiryAlert")
			$_AassignData=$_ApassengerIndex;
		if($_SalertType=="fareExpiryAlert")
			$_AassignData=$_AfareIndex;

		return $_AassignData;
	}
	
	/* 
	*@inputs         :$_Adata,$_Sprocess
 	*@description    :This function will assign the data into smarty and send the mail 
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _assingDataAndSendMail($_Adata,$_Sprocess="CRON")
	{
		global $CFG;
		//checking the user email mapping and changing the language
		if($this->_OcommonObj->_getUserEmailSetting($_Adata['user_id'],$_Adata['emailType'],$_Adata["alertType"]))
		{
			//getting the index to assign data into smarty
			$_AassignData=$this->_getSmartyAssignIndex($_Adata["alertType"]);
			foreach ($_AassignData as $_Skey => $_Svalue) 
				$this->_OcommonObj->objLangSmarty->assign($_Skey,$_Adata[$_Svalue]);
			
			$displayHeaderDetails['headerTitle']=$this->_OcommonObj->objLangSmarty->getConfigVars($_Adata['headerTitleName']);
			$this->_OcommonObj->objLangSmarty->assign("CFG",$CFG);
			$this->_OcommonObj->objLangSmarty->assign("objCron",$_Adata);
			$this->_OsendEmail->toUserId=$_Adata['user_id'];
			$this->_OsendEmail->emailType=$_Adata['emailType'];
			$this->_OsendEmail->_IrequestMasterId=$_Adata['request_master_id'];
			$this->_OsendEmail->_AGRDetails=$this->_getDataForFlightDetailsTable($_Adata);
			$this->_OsendEmail->subject=$_Adata["subject"];
			$this->_OcommonObj->objLangSmarty->assign("objGroupRequest",$this->_OsendEmail);
			
			if(strpos($this->_OsendEmail->subject,'%S') !== false)
				$this->_OsendEmail->subject=str_replace('%S',$_Adata['groupRequestId'],$this->_OsendEmail->subject);
			
			$this->_OsendEmail->to = $_Adata['email_id'];
			$this->_OsendEmail->message.=$this->_OcommonObj->objLangSmarty->fetch("cronEmail.tpl");
			
			if(in_array($_Sprocess, array("CRON","SENDMAIL")))
			{
				$this->_OsendEmail->setHeader();
				$this->_OsendEmail->setEmail();
			}
			elseif($_Sprocess == 'VIEW')
				return $this->_OsendEmail->message;
							
			if($_Sprocess=="CRON")
			{
				$this->_OcronEmailDetails->_SsentDate = $this->_SstartDate;
				#Adding the payment expiry comments in PNR starts
				if($CFG['site']['navitaireBasedAirline']=='Y' && $CFG['payment']['expiryMailSentDate']=='Y')																		
				{
					$this->_OobjPnr->__construct();
					$this->_OobjPnr->_Oconnection = $this->_Oconnection;
					$this->_OobjPnr->_IrequestMasterId = $_Adata['request_master_id'];
					$this->_OobjPnr->_SPNR = $_Adata['pnr'];
					$this->_OobjPnr->_addPNRPaymentExpiryComments($_Adata['email_id'],$this->_SstartDate,$this->_OcronEmailDetails->_AcronEmailDetails);
				}
				#Adding the payment expiry comments in PNR ends
				$this->_OcronEmailDetails->_insertCronEmailDetails();
			}				
		}
	}
	
	
	/* 
	*@inputs         :$_Adata
 	*@description    :This function will give the flight details to show flight details table in template
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	
	function _getDataForFlightDetailsTable($_Adata)
	{
		
		$_AflightInfo = $this->_getFlightInformation($_Adata);
		$_AflightInfo['mailType']=$_Adata["alertType"];
		return $_AflightInfo;
	}
	
	/* 
	*@inputs         :$_Adata
 	*@description    :This function will give the request details based on the request id
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	
	function _getRequestDetails($_IrequestId)
	{		
		if(empty($this->_ArequestDetails[$_IrequestId]))
		{
			$this->_OrequestDetails->__construct();
			$this->_OrequestDetails->_IrequestMasterId=$_IrequestId;
			$_ArequestDetails=$this->_OrequestDetails->_selectRequestDetails();	
			$this->_ArequestDetails[$_IrequestId]=$_ArequestDetails;
		}
		return $this->_ArequestDetails[$_IrequestId];
	}
	
	/* 
	*@inputs         :$_IuserId,$_IrequestId
 	*@description    :This function will give the user informations based on the user id and request id
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	
	function _fetchUserInformationsForMail($_IuserId,$_IrequestId)
	{
		global $CFG;
		$_AsalesPersonDetails=array(); 	    
		//Forming array for doing existing mailing process
		$_AuserDetails = $this->_OcommonObj->_getUserDetails($_IuserId);
		$_AsalesPersonDetails=array($_AuserDetails);
		
		$_AuserIdDetails['group_id'] =$_AuserDetails[0]['group_id'];
		$_AuserIdDetails['user_id'] =$_AuserDetails[0]['user_id'];
		//Checking config variable
		if($CFG['cornEmail']['salesPersonFareExpiryMail'] == 'Y')
			//Getting sales person details using corporate_id
			$_AsalesPersonDetails = $this->_OcommonObj->_getSalesPersonDetails($_AuserDetails['corporate_id'],$_AuserIdDetails);
		
		//Send fare expiry mail to group desk based on the below configuration
		if($CFG['cronEmail']['sendExpiryMailTo']['sectorAnalystExpiryMail']=='Y')
		{
			$_AsectorAnalystDetails=array();
			$_AsectorAnalystPosBasedDetails=array();
			$_ArequestDetails=$this->_getRequestDetails($_IrequestId);
			//Get sector and pos based group desk for send fare expiry mail
			$_AsectorAnalystDetails= $this->_OcommonObj->_getSectorAnalystDetails('',$_AuserIdDetails,$_ArequestDetails[0]['origin_airport_code'],$_ArequestDetails[0]['dest_airport_code']);
			$_AsectorAnalystPosBasedDetails= $this->_OcommonObj->_getSectorAnalystDetails($_AuserDetails['corporate_id'],$_AuserIdDetails);
			$_AsalesPersonDetails=array_merge($_AsalesPersonDetails,$_AsectorAnalystDetails,$_AsectorAnalystPosBasedDetails);
		}
		
		return  $_AsalesPersonDetails;
	}
	
   /* 
	*@inputs         :$_SstartDate(String-current date),$_SvalidityDate(String-fare validity date),$_ScheckHours(String-flag)
 	*@description    :This function will get the falling interval to send a mail
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	public static function _getFallingIntervalInfo($_IdiffHour,$_ScheckHours="N")
	{
		global $CFG;
		$validityInterval=-1;
		//checking the diff hour with max cron expiry hour 
		if($CFG['cornEmail']['responseValidity'][0] >= $_IdiffHour)
		{
			$mailSendingCount=count($CFG['cornEmail']['responseValidity']);
			for($i=0;$i<=$mailSendingCount-1;$i++)
			{	
				if($CFG['cornEmail']['responseValidity'][$i] >= $_IdiffHour)
				{
					//if next interval is there means check weather the current difference is falls in between that
					if(!empty($CFG['cornEmail']['responseValidity'][$i+1]))
					{
						if($_IdiffHour>$CFG['cornEmail']['responseValidity'][$i+1])
						{
							$validityInterval=$i;	
							break;
						}
					} 
					else
					{
						//considering this is a last interval since there is no next interval
						$validityInterval=$i;
						break;
					}							
				}						
			}
		}
		$_AvalidityInfo['fallIndex']=$validityInterval;
		//based on the flag checking the hour difference
		if($_ScheckHours=="Y")
		{
			//diff hour must be equal or less than 1 to interval hour
			$_AvalidityInfo["equalOrLessThanOne"]="N";
			if($CFG['cornEmail']['responseValidity'][$validityInterval]>=$_IdiffHour && $_IdiffHour>=($CFG['cornEmail']['responseValidity'][$validityInterval]-1))
			{
				$_AvalidityInfo["equalOrLessThanOne"]="Y";
			}
		}

	 return  $_AvalidityInfo;	
	}
	
   /****
    *Author     :Kaviyarasan A
    *Created on :03-06-2021
    *Param      :$_Ainputs(data from _getPaymentQueryObject function)
    *Description:This function will return the payment expiry details for mail
    *return     :Array
    *****/
	function _getPaymentExpiryInfo($_Ainputs)
	{
		global $CFG;
		
		# payment validity expiry hour validityHours
		# Validity => date in pnr payment details
		$_AcommonInfo['payment_validity_hours'] = round((strtotime($_Ainputs['validity'])-strtotime(date('Y-m-d H:i:s')))/(60*60));
		
		# total pnr paid amount
		$_IpaidAmount=$this->_OcommonObj->_getPnrPaidAmount($_Ainputs['request_master_id'],$_Ainputs['pnr']);
		$_ApnrDetails=$this->_OcommonObj->_getPnrWiseDetails($_Ainputs['pnr'],$_Ainputs['request_master_id']);
		
		foreach($_ApnrDetails as &$value)
		{
			$value['pnr_amount']=$this->_OcommonObj->_getRoundOffFare($value['pnr_amount'],"",$_Ainputs['user_currency']);
			
			# total pnr amount
			$_AcommonInfo['total_pnr_amount']=$value['pnr_amount'];
			
			# pnr due amount
			$_AcommonInfo['pnr_due_amount']=$value['pnr_amount']-$_IpaidAmount;
			
			# pnr number of guest
			$_AcommonInfo['no_of_guest']=$value['no_of_adult']+$value['no_of_child']+$value['no_of_infant'];
			
			# pnr net rate
			$_AcommonInfo['net_rate']=$value['pnr_amount']/$value['no_of_adult'];
			
			
		}
		
		//getting the pnr payment details
		$this->_OobjPnr->__construct();
		$this->_OobjPnr->_Oconnection = $this->_Oconnection;
		$this->_OobjPnr->_Osmarty=$this->_Osmarty;
		$this->_OobjPnr->_IrequestMasterId=$_Ainputs['request_master_id'];
		$this->_OobjPnr->_SPNR=$_Ainputs['pnr'];
		$_ApnrPaymentDetails=$this->_OobjPnr->_getPNRPaymentDetails();
		
		if(count($_ApnrPaymentDetails['pnrDetails']['pnrPaymentDetails'])>0)
		{

			//checking multiple validity exists
			if($_ApnrPaymentDetails['pnrDetails']['pnrPaymentDetails'][0]['nextPaymentValidity']!='' && strtotime(date('d-M-Y H:i',strtotime($_ApnrPaymentDetails['pnrDetails']['pnrPaymentDetails'][0]['nextPaymentValidity'])))!=strtotime($_ApnrPaymentDetails['pnrDetails']['pnrPaymentDetails'][0]['paymentValidity']))
			{
				# deposit amount for PNR
				$_AcommonInfo['pnr_deposit_amount']=$this->_OcommonObj->_getRoundOffFare($_ApnrPaymentDetails['pnrDetails']['pnrPaymentDetails'][0]['pnrPayableAmount'],"","displayFare");
				
				# full payment data of the PNR
				$_AcommonInfo['pnr_full_payment_date']=$_ApnrPaymentDetails['pnrDetails']['pnrPaymentDetails'][0]['nextPaymentValidity'];
				
			}
		}
		
		//getting the expiry details
		$_AvalidityArray = $this->_OcommonObj->_getPassengerExpiryDetails($_Ainputs['request_master_id'],$_Ainputs['pnr']);
		
		# passenger expiry date passengerExpiryDate
		$_AcommonInfo['pax_expiry_date']=$this->_OcommonObj->_getTimeZoneDateFormatValue($_AvalidityArray[0]['time_validity'],$_Ainputs["userTimeZoneInterval"]);
		
		# getting the terms and condition
		$_AtermsCondtions = $this->_getCancelPolicyDetails($_Ainputs['fare_acceptance_transaction_id']);
		
		#  terms_and_condition
		$_AcommonInfo['tc_name'] = $_AtermsCondtions['name'];
		$_AcommonInfo['tc_description'] = $_AtermsCondtions['description'];
		return $_AcommonInfo;		
	}	
	
	/* 
	*@inputs         :$_AcheckInfo(Array)
 	*@description    :This function will verify the validity informations to send a mail
 	*@return         :Boolean
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _isEmailIdAndValidityEligibleToSendMail($_AcheckInfo)
	{
		global $CFG;
		//getting the expiry interval info
		$_AcurrentIntervalInfo=$this->_getFallingIntervalInfo($this->_SstartDate,$_AcheckInfo["validity"],"Y");
		if($_AcurrentIntervalInfo["equalOrLessThanOne"]=="Y")
		{
			//checking is there any mail already sent  
			$_AsentMails=$this->_verifySentMails($_AcheckInfo);
			if(count($_AsentMails)>0)
			{
				//fetching the last sent mail 
				$_AsentMail=end($_AsentMails);
				//getting the sent mail's expiry interval info
				$_AsentIntervalInfo=$this->_getFallingIntervalInfo($_AsentMail['sent_date'],$_AcheckInfo["validity"]);
			}
			else
			{
				//no mails sent means allow to send a mail
				$_AsentIntervalInfo=array();
				$_AsentIntervalInfo["fallIndex"]=$_AcurrentIntervalInfo["fallIndex"]-1;
			}
			//sent interval should be lesser than the current interval 
			if($_AsentIntervalInfo["fallIndex"]<$_AcurrentIntervalInfo["fallIndex"])
				return true;
		}
		return false;
	}
	
	/* 
	*@inputs         :$_AcheckInfo(Array-inputs for function)
 	*@description    :This function will verify the sent mails,weather the mail has already sent or not
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _verifySentMails($_AcheckInfo)
	{
		//getting the sent mails from cron email details
		$this->_OcronEmailDetails->__construct();
		$this->_OcronEmailDetails->_IrequestMasterId = $_AcheckInfo['request_master_id'];
		$this->_OcronEmailDetails->_SemailSubject = $_AcheckInfo["subject"];
		$this->_OcronEmailDetails->_SsentTo =$_AcheckInfo['email_id'];
		$this->_OcronEmailDetails->_SexpiryDate = $_AcheckInfo['validity'];
		return $this->_OcronEmailDetails->_selectCronEmailDetails();
	}
	
	/* 
	*@inputs         :$_Adata(Array)
 	*@description    :This function will fetch the flight informations based on the transaction and modify status
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	
	function _getFlightInformation($_Adata)
	{
		
		
		if(!isset($_Adata['transaction_id']))
			$_Adata['transaction_id'] = $this->_OcommonObj->_getLastTransactionMasterId($_Adata['request_master_id']);
		//getting the modify status by passing transaction id
		$checkstatus=$this->_OcommonObj->_checkRequestModifyStatus($_Adata['transaction_id']);
		//getting the flight informations
		$flightInfo=$this->_OcommonObj->_getFligthInformationEmail($_Adata['request_master_id'],$_Adata['transaction_id'],$checkstatus,$_Adata['pnr']);
		return $flightInfo;
	}
	
	/* 
	*@inputs         :$_ArowInputs(Array)
 	*@description    :This function will fetch the payment informations with validty
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	
	function _getPaymentInfoWithValidity($_ArowInputs)
	{
		global $CFG;
		//fetching the payment common info
		$_AcommonPaymentInfo=$this->_fetchPaymentRelatedCommonInfo($_ArowInputs);
		//fetching the payment validity info
		$_ApaymentValidityInfo=$this->_getPaymentValidityInfo($_ArowInputs);
		$_ApaymentInfoWithValidity=array_merge($_AcommonPaymentInfo,$_ApaymentValidityInfo);
		return $_ApaymentInfoWithValidity;	
	}
	
	/* 
	*@inputs         :$_ArowInputs(Array)
 	*@description    :This function will fetch the payment validity info
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _getPaymentValidityInfo($_ArowInputs)
	{
		//Finding out whether this request is having single payment or multiple payment
		//By default considering status as full payment
		$_ApaymentValidityInfo["statusDetails"]="fullPayment";
		//getting the pnr payment details
		$this->_OobjPnr->__construct();
		$this->_OobjPnr->_Oconnection = $this->_Oconnection;
		$this->_OobjPnr->_Osmarty=$this->_Osmarty;
		$this->_OobjPnr->_IrequestMasterId=$_ArowInputs['request_master_id'];
		$this->_OobjPnr->_SPNR=$_ArowInputs['pnr'];
		$pnrPaymentDetailsArr=$this->_OobjPnr->_getPNRPaymentDetails();
		if(count($pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'])>0)
		{
			if($pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'][0]['nextPaymentValidity']!='' && strtotime(date('d-M-Y H:i',strtotime($pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'][0]['nextPaymentValidity'])))!=strtotime($pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'][0]['paymentValidity']))
			{
				//since more rows present in pnr payment details considering as deposite payment 
				$_ApaymentValidityInfo["statusDetails"]="deposite";
				$_ApaymentValidityInfo["paymentRequestDetails"]=array();
				$_ApaymentValidityInfo["paymentRequestDetails"][0]['payment_expiry_date']=$this->_OcommonObj->_getTimeZoneDateFormatValue($_ArowInputs['payment_validity'],$_ArowInputs["userTimeZoneInterval"]);
				
				$_ApaymentValidityInfo["paymentRequestDetails"][0]['payment_amount']=$this->_OcommonObj->_getRoundOffFare($pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'][0]['pnrPayableAmount'],"","displayFare");
				
				$paymentExpiryDateForFullPayment=$pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'][0]['nextPaymentValidity'];
				$_ApaymentValidityInfo["paymentExpiryDateForFullPayment"]= $this->_OcommonObj->_getTimeZoneDateFormatValue($paymentExpiryDateForFullPayment,$_ArowInputs["userTimeZoneInterval"]);
			}
		}
		
		if($_ApaymentValidityInfo["statusDetails"]=="fullPayment")
		{
			$_ItotalAmount=$pnrPaymentDetailsArr['pnrDetails']['pnrPaymentDetails'][0]['pnrPayableAmount'];
			$_ApaymentValidityInfo["totalAmount"]=$this->_OcommonObj->_getRoundOffFare($_ItotalAmount,"","displayFare");
		}
		else
		{
			//Getting total amount for the request
			$_ApaymentValidityInfo["totalAmount"]=$this->_OcommonObj->_getPnrAmountValue($_ArowInputs['pnr'],$_ArowInputs['request_master_id']);
		}
		return $_ApaymentValidityInfo;
	}
	
	/* 
	*@inputs         :$_ArowInputs(Array)
 	*@description    :This function will fetch the common payment informations
 	*@return         :Array
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _fetchPaymentRelatedCommonInfo($_ArowInputs)
	{
		global $CFG;
		
		$_SpaymentAmount=$_ArowInputs['payment_percentage'].'~'.$this->_OcommonObj->_getRoundOffFare($_ArowInputs['percentage_amount'],"",$_ArowInputs['user_currency']);
		$_ApaymentRelatedCommonInfo['payment_details']=explode("~",$_SpaymentAmount);
		
		//works only for payment expiry alert 
		if(isset($_ArowInputs['payment_validity']))
		{
			$_ApaymentRelatedCommonInfo['payment_validity_user_dateFormat']=$_ApaymentRelatedCommonInfo['paymentExpiryDate'] = $this->_OcommonObj->_getTimeZoneDateFormatValue($_ArowInputs['payment_validity'],$_ArowInputs["userTimeZoneInterval"]);
			$_ApaymentRelatedCommonInfo['payment_validity_hours'] = round((strtotime($_ArowInputs['payment_validity'])-strtotime(date('Y-m-d H:i:s')))/(60*60));
			
		}		
		//works only for passenger expiry alert 
		if(isset($_ArowInputs['passenger_validity']))
		{
			$_ApaymentRelatedCommonInfo["passenger_validity_user_dateFormat"] =$_ApaymentRelatedCommonInfo['passengerExpiryDate']=$this->_OcommonObj->_getTimeZoneDateFormatValue($_ArowInputs['passenger_validity'],$_ArowInputs["userTimeZoneInterval"]);
			
			$_ApaymentRelatedCommonInfo['passenger_validity_hours'] = round((strtotime($_ArowInputs['passenger_validity'])-strtotime(date('Y-m-d H:i:s')))/(60*60));	
		}
		else
		{
			/*Pnr Passenger Validity Date  for  payment expiry alert */
			$_AvalidityArray = $this->_OcommonObj->_getPassengerExpiryDetails($_ArowInputs['request_master_id'],$_ArowInputs['pnr']);
			$_ApaymentRelatedCommonInfo['passengerExpiryDate']=$this->_OcommonObj->_getTimeZoneDateFormatValue($_AvalidityArray[0]['time_validity'],$_ArowInputs["userTimeZoneInterval"]);
			
		}
		//getting the cancel policy info
		$_AtermsCondtions = $this->_getCancelPolicyDetails($_ArowInputs['fare_acceptance_transaction_id']);
		$_ApaymentRelatedCommonInfo['cancelPolicyName'] = $_AtermsCondtions['name'];
		$_ApaymentRelatedCommonInfo['termsAndConditions'] = $_AtermsCondtions['description'];

		$_ApaymentRelatedCommonInfo["pnrDetails"]=$this->_OcommonObj->_getPnrWiseDetails($_ArowInputs['pnr'],$_ArowInputs['request_master_id']);

		foreach($_ApaymentRelatedCommonInfo["pnrDetails"] as &$value)
		{
			$value['pnr_amount']=$this->_OcommonObj->_getRoundOffFare($value['pnr_amount'],"",$_ArowInputs['user_currency']);
		}
		#Old un used codes are commented
		/*$pnrWiseDepartureDate=$this->_OcommonObj->_getPnrDetails($_ArowInputs['request_master_id'],$_ArowInputs['pnr']);				
		$_SdepartureDate=$pnrWiseDepartureDate[0]['departureDate'].' '.$pnrWiseDepartureDate[0]['departureTime'];				
		$_SdepartAirportCode = $this->_OcommonObj->_getFirstOrigin($_ArowInputs['request_master_id']);
		$_SairportCurrentDateTimeInterval = $this->_OcommonObj->_getAirportCodeCurrentTime($_SdepartAirportCode,true);
		$_SdepartureDate = $this->_OcommonObj->_getConvertToUTCDateValue($_SdepartureDate,$_SairportCurrentDateTimeInterval);

		$lastTransactionMasterId=$_ArowInputs['fare_acceptance_transaction_id'];				
		$paymentRequestIdCount=$this->_OcommonObj->_getPaymentRequestIdCount($lastTransactionMasterId);*/					

		/*This functionality shows wrong passenger expiry date in ticketing expiry mail		
		$valitityDate=$this->_OcommonObj->_getPnrNameValidity($_ArowInputs['request_master_id'],array($_ArowInputs['pnr']));
		foreach($valitityDate as $key=>$val)
		{
			$passengerExpiryDate=$valitityDate[$key];
		}
		$passengerExpiryDate=$this->_OcommonObj->_getTimeZoneDateFormatValue($passengerExpiryDate,$userTimeZoneInterval);*/
		return $_ApaymentRelatedCommonInfo;
	}
		
		/*
	 * To get the Terms and conditions details for the given Terms and conditons ID
	 * Input : Terms and condition matrix ID (Integer)
	 * Output: Return the T&C details (String)
	 */
	function _getCancelPolicyDetails($_IfareAcceptenceTransactionId)
	{
		global $CFG;
		global $_Oconnection;
		$sqlCancelPolicy = "SELECT
								cancel_policy_id
							FROM
								".$CFG['db']['tbl']['transaction_master']."
							WHERE
								transaction_id = ".$_IfareAcceptenceTransactionId;
								
		if(DB::isError($resultCancelPolicy = $this->_Oconnection->query($sqlCancelPolicy)))
		{
			fileWrite($sqlCancelPolicy,"SqlError","a+");
			return false;
		}
		$_IcancelPolicyId = $resultCancelPolicy->fetchRow(DB_FETCHMODE_ASSOC)["cancel_policy_id"];
		
		$_AresultCancelPolicyDetails = array();
		#Maintain the T&C contents for return when it already exists
		static $_AcancelPolicyDetails = array();
		if(isset($_AcancelPolicyDetails[$_IcancelPolicyId]) && isset($_AcancelPolicyDetails[$_IcancelPolicyId]['name']) && !empty($_AcancelPolicyDetails[$_IcancelPolicyId]['name']))
		{
			$_AresultCancelPolicyDetails = $_AcancelPolicyDetails[$_IcancelPolicyId];
		}
		else
		{
			$sqlTermCondition = "SELECT
									cancel_policy_name,
									cancel_policy_description
								FROM
									".$CFG['db']['tbl']['cancel_policy_details']."
								WHERE
									cancel_policy_id = ".$_IcancelPolicyId."
								LIMIT 1";
			if(DB::isError($resultTermCondition = $_Oconnection->query($sqlTermCondition)))
			{
				fileWrite($sqlTermCondition,"SqlError","a+");
				return false;
			}
			if($resultTermCondition->numRows()>0)
			{
				$rowTermCondition = $resultTermCondition->fetchRow(DB_FETCHMODE_ASSOC);
				#Replace the special characters and backslaches
				$search = array("\\\\","\\0","\\n","\\r","\'",'\"',"\\Z");
				$replace = array("\\","\x00","\n","\r","'",'"',"\x1a");
				$rowTermCondition['cancel_policy_description']=str_replace($search,$replace,$rowTermCondition['cancel_policy_description']);
				
				$_AcancelPolicyDetails[$_IcancelPolicyId]['name'] = $rowTermCondition['cancel_policy_name'];
				$_AcancelPolicyDetails[$_IcancelPolicyId]['description'] = $rowTermCondition['cancel_policy_description'];
				
				$_AresultCancelPolicyDetails = $_AcancelPolicyDetails[$_IcancelPolicyId];
			}
		}

		return $_AresultCancelPolicyDetails;
	}
	
   /*
	*@inputs         :null
 	*@description    :This function will send the penalty expiry mail.
 	*@return         :null
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _penaltyExpiryAlert()
 	{
 		//passing the request master id and getting the result object
 		$_OresultPenalty=$this->_getPenaltyQueryObject();
		if($_OresultPenalty)
		{
			while($_Ainput=$_OresultPenalty->fetchRow(DB_FETCHMODE_ASSOC))
			{
				if($this->_isValidityValidToSendEmail($_Ainput['validity']))
				{
					$_Ainput["emailName"]="Penalty expiry alert";
					$_Ainput["cronCall"]="Y";
					$_Ainput["currentDate"]=$this->_SstartDate;
					$this->_OsendEmail->_setInput($_Ainput);
					$this->_OsendEmail->_sendMessage();
				}
			}
		}
	}
	
	
   /* 
	*@inputs         :$_IrequestMasterId(Integer)
 	*@description    :This function will execute the query and return the result object.
 	*@return         :$_OresultPayment(mysql query object)
	*@author         :A.kaviyarasan
 	*@created date   :2020-02-10
	*/
	function _getPenaltyQueryObject()
	{
		global $CFG;
		// Get AR(payment pending) status code
		$_AstatusCode = array('AR');
		$_AstatusDetails = $this->_OcommonObj->_getStatusDetails('',$_AstatusCode);
		$_Astatus =implode(",", $_AstatusDetails);						
		
		//resendMail process
		if($this->_Ainput['request_master_id'])
			$_Scondition="rm.request_master_id=".$this->_Ainput['request_master_id']." AND ppd.pnr='".$this->_Ainput['pnr']."'";
		else
			$_Scondition="((rtm.expiry_date BETWEEN '".$this->_SstartDate."' AND '".$this->_SendDate."'))";
		// Get parent requests details with penalty expiry date
		$sqlPenalty ="SELECT 
					DISTINCT pbd.pnr,
					rm.user_id,
					arm.airlines_request_id,
					arm.request_master_id,
					arm.current_status,
					tm.transaction_id,
					rtm.expiry_date AS validity
				FROM
					airlines_request_mapping arm,
					request_master rm,
					transaction_master tm,
					request_timeline_details rtm,
					pnr_blocking_details pbd,
					request_group_details rgd 
				WHERE
					tm.airlines_request_id = arm.airlines_request_id AND
					rm.fare_acceptance_transaction_id = tm.transaction_id AND
					rm.request_master_id = arm.request_master_id AND 
					pbd.request_master_id = rm.request_master_id AND 
					tm.transaction_id = rtm.transaction_id AND 
					rgd.transaction_master_id = tm.transaction_id AND 
					rgd.series_group_id = rtm.series_group_id AND  
					rtm.timeline_type  = 'PENALTY' AND
					rtm.status != 'TIMELINEEXTEND' AND
					arm.current_status IN (".$_Astatus.") AND
					rgd.group_status IN (".$_Astatus.") AND
					".$_Scondition."  				
				ORDER BY
					arm.request_master_id,
					tm.transaction_id DESC,rtm.expiry_date ASC";
		if(DB::isError($_OresultPayment= $this->_Oconnection->query($sqlPenalty)))
		{
			fileWrite($sqlPenalty,"SqlError","a+");
			return false;
		}
		//returning the result object
		if($_OresultPayment->numRows()>0)
		{
			return $_OresultPayment;
		}else
		{
			return false;
		}
	}

}

?>

