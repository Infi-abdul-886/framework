<?php
 /*******************************************************************************
 * @class			viewHistoryProcess 
 * @author      	Gopinath.k
 * @created date	08-jul-2022
 * **************************************************************************** */
#Required the require files
fileRequire("classes/class.common.php");
fileRequire("dataModels/class.viewHistoryDataProcess.php");
//fileRequire("dataModels/class.noSqlHistoryDataProcess.php");

class viewHistoryProcess extends common
{
   var $_OviewHistoryProcess;
   var $_OnoSqlHistoryProcess;
   var $_IchildId;
   var $_SbeforePnr;
   var $_SnamePnr;
   var $_SticketPnr;

   function __construct()
    {
		$this->_Oconnection = '';
		$this->_noSqlConnection='';
		$this->_Osmarty='';
		$this->_OobjResponse='';
		$this->_IchildId=0;
		$this->_SbeforePnr='';
		$this->_SnamePnr='';
		$this->_SticketPnr='';
		$this->_DownSizeManualStatus='';
		$this->_GroupChangeRequestId='';
		$this->_IpaymentMasterId='';
		$this->_OviewHistoryProcess = new viewHistoryDataProcess();
		//$this->_OnoSqlHistoryProcess = new noSqlHistoryDataProcess();

		$this->_IinputData = array();
    }
    /**********************************************************
	* @author      	: Gopinath.k
	* @created date	: 2022-07-08
	* @Description	: To select request data from given action code.
	**********************************************************/
    function _fetchHistoryData($_SactionCode='',$_IrequestMasterId='')
	{
		global $CFG;
	    $_IairlinesRequestId=$this->_getAirlineRequestId($_IrequestMasterId);
		$_SjsonFilePath = 'xml/viewHistoryQuery.json';		
		$_AhistoryColumn = $this->_loadJsonFile($_SjsonFilePath);		
		$_AhistoryColumn = json_decode($_AhistoryColumn,1);
        $_AsendResponse=array();
		if (isset($_AhistoryColumn[$_SactionCode]) && !empty($_AhistoryColumn[$_SactionCode]))
            {
              $_AstatusValue=$_AhistoryColumn[$_SactionCode];
           	  foreach($_AstatusValue['database'] as $key=>$value)
		        {
					$_AtableData = $this->_selecthistoryDetails($key,$value,$_SactionCode,$_IrequestMasterId);
					if($key=='request_approved_flight_details' || $key=='request_approved_flight_history')
					{
					 foreach($_AtableData as $tableRow => $item) 
						 {
							 foreach($item as $tableColumn => $itemData)
							 {
								 if ($tableColumn == 'ancillary_fare' && empty(json_decode($itemData,1))) {
									unset($_AtableData[$tableRow][$tableColumn]);
								 }
							 }	
						 }
					}
					$_AsendResponse[$key]=$_AtableData;
		        }
            }
		if (!empty($_AsendResponse)) 
		{
			$_IchildId =$_AsendResponse['reference_request_master_id'];
			//$_JviewHistoryFormValues =json_encode(array(json_decode($_AsendResponse)));
			$_JviewHistoryFormValues=array();
			$_JviewHistoryFormValues = json_encode($_AsendResponse,1);
           // $_JviewHistoryFormValues = base64_encode(gzcompress($_JviewHistoryFormValues, 9));
			$_SactionedBy = $_AsendResponse['request_raised_by'];
			//$_IinsertfetchRequestData=$this->_insertHistoryDetails($_IrequestMasterId,$_IchildId,$_SactionedBy,$_SactionCode,$_JviewHistoryFormValues);
			$this->_OviewHistoryProcess->__construct();
			$this->_OviewHistoryProcess->_Oconnection = $this->_Oconnection;
			$this->_OviewHistoryProcess->_IrequestMasterId = $_IrequestMasterId;
			$this->_OviewHistoryProcess->_IchildId = $this->_IparentMasterId;
			$this->_OviewHistoryProcess->_SactionedName = $_SactionCode;
			$this->_OviewHistoryProcess->_SactionedDetails = $_AsendResponse;
			$this->_OviewHistoryProcess->_SactionedBy = $_SactionedBy;
			$this->_OviewHistoryProcess->_SactionedDate=$this->_getUTCDateValue();
			if($CFG["site"]["logDbType"] =="mySql")
			{
				$this->_OviewHistoryProcess->_insertViewHistoryDetails();
			}
             //nosql call 
			 if(isset($CFG["site"]["sendApiData"]) && $CFG["site"]["sendApiData"] =="Y")
			 {
				fileRequire("dataModels/class.noSqlHistoryDataProcess.php");
				fileRequire("classes/class.dataBase.php");
				$this->_OnoSqlHistoryProcess = new noSqlHistoryDataProcess();
	            $_OdataBase=new dataBase();
				// $_OdataBase->noSqldataBaseConnection();
				// $_OnoSqlconnection=$_OdataBase->_noSqlConnection; 
				// $this->_OnoSqlHistoryProcess->_noSqlConnection=$_OnoSqlconnection;
				// $this->_OviewHistoryProcess->_Oconnection = $_OnoSqlconnection;
				$this->_OnoSqlHistoryProcess->_Oconnection = $this->_Oconnection;
				$this->_OnoSqlHistoryProcess->_IrequestMasterId = $_IrequestMasterId;
				$this->_OnoSqlHistoryProcess->_IchildId = $this->_IchildId;
				$this->_OnoSqlHistoryProcess->_SparentMasterId=$this->_SparentMasterId;
				$this->_OnoSqlHistoryProcess->_IseriesRequestId=$this->_IseriesRequestId;
				$this->_OnoSqlHistoryProcess->_ItenderId=$this->_ItenderId;

				if($this->_IparentUpsizeId !='')
				{
					$this->_OnoSqlHistoryProcess->_IparentUpsizeId = $this->_IparentUpsizeId;
				}

				if($this->_IparentMasterId !='')
				{
					$this->_OnoSqlHistoryProcess->_IrequestMasterId = $_IrequestMasterId;
				}
				if($_SactionCode == 'AD')
				{
					$this->_OnoSqlHistoryProcess->_IrequestMasterId = $this->_IrequestMasterId;
				    $this->_OnoSqlHistoryProcess->_IchildId = $_IrequestMasterId;
				}
				$_AactionCodeArray=['PM','UP','RN','RS','DD'];
				if(($_SactionCode == 'PM' || $_SactionCode == 'UP' || $_SactionCode == 'RN'|| $_SactionCode == 'RS') && isset($this->_IrequestMasterId))
				{
					$this->_OnoSqlHistoryProcess->_IrequestMasterId = $this->_IrequestMasterId;
					$this->_OnoSqlHistoryProcess->_AresubmittedGroupId = $this->_AresubmittedGroupId;
				}
				if(in_array($_SactionCode,$_AactionCodeArray) && isset($this->_IrequestMasterId))
				{
					$this->_OnoSqlHistoryProcess->_IrequestMasterId = $this->_IrequestMasterId;
				}
				if($_SactionCode == 'AN')
				{
					$this->_OnoSqlHistoryProcess->_ApolicyMatrixDetails = $this->_ApolicyMatrixDetails;
				}
				if(isset($this->_IdivideGroupId))
				{
					$this->_OnoSqlHistoryProcess->_IdivideGroupId = $this->_IdivideGroupId;
				}
				//assign parent status in child id for partial modify to child
				if(isset($this->_SparentStatus) && !empty($this->_SparentStatus))
				{
					$this->_OnoSqlHistoryProcess->_SparentStatus = $this->_SparentStatus;
				}
				if(isset($this->_SchildRequestForModify) && !empty($this->_SchildRequestForModify))
				{
					$this->_OnoSqlHistoryProcess->_SchildRequestForModify = $this->_SchildRequestForModify;
				}
				if($this->_SquoteTypeParent != ''){
					$this->_OnoSqlHistoryProcess->_SquoteTypeParent=$this->_SquoteTypeParent;
				}
				if(isset($this->_SfirstScheduleChangeAfterPnr) && !empty($this->_SfirstScheduleChangeAfterPnr))
				{
					$this->_OnoSqlHistoryProcess->_SfirstScheduleChangeAfterPnr=$this->_SfirstScheduleChangeAfterPnr;
				}
				if(isset($this->_SseriesScheduleChanage) && !empty($this->_SseriesScheduleChanage) && isset($this->_IparentRequestMasterId) && !empty($this->_IparentRequestMasterId))
				{
					$this->_OnoSqlHistoryProcess->_SseriesScheduleChanage=$this->_SseriesScheduleChanage;
					$this->_OnoSqlHistoryProcess->_IrequestMasterId = $this->_IparentRequestMasterId;
				}
				if(isset($this->_SparentScheduleChangeStatus) && !empty($this->_SparentScheduleChangeStatus))
				{
					$this->_OnoSqlHistoryProcess->_SparentScheduleChangeStatus=$this->_SparentScheduleChangeStatus;
				}
				if (isset($this->_DownSizeManualStatus,$this->_GroupChangeRequestId) && $this->_DownSizeManualStatus == 'Y' && !empty($this->_GroupChangeRequestId))
				{
				$this->_OnoSqlHistoryProcess->_GroupChangeRequestId = $this->_GroupChangeRequestId;
				$this->_OnoSqlHistoryProcess->_DownSizeManualStatus = $this->_DownSizeManualStatus;
				}
				if($_SactionCode == 'OE')
				{
					$this->_OnoSqlHistoryProcess->_SfareExpiryEmailId = $CFG['approvePage']['autopilotEmailId'];
				}
				$this->_OnoSqlHistoryProcess->_SbeforePnr = $this->_SbeforePnr;
				$this->_OnoSqlHistoryProcess->_SactionedName = $_SactionCode;
				$this->_OnoSqlHistoryProcess->_SactionedDetails = $_AsendResponse;
				$this->_OnoSqlHistoryProcess->_SactionedBy = $_SactionedBy;
				$this->_OnoSqlHistoryProcess->_SactionedDate=$this->_getUTCDateValue();
				$this->_OnoSqlHistoryProcess->_SnamePnr=$this->_SnamePnr;
				$this->_OnoSqlHistoryProcess->_SticketPnr=$this->_SticketPnr;

				//$this->_OnoSqlHistoryProcess->_insertViewHistoryDetails();
				$this->_OnoSqlHistoryProcess->_sendViewHistoryDetails();
			 }

        }
	}
	/**********************************************************
	* @author      	: Gopinath.k
	* @created date	: 2022-07-08
	* @Description	: To select request data.
	**********************************************************/
	function _selecthistoryDetails($_StableName='',$_Scondition='',$_SactionCode='',$_IrequestMasterId='')
	{
		global $CFG;
		$_IairlinesRequestId=$this->_getAirlineRequestId($_IrequestMasterId);
		if($this->_IparentMasterId!='')
		{
			$_IrequestMasterId=$this->_IparentMasterId;
		}
		if($_StableName!='' && $_StableName != 'template')
		{
			if(strpos($_Scondition,'%requestMasterId') !== false)
			    $_Scondition = str_replace('%requestMasterId',$_IrequestMasterId,$_Scondition);
			if(strpos($_Scondition,'%parentUP') !== false)
			    $_Scondition = str_replace('%parentUP',$this->_IparentUpsizeId,$_Scondition);
			if(strpos($_Scondition,'%parent') !== false)
			    $_Scondition = str_replace('%parent',$this->_IinputData['parentRequestMasterId'],$_Scondition);
			if(strpos($_Scondition,'%tenderId') !== false)
			    $_Scondition = str_replace('%tenderId',$this->_ItenderId,$_Scondition);	
			if(strpos($_Scondition,'%childMasterId') !== false)
			    $_Scondition = str_replace('%childMasterId',$this->_SchildMasterId,$_Scondition);	
			if(strpos($_Scondition,'%airlineRequestId') !== false)
			    $_Scondition = str_replace('%airlineRequestId',$_IairlinesRequestId,$_Scondition);
			if(strpos($_Scondition,'%namePnr') !== false)
			    $_Scondition = str_replace('%namePnr',$this->_IinputData['namePnr'],$_Scondition);
			if(strpos($_Scondition,'%seriesGroupId') !== false)
			    $_Scondition = str_replace('%seriesGroupId',$this->_IseriesGroupId,$_Scondition);
			if(strpos($_Scondition,'%timeLimitPnr') !== false)
			    $_Scondition = str_replace('%timeLimitPnr',$this->_Spnr,$_Scondition);
			if(strpos($_Scondition,'%timelineType') !== false)
			    $_Scondition = str_replace('%timelineType',$this->_StimelineType,$_Scondition);
				
			if(strpos($_Scondition,'%tenderPnr') !== false)
			    $_Scondition = str_replace('%tenderPnr',$this->_Spnr,$_Scondition);			
			if(strpos($_Scondition,'%ticketPnr') !== false)
			    $_Scondition = str_replace('%ticketPnr',$this->_IinputData['pnr'],$_Scondition);
			if(strpos($_Scondition,'%ancillaryPnr') !== false)
				$_Scondition = str_replace('%ancillaryPnr',$this->_Spnr,$_Scondition);
			if(strpos($_Scondition,'%pnrBlockingId') !== false)
				$_Scondition = str_replace('%pnrBlockingId',$this->_SpnrBlockingId,$_Scondition);
			if(strpos($_Scondition,'%farePolicyId') !== false)
				$_Scondition = str_replace('%farePolicyId',$this->farePloicyIds,$_Scondition);
			if(strpos($_Scondition,'%ssrMasterId') !== false)
				$_Scondition = str_replace('%ssrMasterId',$this->_SssrMasterId,$_Scondition);	
			if(isset($this->_AinputData['modifiedHistoryId']) && strpos($_Scondition,'%modifiedHistoryId') !== false)
				$_Scondition = str_replace('%modifiedHistoryId',implode(',', $this->_AinputData['modifiedHistoryId']),$_Scondition);
			if(isset($this->_IinputData['modifiedHistoryId']) && strpos($_Scondition,'%modifiedHistoryId') !== false)
				$_Scondition = str_replace('%modifiedHistoryId',implode(',', $this->_IinputData['modifiedHistoryId']),$_Scondition);
			if(strpos($_Scondition,'%rejectGroupId') !== false)
				$_Scondition = str_replace('%rejectGroupId',implode(',', $this->rejectGroupId),$_Scondition);
				
			if(strpos($_Scondition,'%allowGroupId') !== false)
				$_Scondition = str_replace('%allowGroupId',implode(',', $this->_AallowGroupId),$_Scondition);	

			if(is_array($this->_IpaymentMasterId) && strpos($_Scondition,'%paymentMasterId') !== false)
				$_Scondition = str_replace('%paymentMasterId',implode(',', $this->_IpaymentMasterId),$_Scondition);
			if(strpos($_Scondition,'%ApaymentPnr') !== false)
				$_Scondition = str_replace('%ApaymentPnr',"'".implode("','", $this->_ApaymentPnr)."'",$_Scondition);
			if(strpos($_Scondition,'%penaltyAction') !== false)
				$_Scondition = str_replace('%penaltyAction',$this->_SpenaltyAction,$_Scondition);
	
			if(isset($this->_IinputData['requestHistoryId']) && strpos($_Scondition,'%requestHistoryId') !== false)
				$_Scondition = str_replace('%requestHistoryId',implode(',', $this->_IinputData['requestHistoryId']),$_Scondition);
			if(isset($this->_IinputData['flightStatus']) && strpos($_Scondition,'%flightStatus') !== false)
				$_Scondition = str_replace('%flightStatus',"'".implode("','", $this->_IinputData['flightStatus'])."'",$_Scondition);
			if($_StableName=='passenger_details')
			{
				        $_AtableField = 
						                   "passenger_id,
						                    pnr,
											foc_status,
											passenger_status,
											additional_details,
											aes_decrypt(first_name, HEX('infiniti'))AS first_name,
											aes_decrypt(last_name,HEX('infiniti'))last_name,
											aes_decrypt(pax_email_id,HEX('infiniti')) pax_email_id,
											aes_decrypt(pax_mobile_number,HEX('infiniti')) AS pax_mobile_number,
											aes_decrypt(pax_employee_code,HEX('infiniti')) AS pax_employee_code,
											aes_decrypt(pax_employee_code,HEX('infiniti')) AS pax_employee_code,
											aes_decrypt(pax_employee_id,HEX('infiniti')) AS pax_employee_id,
											aes_decrypt(id_proof,HEX('infiniti')) AS id_proof,
											aes_decrypt(id_proof_number,HEX('infiniti')) AS id_proof_number,
											aes_decrypt(sex,HEX('infiniti')) AS sex,
											aes_decrypt(dob,HEX('infiniti')) AS dob,
											aes_decrypt(citizenship,HEX('infiniti')) AS citizenship,
											aes_decrypt(passport_no,HEX('infiniti')) AS passport_no,
											aes_decrypt(date_of_issue,HEX('infiniti')) AS date_of_issue,
											aes_decrypt(date_of_expiry,HEX('infiniti')) AS date_of_expiry,
											aes_decrypt(traveller_number,HEX('infiniti')) AS traveller_number,
											aes_decrypt(frequent_flyer_number,HEX('infiniti')) AS frequent_flyer_number,
											aes_decrypt(passport_issued_place,HEX('infiniti')) AS passport_issued_place,
											aes_decrypt(place_of_birth,HEX('infiniti')) AS place_of_birth,
											aes_decrypt(address,HEX('infiniti')) AS address";
			}
			else
			{
				$_AtableField = '*';
			}		
			$requestValue = "SELECT ".$_AtableField." 
                    FROM
                        ".$_StableName."
                    WHERE
					   ".$_Scondition." ";
                    if(DB::isError($resultGetRequestId=$this->_Oconnection->query($requestValue)))
					{
					   fileWrite($sqlGetRequestId,"SqlError","a+");
					   return false;
					}
					if($resultGetRequestId->numRows() >0)
					{
					   while($rowGetRequestId=$resultGetRequestId->fetchRow(DB_FETCHMODE_ASSOC))
						{
						   $result[] = $rowGetRequestId;
						}
					}
			return $result;
		}
	}
	function _copyParentHistory($_IparentRequestMasterId,$_IchildRequestMasterId)
	{
		global $CFG;
		$_AparentHistoryDetails=array();
		$this->_OviewHistoryProcess->__construct();
		$this->_OviewHistoryProcess->_Oconnection = $this->_Oconnection;
		$this->_OviewHistoryProcess->_IrequestMasterId = $_IparentRequestMasterId;
		if($CFG["site"]["logDbType"] =="mySql")
	    {
		    $_AparentHistoryDetails=$this->_OviewHistoryProcess->_selectHistoryDetails();
		}

		if(isset($CFG["site"]["sendApiData"]) && $CFG["site"]["sendApiData"] =="Y")
		   {
			    fileRequire("dataModels/class.noSqlHistoryDataProcess.php");
				fileRequire("classes/class.dataBase.php");
				$this->_OnoSqlHistoryProcess = new noSqlHistoryDataProcess();
	            $_OdataBase=new dataBase();
				$_OdataBase->noSqldataBaseConnection();
				$_OnoSqlconnection=$_OdataBase->_noSqlConnection; 
				$this->_OnoSqlHistoryProcess->_noSqlConnection=$_OnoSqlconnection;
				$this->_OnoSqlHistoryProcess->_Oconnection = $_OnoSqlconnection;
				$this->_OnoSqlHistoryProcess->_IrequestMasterId = $_IparentRequestMasterId;
				$_AparentViewHistoryDetails=$this->_OnoSqlHistoryProcess->_selectViewHistoryDetails($_IparentRequestMasterId);
				foreach ($_AparentViewHistoryDetails as $parentVal)
				{
					$this->_OnoSqlHistoryProcess->_IrequestMasterId = $_IchildRequestMasterId;
				    $this->_OnoSqlHistoryProcess->_IchildId = $_IparentRequestMasterId;
					$this->_OnoSqlHistoryProcess->_SactionedName = $parentVal['action_name'];
					$this->_OnoSqlHistoryProcess->_SactionedDetails =$parentVal['action_details'];
					$this->_OnoSqlHistoryProcess->_SactionedBy = $_SactionedBy;
					$this->_OnoSqlHistoryProcess->_SactionedDate=$this->_getUTCDateValue();
					$this->_OnoSqlHistoryProcess->_SdisplayStatus='0';
					$this->_OnoSqlHistoryProcess->_insertCopyViewHistoryDetails($parentVal);
				}
				$this->_OnoSqlHistoryProcess->_AhistoryDetails = $_AparentViewHistoryDetails;
			}

		foreach ($_AparentHistoryDetails as $parentKey => $parentVal)
		{
			$_AparentHistoryDetails[$parentKey]['request_master_id']=$_IchildRequestMasterId;
			$_AparentHistoryDetails[$parentKey]['parent_id']=$_IparentRequestMasterId;
			$_AparentHistoryDetails[$parentKey]['display_status']=0;
		}
		$this->_OviewHistoryProcess->__construct();
		$this->_OviewHistoryProcess->_Oconnection = $this->_Oconnection;
		$this->_OviewHistoryProcess->_AhistoryDetails = $_AparentHistoryDetails;
		if($CFG["site"]["logDbType"] =="mySql")
	    {
		    $this->_OviewHistoryProcess->_copyViewHistoryDetails();
		}

		return true;
	}
	//allow group id for sc
	function _allowGroupsId($_IrequestMasterId)
	{
		global $CFG;
		$_AgroupStatusArray = array();
		fileRequire("dataModels/class.requestGroupDetails.php");
		$_ItransactionMasterId = $this->_getLastTransactionMasterId($_IrequestMasterId);
		$_OrequestGroupDetails=new requestGroupDetails();
		$_OrequestGroupDetails->_Oconnection=$this->_Oconnection;
		$_OrequestGroupDetails->_ItransactionMasterId=$_ItransactionMasterId;
		$_AgroupStatus=$_OrequestGroupDetails->_selectRequestGroupDetails();
		//$_AgroupStatus=array_column($_AgroupStatus,'group_status');
		foreach($_AgroupStatus as $key => $value)
		{
			$_AgroupStatusArray[$value['series_group_id']] = $value['group_status'];
		}
		$_ArestrictGroups = array(0=>'2',1=>'4',3=>'6',4=>'24',5=>'31');

		$_AgroupValue = array_diff($_AgroupStatusArray,$_ArestrictGroups);
		$_AgroupValue = array_keys($_AgroupValue);
		if(empty($_AgroupValue))
		{
			$_AgroupValue = array_keys($_AgroupStatusArray);
		}
		return $_AgroupValue;
	}

}
?>
