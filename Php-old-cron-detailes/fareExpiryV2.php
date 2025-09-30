<?php
/*******************************************************************************
 * @File name		fareExpiry
 * @Created by		kathirvelu B
 * @Modified by		kathirvelu B
 * @Modified Date	2022-SEP-05 04:30:19 PM IST
 * @File Descrition	Fare, Payment and Passenger Expiry Remainder alerts will be send
 * 					before x hours based on the configs
 ******************************************************************************/

#To prevent batch to run from browser / manually

/*if ((!empty($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] != 'POST')) && (php_sapi_name() != 'cli' || !empty($_SERVER['REMOTE_ADDR']))) {
exit("Please wait until batch run");
}*/

global $CFG;

ini_set("display_errors", 1);
ini_set("max_execution_time", 1200);

#Define an application base path
$CFG['path']['basePath'] = dirname(dirname(__FILE__)) . "/";
set_include_path(get_include_path() . PATH_SEPARATOR . $CFG['path']['basePath']);

#Include an application configuration file
require_once $CFG['path']['basePath'] . "cron/config.cron.php";

#Include the Common class file
@fileRequire("classes/class.common.php");
// fileRequire("classes/class.sendEmail.php");
// fileRequire("classes/class.encrypt.php");
// fileRequire("classes/class.getPNRDetails.php");
// fileRequire("dataModels/class.requestDetails.php");
// fileRequire("dataModels/class.cronEmailDetails.php");
// fileRequire("dataModels/class.requestGroupDetails.php");

class fareExpiryV2 extends common {

	/**
	 * @type Object
	 * @desc Database Connection
	 */
	public $_Oconnection;

	/**
	 * @type Object
	 * @desc Smarty templates
	 */
	public $_Osmarty;

	/**
	 * @type Array
	 * @desc 
	 */
	public $_Aprocess = array(
		'FARE' => '_sendFareExpiryAlert',
		'PAYMENT' => '_sendPaymentAlert',
		'PASSENGER' => '_sendNameListSubmissionAlert',
		'PENALTY' => '_sendPenaltyAlert',
		'PENALTYEXPIRY' => '_runChangePenaltyStatus',
		'OFFEREXPIRY' => '_runChangeOfferExpiryStatus',
	);

	/**
	 * @type Array
	 * @desc 
	 */
	public $_AemailName = array(
		'FARE' => 'Fare expiry alert',
		'PAYMENT' => 'Payment expiry alert',
		'PASSENGER' => 'Passenger expiry alert',
		'PENALTY' => 'Penalty expiry alert',
	);

	/**
	 * @type Array
	 * @desc 
	 */
	private $_DcurrentDateTime = '';

	function __construct() {
		$this->_DcurrentDateTime = $this->_getUTCDateValue();
	}
	/**
	 * @desc This process _processArgument can process the cron argument while rendering
	 * @param
	 * @return array
	 */
	public function _processArgument($_AcronType = array()) {
		global $CFG;

		// To run particular expiry alert, or when cronType is empty, will run all the alerts
		if (!empty($_AcronType)) {
			$CFG["site"]["expiryMailSending"] = $_AcronType;
		}
		// For create session for cron auto pilot user
		if (!$this->_createAutoPilotUserIdSession()) {
			return false;
		}

		$_Aresponse = array();

		fileWrite('fareexpiryupdatestart------------------' . date('Y-m-d H:i:s'), 'fareExpiry', 'a+');
		$this->_fareExpiredStatusUpdate();
		fileWrite('fareexpiryupdateends------------------' . date('Y-m-d H:i:s'), 'fareExpiry', 'a+');
	// Penalty start date status change
		if (isset($CFG['timeLineMatrix']['penaltyStartDate']['status']) && $CFG['timeLineMatrix']['penaltyStartDate']['status'] == 'Y') {
			$this->_penaltyDateStatusChange();
		}

		foreach ($CFG["site"]["expiryMailSending"] as $_Salerts) {

			fileWrite($_Salerts . ": process started", "fareExpiry", "a+");
			// Check whether function exist in $_Aprocess config array
			if (!isset($this->_Aprocess[$_Salerts])) {
				$_Aresponse[$_Salerts] = "Function not found";
				fileWrite($_Salerts . ": " . $_Aresponse[$_Salerts], "fareExpiry", "a+");
				continue;
			}

			// To check whether given alerts will send an email to users 
			// or when its update process (penalty, offer expired) will be skipped
			if (!in_array($_Salerts, array_keys($this->_AemailName))) {
				continue;
			}
			fileWrite($_Salerts.'start------------------' . date('Y-m-d H:i:s'), 'fareExpiry', 'a+');	

			if ($_Salerts=='PAYMENT') {
				//message service flow
				if (!empty($CFG["site"]["messageService"])) {
					fileRequire("classes/class.fareExpiry.php");
					$_Oexpiry = new fareExpiry($this->_Oconnection, $this->_Osmarty, "Y");
					$_Oexpiry->_paymentExpiry();
				}	
			}

			if ($_Salerts=='FARE') {
				//message service flow
				if (!empty($CFG["site"]["messageService"])) {
					fileRequire("classes/class.fareExpiry.php");
					$_Oexpiry = new fareExpiry($this->_Oconnection, $this->_Osmarty, "Y");
					$_Oexpiry->_sendfareExpiryAlert();
				}
			}
			if ($_Salerts=='PASSENGER') {
				//message service flow
				if (!empty($CFG["site"]["messageService"])) {
					fileRequire("classes/class.fareExpiry.php");
					$_Oexpiry = new fareExpiry($this->_Oconnection, $this->_Osmarty, "Y");
					$_Oexpiry->_passengerExpiry();
				}
			}

			if ($_Salerts=='PENALTY') {
				//message service flow
				if (!empty($CFG["site"]["messageService"])) {
				fileRequire("classes/class.fareExpiry.php");
				$_Oexpiry = new fareExpiry($this->_Oconnection, $this->_Osmarty, "Y");
				$_Oexpiry->_penaltyExpiryAlert();
				}
			}

			fileWrite($_Salerts.'ends------------------' . date('Y-m-d H:i:s'), 'fareExpiry', 'a+');	

		}
		
	}

}
$_COOKIE['moduleName'] = "fareExpiryV2";
fileWrite("Expiry alert cron job started", "fareExpiry", "w+");
#Start the expiry cron process
$_OfareExpiry = new fareExpiryV2();
$_OfareExpiry->_Oconnection = $_Oconnection;
$_OfareExpiry->_Osmarty = $smarty;

$_Aalerts = (!empty($argv[2])) ? $argv[2] : array();
fileWrite("Cron run for " . (empty($_Aalerts) ? 'All' : implode(',', $_Aalerts)), "fareExpiry", "a+");

$_Amsg = $_OfareExpiry->_processArgument($_Aalerts);
$_Smsg = implode(', ',array_values($_Amsg));
fileWrite("Message: " .$_Smsg, "fareExpiry", "a+");

#To disconnect the connection
$_Oconnection->disconnect();
unset($_OfareExpiry);

fileWrite("Expiry alert cron job completed", "fareExpiry", "a+");
#Display the message
exit($_Smsg);
?>