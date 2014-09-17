<?php //Start insightly data

require_once 'inc/config.inc.php'; //contains apikey
require_once 'inc/MCAPI.class.php';
require("inc/insightly.php");

$insightly = new Insightly($apikeyIN);

$opportunities = $insightly->getOpportunities(array('top'=> 20)); // can limit # of records retrived with: array('top' => SOME_INTEGER)

$today = date("Y-m-d");

// Get basic opportunity data
foreach($opportunities as $opportunity){
	$oppId = $opportunity->OPPORTUNITY_ID;
	$oppName = $opportunity->OPPORTUNITY_NAME;
	$oppState = $opportunity->OPPORTUNITY_STATE;
	$oppUpdated = substr($opportunity->DATE_UPDATED_UTC, 0, -9);
	$oppLinks = $opportunity->LINKS;
	$oppCustomFields = $opportunity->CUSTOMFIELDS;

	//Getting info for responsible user in CRM
	$oppResponsibleUserID = $opportunity->RESPONSIBLE_USER_ID;
	$oppResponsibleUserInfo = $insightly->getUser($oppResponsibleUserID);
	$oppResponsibleUserFName = $oppResponsibleUserInfo->FIRST_NAME;
	$oppResponsibleUserLName = $oppResponsibleUserInfo->LAST_NAME;
	$oppResponsibleUserName = $oppResponsibleUserFName . " " . $oppResponsibleUserLName;
	$oppResponsibleUserEmail = $oppResponsibleUserInfo->EMAIL_ADDRESS;

	//Loop through links
	foreach($oppLinks as $oppLink){

		$oppLinkRole = strtoupper($oppLink->ROLE);
		//Only get data for Students updated today
		if($oppLinkRole == "STUDENT" /**&& $oppUpdated == $today**/){ 
			$contactID = $oppLink->CONTACT_ID;

			//Loop through opportunity custom fields
			foreach($oppCustomFields as $oppCustomField) {

				//Get Country of Citizenship custom field
				if(isset($oppCustomField->CUSTOM_FIELD_ID) && $oppCustomField->CUSTOM_FIELD_ID == "OPPORTUNITY_FIELD_5"){
					$oppCountry = $oppCustomField->FIELD_VALUE;
				} 
				//Get Source custom field
				if(isset($oppCustomField->CUSTOM_FIELD_ID) && $oppCustomField->CUSTOM_FIELD_ID == "OPPORTUNITY_FIELD_9"){
					$oppSource = $oppCustomField->FIELD_VALUE;
				} 

				//Get Birthday custom field
				if(isset($oppCustomField->CUSTOM_FIELD_ID) && $oppCustomField->CUSTOM_FIELD_ID == "OPPORTUNITY_FIELD_12"){
					$oppBirthday = $oppCustomField->FIELD_VALUE;
				} 
			}

			// Get contact record info based on $contactID
			$contact = $insightly->getContact($contactID);

			//Use default value of "student" if names not set
			if(isset($contact->FIRST_NAME)){
				$contactFName = $contact->FIRST_NAME;
			} else {
				$contactFName = "Student";
			}
			if(isset($contact->LAST_NAME)){
				$contactLName = $contact->LAST_NAME;
			} else {
				$contactLName = "Student";
			}

			$contactInfos = $contact->CONTACTINFOS;

			//Loop through contact details to get email and phone
			foreach($contactInfos as $contactInfo){
				if(isset($contactInfo->TYPE) && $contactInfo->TYPE == "EMAIL"){
					$contactEmail = $contactInfo->DETAIL;
				} 
				if(isset($contactInfo->TYPE) && $contactInfo->TYPE == "PHONE"){
					$contactPhone = $contactInfo->DETAIL;
				} 
			}

			//If contact email is set, create batch array
			if(isset($contactEmail)){
				//Checking the variables are correct. Remove or comment out on production.
				echo $oppId;
				echo "<br/>" . $oppResponsibleUserName;
				echo "<br/>" . $oppResponsibleUserEmail;
				echo "<br/>" . $oppName;
				echo "<br/>" . $oppId;
				echo "<br/>" . $oppLinkRole;
				echo "<br/>" . $oppBirthday;
				echo "<br/>" . $oppState;
				echo "<br/>" . $oppUpdated;
				echo "<br/>" . $contactID;
				echo "<br/>" . $oppCountry;
				echo "<br/>" . $contactFName;
				echo "<br/>" . $contactLName;
				echo "<br/>" . $contactEmail;
				echo "<br/>" . $contactPhone;
				echo "<br/>" . $oppSource;
				echo "<br/><br/>";

				// Creates batch[] array for Mailchimp import
				$batch[] = array('EMAIL'=>$contactEmail, 'FNAME'=>$contactFName, 'LNAME'=>$contactLName, 'MMERGE3'=>$oppCountry, 'MMERGE6'=>'International Student', 'MMERGE4'=>$contactPhone, 'CRMSTATE'=>$oppState, 'CRMOPPID'=>$oppId, 'MMERGE7'=>$oppSource, 'MMERGE8'=>$oppBirthday, 'CRMOPPOWNE'=>$oppResponsibleUserName, 'CRMOWNEMAI'=>$oppResponsibleUserEmail); 		

			}
		}
	}
	
} // END INSIGHTLY DATA

// //START MAILCHIMP
// if(isset($batch)){
// 	$api = new MCAPI($apikeyMC);

// 	$optin = false; //no, don't send optin emails
// 	$up_exist = true; // yes, update currently subscribed users
// 	$replace_int = false; // no, add interest, don't replace

// 	$vals = $api->listBatchSubscribe($listId,$batch,$optin, $up_exist, $replace_int);

// 	if ($api->errorCode){
		
// 	    echo "Batch Subscribe failed!\n";
// 		echo "code:".$api->errorCode."\n";
// 		echo "msg :".$api->errorMessage."\n";
// 		} else {
// 		echo "added:   ".$vals['add_count']."\n";
// 		echo "updated: ".$vals['update_count']."\n";
// 		echo "errors:  ".$vals['error_count']."\n";
		
// 		foreach($vals['errors'] as $val){
// 			echo $val['email_address']. " failed\n";
// 			echo "code:".$val['code']."\n";
// 			echo "msg :".$val['message']."\n";
// 		}

// 	//Send email with Mailchimp Errors to see what was imported and where errors occurred.
// 		$to = "ciaran@zhoom.com.au"; 
// 		$subject = "Insightly to Mailchimp Transfer Completed [" . $today . "]. Errors:" . $vals['error_count']."\n; Added: ".$vals['add_count'] ."\n; Updated: ". $vals['update_count'] ."\n";
// 		$body = $vals['errors'];
// 		mail($to, $subject, $body);
// 	}
// } else {
// 	echo "No updated records to import";
// 	//Send email with Mailchimp Errors to see what was imported and where errors occurred.
// 	$to = "ciaran@zhoom.com.au"; 
// 	$subject = "Insightly to Mailchimp Transfer Completed [" . $today . "]. No updated records to import";
// 	$body = "Nothing here :) ";
// 	mail($to, $subject, $body);
// }
// //END MAILCHIMP

?>
