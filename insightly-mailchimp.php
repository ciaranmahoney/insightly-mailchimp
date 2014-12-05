<?php //Start insightly data

require_once 'inc/config.inc.php'; //contains api keys and email addresses for administrator notifications.
require_once 'inc/MCAPI.class.php'; // MailChimp wrapper
require_once 'inc/insightly.php'; // Insightly wrapper

$insightly = new Insightly($apikeyIN); // Connects to Insightly's API 

$opportunities = $insightly->getOpportunities(); // This gets all opportunities from Insightly as JSON. You can limit the # of records retrived using parameter: array('top' => SOME_INTEGER)

$pipelineStages = $insightly->getPipelineStages(); // This gets all the pipeline stages from Insightly as JSON.

$today = date("Y-m-d"); // Sets today's date so we can filter by date.

// Get basic opportunity data by looping through the opportunities JSON and extract the data we want. 
foreach($opportunities as $opportunity) {
	$oppId = $opportunity->OPPORTUNITY_ID;
	$oppName = $opportunity->OPPORTUNITY_NAME;
	$oppState = $opportunity->OPPORTUNITY_STATE; // OPEN, CLOSED, ABANDONED, etc
	$oppUpdated = substr($opportunity->DATE_UPDATED_UTC, 0, -9); // Date opportunity was last updated in Y-m-d format. 
	$oppLinks = $opportunity->LINKS; // An array of all the linked items, eg, Contacts, Accounts, etc
	$oppCustomFields = $opportunity->CUSTOMFIELDS; // An array of all custom fields

	// Loop through the opportunities links to check the linked item's role.
	// We need to do this to ensure we are getting the appripriate contact person for the opportunity. 
	// In this case, we check to see if the linked item is a Student, but you could check for any type of linked role here. 
	// This relies on good naming conventions for linked entities.
	foreach($oppLinks as $oppLink){

		//Here we use a conditional to only select data for links where role is STUDENT & opportunity was updated today
		$oppLinkRole = strtoupper($oppLink->ROLE);
		
		if($oppLinkRole == "STUDENT" && $oppUpdated == $today) { 
			$contactID = $oppLink->CONTACT_ID;

			// Insightly requires another API call to get more detailed infomation for the opportunity. 
			// Here we use the opportunity ID ($oppId) and the getOpportunity method to query Insightly. 
			// This returns detailed opportunity information in JSON format. 
			$oppInfo = $insightly->getOpportunity($oppId);

			// Now we extract the pipeline stage ID and loop through the pipeline stages JSON data to match the stage ID with stage name.
			$oppPipelineStageID = $oppInfo->STAGE_ID;
			 
			foreach($pipelineStages as $pipelineStage) {
				$pipelineStageName = $pipelineStage->STAGE_NAME;
				$pipelineStageID = $pipelineStage->STAGE_ID;

				if($oppPipelineStageID == $pipelineStageID) {
					$oppPipelineStageName = $pipelineStageName;
				}
			} 

			// We use the getUser(#ID) method to get complete responsible user information in JSON format.
			// Then we can extract the user's info such as name and email.
			$oppResponsibleUserID = $opportunity->RESPONSIBLE_USER_ID;
			$oppResponsibleUserInfo = $insightly->getUser($oppResponsibleUserID);

			if(isset($oppResponsibleUserInfo)){
				$oppResponsibleUserFName = $oppResponsibleUserInfo->FIRST_NAME;
				$oppResponsibleUserLName = $oppResponsibleUserInfo->LAST_NAME;
				$oppResponsibleUserName = $oppResponsibleUserFName . " " . $oppResponsibleUserLName;
				$oppResponsibleUserEmail = $oppResponsibleUserInfo->EMAIL_ADDRESS;
			}

			// In this case, there are a few custom fields we need to extract. 
			// We loop through the custom fields array and extract the data we require, using Insightly's numbered OPPORTUNITY_FIELD tags. 
			// To find these tags, edit an opportunity in Insightly, inspect element and look for input value="OPPORTUNITY_FIELD_##"
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

			// Use getContact(#ContactID) method to get contact record JSON.
			if(null !== $insightly->getContact($contactID)){
				$contact = $insightly->getContact($contactID);

				//Use default value of "student" if names not set. MailChimp may not accept the data without both names.
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

				//Loop through contact details to get email and phone.
				foreach($contactInfos as $contactInfo){
					if(isset($contactInfo->TYPE) && $contactInfo->TYPE == "EMAIL"){
						$contactEmail = $contactInfo->DETAIL;
					} 
					if(isset($contactInfo->TYPE) && $contactInfo->TYPE == "PHONE"){
						$contactPhone = $contactInfo->DETAIL;
					} 
				}
			}

			//If contact info is set, create batch array in the format required by MailChimp
			if(isset($contactEmail)){
				// Creates batch[] array for Mailchimp import
				$batch[] = array('EMAIL'=>$contactEmail, 'FNAME'=>$contactFName, 'LNAME'=>$contactLName, 'MMERGE3'=>$oppCountry, 'MMERGE6'=>'International Student', 'MMERGE4'=>$contactPhone, 'CRMSTATE'=>$oppState, 'CRMOPPID'=>$oppId, 'MMERGE7'=>$oppSource, 'MMERGE8'=>$oppBirthday, 'CRMOPPOWNE'=>$oppResponsibleUserName, 'CRMOWNEMAI'=>$oppResponsibleUserEmail, 'CRMPIPELIN'=>$oppPipelineStageName); 	

				// Used for checking the variables are correct by running insightly-mailchimp.php locally. Remove or comment out on production.
				// echo $oppId;
				// echo "<br/>Responsible User Name: " . $oppResponsibleUserName;
				// echo "<br/>Responsible User Email: " . $oppResponsibleUserEmail;
				// echo "<br/>Opportunity Name: " . $oppName;
				// echo "<br/>Opportunity ID: " . $oppId;
				// echo "<br/>Opportunity Souce: " . $oppSource;
				// echo "<br/>Opportunity Country" . $oppCountry;
				// echo "<br/>Opportunity State: " . $oppState;
				// echo "<br/>Pipeline Stage Name: " . $oppPipelineStageName;
				// echo "<br/>Linked Item Role: " . $oppLinkRole;
				// echo "<br/>Opportunity Updated Date: " . $oppUpdated;
				// echo "<br/>Contact ID: " . $contactID;
				// echo "<br/>Birthday: " . $oppBirthday;
				// echo "<br/>Contact First Name: " . $contactFName;
				// echo "<br/>Contact Last Name: " . $contactLName;
				// echo "<br/>Contact Email: " . $contactEmail;
				// echo "<br/>Contact Phone Number" . $contactPhone; 
				// echo "<br/><br/>";	

			}
		} // End if statement to filter data
	} // End opp links loop
	
} //End opportunities loop

