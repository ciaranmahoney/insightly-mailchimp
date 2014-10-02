<?php //Start insightly data

require_once 'inc/config.inc.php'; //contains apikey
require_once 'inc/MCAPI.class.php';
require("inc/insightly.php");

$insightly = new Insightly($apikeyIN);

$opportunities = $insightly->getOpportunities(); // can limit # of records retrived with: array('top' => SOME_INTEGER)
$pipelineStages = $insightly->getPipelineStages();

$today = date("Y-m-d");

// Get basic opportunity data
foreach($opportunities as $opportunity){
	$oppId = $opportunity->OPPORTUNITY_ID;
	$oppName = $opportunity->OPPORTUNITY_NAME;
	$oppState = $opportunity->OPPORTUNITY_STATE;
	$oppUpdated = substr($opportunity->DATE_UPDATED_UTC, 0, -9);
	$oppLinks = $opportunity->LINKS;
	$oppCustomFields = $opportunity->CUSTOMFIELDS;

	//Loop through links to check if the linked contact is a student
	foreach($oppLinks as $oppLink){

		$oppLinkRole = strtoupper($oppLink->ROLE);
		//Only get data for Students that were updated today
		if($oppLinkRole == "STUDENT" && $oppUpdated == $today){ 
			$contactID = $oppLink->CONTACT_ID;

			//Getting Pipeline stage name
			$oppInfo = $insightly->getOpportunity($oppId);
			$oppPipelineStageID = $oppInfo->STAGE_ID;

			foreach($pipelineStages as $pipelineStage){
				$pipelineStageName = $pipelineStage->STAGE_NAME;
				$pipelineStageID = $pipelineStage->STAGE_ID;

				if($oppPipelineStageID == $pipelineStageID) {
					$oppPipelineStageName = $pipelineStageName;
				}
			}
			//Getting info for responsible user in CRM
			$oppResponsibleUserID = $opportunity->RESPONSIBLE_USER_ID;
			$oppResponsibleUserInfo = $insightly->getUser($oppResponsibleUserID);

			if(isset($oppResponsibleUserInfo)){
				$oppResponsibleUserFName = $oppResponsibleUserInfo->FIRST_NAME;
				$oppResponsibleUserLName = $oppResponsibleUserInfo->LAST_NAME;
				$oppResponsibleUserName = $oppResponsibleUserFName . " " . $oppResponsibleUserLName;
				$oppResponsibleUserEmail = $oppResponsibleUserInfo->EMAIL_ADDRESS;
			}

			//Get custom field data
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
					$oppBirthday = date("m/d", strtotime($oppCustomField->FIELD_VALUE));
				} 
			}

			// Get contact record info based on $contactID
			if(null !== $insightly->getContact($contactID)){
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
			}

			//If contact info is set, create batch array
			if(isset($contactEmail)){
				//I use this for checking the variables are correct by running insightly-mailchimp.php on localhost. Remove or comment out on production.
				// echo $oppId;
				// echo "<br/>" . $oppResponsibleUserName;
				// echo "<br/>" . $oppResponsibleUserEmail;
				// echo "<br/>" . $oppName;
				// echo "<br/>" . $oppId;
				// echo "<br/>" . $oppLinkRole;
				// echo "<br/>" . $oppBirthday;
				// echo "<br/>" . $oppState;
				// echo "<br/>" . $oppPipelineStageName;
				// echo "<br/>" . $oppUpdated;
				// echo "<br/>" . $contactID;
				// echo "<br/>" . $oppCountry;
				// echo "<br/>" . $contactFName;
				// echo "<br/>" . $contactLName;
				// echo "<br/>" . $contactEmail;
				// echo "<br/>" . $contactPhone; 
				// echo "<br/>" . $oppSource;
				// echo "<br/><br/>";

				// Creates batch[] array for Mailchimp import
				$batch[] = array('EMAIL'=>$contactEmail, 'FNAME'=>$contactFName, 'LNAME'=>$contactLName, 'MMERGE3'=>$oppCountry, 'MMERGE6'=>'International Student', 'MMERGE4'=>$contactPhone, 'CRMSTATE'=>$oppState, 'CRMOPPID'=>$oppId, 'MMERGE7'=>$oppSource, 'MMERGE8'=>$oppBirthday, 'CRMOPPOWNE'=>$oppResponsibleUserName, 'CRMOWNEMAI'=>$oppResponsibleUserEmail, 'CRMPIPELIN'=>$oppPipelineStageName); 		

			}
		}
	}
	
} // END INSIGHTLY DATA

//START MAILCHIMP
// Hacked together from code found here: http://apidocs.mailchimp.com/api/downloads/#php
if(isset($batch)){ // Send confirmation email when data transferred.
	$api = new MCAPI($apikeyMC);

	$optin = false; //no, don't send optin emails
	$up_exist = true; // yes, update currently subscribed users
	$replace_int = false; // no, add interest, don't replace

	$vals = $api->listBatchSubscribe($listId,$batch,$optin, $up_exist, $replace_int);

	if ($api->errorCode){
		
	    echo "Batch Subscribe failed!\n";
		echo "code:".$api->errorCode."\n";
		echo "msg :".$api->errorMessage."\n";
	} else {
		echo "added:   ".$vals['add_count']."\n";
		echo "updated: ".$vals['update_count']."\n";
		echo "errors:  ".$vals['error_count']."\n";
		
		foreach($vals['errors'] as $val){
			echo $val['email_address']. " failed\n";
			echo "code:".$val['code']."\n";
			echo "msg :".$val['message']."\n";
			$errorList = $val['email_address'] . "Error Code:".$val['code']."\n Error Msg :".$val['message']."\n";
		}

	//Send email with Mailchimp Errors to see what was imported and where errors occurred.
		$to = $completionToEmail; 
		$subject = "Insightly to Mailchimp Transfer Completed [" . $today . "]. Errors:" . $vals['error_count']."\n; Added: ".$vals['add_count'] ."\n; Updated: ". $vals['update_count'] ."\n";
		if(isset($errorList)) {
			$body = "**Some errors occurred during import**\n" . $errorList;
		} else {
			$body = "Import done with no errors. Woo hoo!";
		}

		//Set headers
		$headers = "From: " . $completionFromEmail . "\r\n";
		$headers .= "Reply-To: " . $completionReplyEmail . "\r\n"; 
		$headers .= "Cc: " . $completionCCEmail . "\r\n";

		//Send email
		mail($to, $subject, $body, $headers);

	}

} else { // Send confirmation if there no new records to transfer
	echo "No updated records to import";
	//Send email confirming completion, but with no new records.
	$to = $completionToEmail; 
	$subject = "Insightly to Mailchimp Transfer Completed [" . $today . "]. No updated records to import";
	$body = "Nothing new to update... Have a good day :)";

	// Set headers
	$headers = "From: " . $completionFromEmail . "\r\n";
	$headers .= "Reply-To: " . $completionReplyEmail . "\r\n"; 
	$headers .= "Cc: " . $completionCCEmail . "\r\n";

	//Send email
	mail($to, $subject, $body, $headers);
}
//END MAILCHIMP

?>
