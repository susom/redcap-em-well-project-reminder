<?php
/*
	UserPie Version: 1.0
	http://userpie.com
	
*/
namespace Stanford\WellReminders;

$debug_mode 		= true;

class userPieMail {

	//UserPie uses a text based system with hooks to replace various strs in txt email templates
	public $contents = NULL;

	//Function used for replacing hooks in our templates
	public function newTemplateMsg($template,$additionalHooks){
		global $mail_templates_dir,$debug_mode;
	
		// $this->contents = file_get_contents($mail_templates_dir.$template);
		$this->contents = $template;

		//Check to see we can access the file / it has some contents
		if(!$this->contents || empty($this->contents)){
			if($debug_mode){
				if(!$this->contents){ 
					echo lang("MAIL_TEMPLATE_DIRECTORY_ERROR",array(getenv("DOCUMENT_ROOT")));
					die(); 
				}else if(empty($this->contents)){
					echo lang("MAIL_TEMPLATE_FILE_EMPTY"); 
					die();
				}
			}
			return false;
		}else{
			//Replace default hooks
			$this->contents = replaceDefaultHook($this->contents);
			//Replace defined / custom hooks
			$this->contents = str_replace($additionalHooks["searchStrs"],$additionalHooks["subjectStrs"],$this->contents);

			//Do we need to include an email footer?
			//Try and find the #INC-FOOTER hook
			if(strpos($this->contents,"#INC-FOOTER#") !== FALSE){
				$footer = file_get_contents($mail_templates_dir."email-footer.txt");
				if($footer && !empty($footer)) $this->contents .= replaceDefaultHook($footer); 
				$this->contents = str_replace("#INC-FOOTER#","",$this->contents);
			}
			
			return true;
		}
	}
	
	public function sendMail($email,$subject,$msg = NULL)
	{
		global $websiteName, $emailAddress;
		$header =  "MIME-Version: 1.0\r\n";
		$header .= "Content-type: text/html; charset=iso-8859-1\r\n";
		$header .= "From: ". $websiteName . " <" . $emailAddress . ">\r\n";
		
		//Check to see if we sending a template email.
		$message = ($msg == NULL) ? $this->contents : $msg;
		
		// $message = wordwrap($message, 70);
		
		return mail($email,$subject,$message,$header);
	}
}

// Used by UserPie Email
function replaceDefaultHook($str) {
	global $default_hooks,$default_replace;

	return (str_replace($default_hooks,$default_replace,$str));
}

function emailReminder($fname,$uid,$hooks,$email,$email_template, $email_subject, $email_msg){
	$mail = new userPieMail();

	// Build the template - Optional, you can just use the sendMail function to message
	if(!is_null($email_template) && !$mail->newTemplateMsg($email_template,$hooks)) {
		print_r("error : building template");
	 // logIt("Error building actition-reminder email template", "ERROR");
	} else {
	 // Send the mail. Specify users email here and subject.
	 // SendMail can have a third parementer for message if you do not wish to build a template.

	
	 $email_msg = !empty($hooks) ? str_replace($hooks["searchStrs"],$hooks["subjectStrs"],$email_template) : $email_msg;

	 if(!is_null($email_msg) && !$mail->sendMail($email,$email_subject,$email_msg)) {
	 	print_r("error : sending email");
	    // logIt("Error sending email: " . print_r($mail,true), "ERROR");
	 } else {
	 	print_r("Email sent to $fname ($uid) @ $email <br>");
	    // Update email_act_sent_ts
	 }
	}
}

function prepareEmail($fname, $lname, $type){
    $email_greeting_a       = array();
    $email_greeting_a[]     = "Good morning, we miss you $fname!<br/>";
    $email_greeting_a[]     = "Below are some ways for you to get the most out of your Stanford WELL for Life experience:<br/>";

    $email_greeting_b       = array();
    switch($type){
        case 0:
        $email_greeting_b[] = "Complete your WELL for Life registration: receive your custom well-being score after registering and completing the Stanford WELL for Life Scale!<br/>"; 
        break;

        case 1:
        case 4:
        $email_greeting_b[] = "Start the Stanford WELL for Life Scale: receive your custom well-being score after completing the Stanford WELL for Life Scale!"; 
        break;

        case 2:
        $email_greeting_b[] = "Finish the Stanford WELL for Life Scale: receive your custom well-being score after completing the Stanford WELL for Life Scale!"; 
        $email_greeting_b[] = "Join our mini-challenge: can you improve your well-being by focusing on changing one area of well-being? <a href='https://wellforlife-portal.stanford.edu/'>Login</a> to your portal to find out what this mini-challenge is!<br/>";
        break;

        case 3:
        $email_greeting_b[] = "Finish your Brief Stanford WELL for Life Scale: receive your custom well-being score after completing the Brief Stanford WELL for Life Scale!<br/>";
        break;
    }

    $email_greeting_z       = array();
    $email_greeting_z[]     = "For resources and other ways to get involved, <a href='https://wellforlife-portal.stanford.edu/'>login</a> to the portal to check out the newsfeed and follow us on <a href='https://www.facebook.com/wellforlifeatstanford/'>Facebook</a>, <a href='https://www.instagram.com/well_for_life/'>Instagram</a> and <a href='https://twitter.com/well_for_life'>Twitter</a>!<br/>";
    $email_greeting_z[]     = "For questions, comments, concerns, or to unsubscribe please email: <a href='mailto:wellforlife@stanford.edu'>wellforlife@stanford.edu</a> <br/>";
    $email_greeting_z[]     = "Cheers!";
    $email_greeting_z[]     = "The WELL for Life Team";
    $email_greeting_z[]     = "<i style='font-size:77%;'>Participant rights: contact our IRB at 1-866-680-2906</i>";
 
    $email_greeting         = array_merge($email_greeting_a, $email_greeting_b, $email_greeting_z);
    $email_msg              = implode("<br/>",$email_greeting);
    return $email_msg;
}

?>