// END INSIGHTLY DATA

// START MAILCHIMP IMPORT

if(isset($batch)){ // Send confirmation email when data transferred.
	$api = new MCAPI($apikeyMC); // Connect with MailChimp

	// Set MailChimp options
	$optin = false; //no, don't send optin emails
	$up_exist = true; // yes, update currently subscribed users
	$replace_int = false; // no, add interest, don't replace

	// Use listBatchSubscribe method to import $batch to MailChimp
	$vals = $api->listBatchSubscribe($listId, $batch, $optin, $up_exist, $replace_int);

	// Report errors, if there are any
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

	// Send email with Mailchimp Errors to see what was imported and where errors occurred.
		$to = $completionToEmail; // This is set in config.inc.php
		$subject = "Insightly to Mailchimp Transfer Completed [" . $today . "]. Errors:" . $vals['error_count']."\n; Added: ".$vals['add_count'] ."\n; Updated: ". $vals['update_count'] ."\n";
		if(isset($errorList)) {
			$body = "**Some errors occurred during import**\n" . $errorList;
		} else {
			$body = "Import done with no errors. Woo hoo!";
		}

		// Set headers for the the notification email - variables all set in config.inc.php
		$headers = "From: " . $completionFromEmail . "\r\n";
		$headers .= "Reply-To: " . $completionReplyEmail . "\r\n"; 
		$headers .= "Cc: " . $completionCCEmail . "\r\n";

		//Send email
		mail($to, $subject, $body, $headers);

	}

// If there are no new records to transfer, we still send a notification saying just that.
} else { 
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
