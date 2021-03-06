<?php
namespace Stanford\WellReminders;

// Load trait
require_once "emLoggerTrait.php";

use ExternalModules\ExternalModules;
use REDCap;
use Message;

class WellReminders extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    /**
     * This is the cron task specified in the config.json
     */
    public function startCron() {
        $this->emDebug("Cron Args",func_get_args());

        $start_times = array("10:00");
        $run_days    = array("sun");
        $cron_freq = 3600; //weekly

        $this->emDebug("Starting Cron : Check if its in the right time range");
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        echo "here is the useragent : $user_agent <br>";

        if ($this->timeForCron(__FUNCTION__, $start_times, $cron_freq, $run_days)  || strpos($user_agent,"Chrome") > -1) {
            // DO YOUR CRON TASK
            $this->emDebug("DoCron");

            $db_enabled = ExternalModules::getEnabledProjects($this->PREFIX);
            echo "start getting emails";

            $lastyear    = date("Y-m-d",strtotime("-1 year")); 
            $daily_count = array();
            //while ($proj = db_fetch_assoc($db_enabled)) {
            while($proj = $db_enabled->fetch_assoc()){
                $pid = $proj_id = $project_id = $proj['project_id'];
                $this->emDebug("Processing " . $pid);

                $conditions = array(
                     "Consented but not setup password yet (portal_email_verified + !portal_consent_ts)"
                    ,"Consented but not started the survey yet (portal_consent_ts + !core_fitness_level)"
                    ,"Started the survey but not completed yet (core_fitness_level + !core_mail_zip both INFERRED)"
                    ,"On the second year and not completed yet"
                    ,"Not completed the long anniversary survey"
                );

                echo "<h3> Here are the conditions we test for (can take a long time) for project $pid</h3>";
                echo "<pre>";
                print_r($conditions);
                echo "</pre>";

                $send_emails    = array();
                
                // √ consented but no password set up
                // we know they clicked on the link in their email
                // we know they have not set up password
                // we are just assuming they clicked on the 'i consent/agree' button
                $consented_no_pw = REDCap::getData($pid,'array', null, array('id'
                                                            ,'portal_consent_ts' //filled out security questions
                                                            ,'portal_email_verified_ts'
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ), array('enrollment_arm_1')
                                                            , null, false, true, false
                                                            , '[portal_consent_ts] = ""    
                                                                AND [portal_email_verified_ts] >= "'.$lastyear.'" 
                                                                AND [portal_unsubscribe] != 1    
                                                                AND [user_test_data] != 1   
                                                                AND [portal_undeliverable_email] != 1   
                                                                AND [email_reminders_count] < 3'
                                                            , true, true ); 
                $daily_count[] =  "Consented_no_pw : ".count($consented_no_pw)."\r\n";
                foreach($consented_no_pw as $user){
                    $user               = array_shift($user);
                    $uid                = $user["id"];
                    $fname              = ucfirst($user["portal_firstname"]);
                    $lname              = ucfirst($user["portal_lastname"]);
                    $email              = $user["portal_email"];
                    $count              = $user["email_reminders_count"];
                    $send_emails[$uid]  = array(
                                             "fname"    => $fname
                                            ,"lname"    => $lname
                                            ,"email"    => $email
                                            ,"count"    => $count
                                            ,"type"     => 0 //"Reminder : Please complete the WELL for Life Brief Scale Survey"
                                        );
                }

                //√ CONSENTED BUT NOT STARTED
                $consented_pw_notstart = REDCap::getData($pid,'array', null, array('id'
                                                            ,'portal_consent_ts' //filled out security questions
                                                            ,'portal_email_verified_ts'
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ,'portal_email_act_token'
                                                            ), array('enrollment_arm_1')
                                                            , null, false, true, false
                                                            , '[portal_consent_ts] != ""    
                                                                AND [portal_consent_ts] >= "'.$lastyear.'" 
                                                                AND [core_fitness_level] = ""   
                                                                AND [wellbeing_questions_complete] != "2" 
                                                                AND [portal_unsubscribe] != 1    
                                                                AND [user_test_data] != 1   
                                                                AND [email_reminders_count] < 3'
                                                            , true, true ); 
                $daily_count[] =  "Consented_pw_notstart : ".count($consented_pw_notstart)."\r\n";
                foreach($consented_pw_notstart as $user){
                    $user               = array_shift($user);
                    $uid                = $user["id"];
                    $fname              = ucfirst($user["portal_firstname"]);
                    $lname              = ucfirst($user["portal_lastname"]);
                    $email              = $user["portal_email"];
                    $count              = $user["email_reminders_count"];
                    $code               = $user["portal_email_act_token"];

                    $send_emails[$uid]  = array(
                                             "fname"    => $fname
                                            ,"lname"    => $lname
                                            ,"email"    => $email
                                            ,"count"    => $count
                                            ,"type"     => 1
                                            ,"code"     => $code
                                        );
                }

                //√ STARTED BUT NOT COMPLETE
                $surveystart_nofinish = REDCap::getData($pid,'array', null, array('id'
                                                            ,'portal_consent_ts' //filled out security questions
                                                            ,'portal_email_verified_ts'
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ,'portal_email_act_token'
                                                            ), array('enrollment_arm_1')
                                                            , null, false, true, false
                                                            , '[portal_consent_ts] != ""    
                                                                AND [portal_consent_ts] >= "'.$lastyear.'"     
                                                                AND [your_feedback_complete] != 2    
                                                                AND [portal_unsubscribe] != 1    
                                                                AND [user_test_data] != 1
                                                                AND [portal_undeliverable_email] != 1      
                                                                AND [email_reminders_count] < 3'
                                                            , true, true ); 
                $daily_count[] =   "Surveystart_nofinish : ".count($surveystart_nofinish)."\r\n";
                foreach($surveystart_nofinish as $user){
                    $user               = array_shift($user);
                    $uid                = $user["id"];
                    $fname              = ucfirst($user["portal_firstname"]);
                    $lname              = ucfirst($user["portal_lastname"]);
                    $email              = $user["portal_email"];
                    $count              = $user["email_reminders_count"];
                    $code               = $user["portal_email_act_token"];

                    $send_emails[$uid]  = array(
                                             "fname"    => $fname
                                            ,"lname"    => $lname
                                            ,"email"    => $email
                                            ,"count"    => $count
                                            ,"type"     => 2
                                            ,"code"     => $code
                                        );
                }


                //01/15/19 - Second & THIRD Year being sent from MAIL CHIMP Manaually

                //√ SECOND YEAR, BUT BRIEF NOT STARTED COMPLETED
                //use well_score because short survey is in different project
//                $anniversary_end    = date('Y-m-d', strtotime('-1 years'));
//                $anniversary_start  = date('Y-m-d', strtotime('-2 years'));
//                $brief_reminder     = REDCap::getData($pid,'array', null, array('id'
//                                                            ,'portal_consent_ts' //clicked on consent  + filled out password security qs
//                                                            ,'portal_firstname'
//                                                            ,'portal_lastname'
//                                                            ,'portal_email'
//                                                            ,'email_reminders_count'
//                                                            ,'well_score'
//                                                            ), array('enrollment_arm_1')
//                                                            , null, false, true, false
//                                                            , '[enrollment_arm_1][portal_consent_ts] >= "' . $anniversary_start . '"
//                                                            AND [enrollment_arm_1][portal_consent_ts] < "' . $anniversary_end . '"
//                                                            AND [short_anniversary_arm_1][well_score_long] = ""
//                                                            AND [enrollment_arm_1][portal_unsubscribe] != 1
//                                                            AND [enrollment_arm_1][user_test_data] != 1
//                                                            AND [enrollment_arm_1][email_reminders_count] < 3'
//                                                            , true, true );
//                $daily_count[] =  "Brief_reminder : ".count($brief_reminder)."\r\n";
//                foreach($brief_reminder as $user){
//                    $user               = array_shift($user);
//                    $uid                = $user["id"];
//                    $fname              = ucfirst($user["portal_firstname"]);
//                    $lname              = ucfirst($user["portal_lastname"]);
//                    $email              = $user["portal_email"];
//                    $count              = $user["email_reminders_count"];
//
//                    $send_emails[$uid]  = array(
//                                             "fname"    => $fname
//                                            ,"lname"    => $lname
//                                            ,"email"    => $email
//                                            ,"count"    => $count
//                                            ,"type"     => 3
//                                        );
//                }

                //√ THIRD ANNIVERSARY , NOT STARTED
//                $anniversary_end    = date('Y-m-d', strtotime('-2 years'));
//                $anniversary_start  = date('Y-m-d', strtotime('-3 years'));
//                $secondlong         = REDCap::getData($pid,'array', null, array('id'
//                                                            ,'portal_consent_ts' //clicked on consent  + filled out password security qs
//                                                            ,'portal_firstname'
//                                                            ,'portal_lastname'
//                                                            ,'portal_email'
//                                                            ,'email_reminders_count'
//                                                            ,'well_score'
//                                                            ), array('enrollment_arm_1', 'anniversary_2_arm_1')
//                                                            , null, false, true, false
//                                                            , '[enrollment_arm_1][portal_consent_ts] < "'.$anniversary_end.'"
//                                                            AND [enrollment_arm_1][portal_consent_ts] != ""
//                                                            AND [enrollment_arm_1][portal_unsubscribe] != 1
//                                                            AND [enrollment_arm_1][user_test_data] != 1
//                                                            AND [enrollment_arm_1][email_reminders_count] < 3'
//                                                            , true, true );
//                $daily_count[] =   "Secondlong : ".count($secondlong)."\r\n";
//                foreach($secondlong as $user){
//                    $user               = array_shift($user);
//                    $uid                = $user["id"];
//                    $fname              = ucfirst($user["portal_firstname"]);
//                    $lname              = ucfirst($user["portal_lastname"]);
//                    $email              = $user["portal_email"];
//                    $count              = $user["email_reminders_count"];
//
//                    $send_emails[$uid]  = array(
//                                             "fname"    => $fname
//                                            ,"lname"    => $lname
//                                            ,"email"    => $email
//                                            ,"count"    => $count
//                                            ,"type"     => 4
//                                        );
//                }

                $this->emDebug("Gathering records that match criteria");

                // Export ALL data in ARRAY forma

                $websiteName    = "WELL For Life";
                $emailAddress   = "wellforlife@stanford.edu";

                $update_reminder_count = array();
                foreach($send_emails as $uid => $user){
                    $fname = $user["fname"];
                    $lname = $user["lname"];
                    $email = $user["email"];
                    $type  = $user["type"];
                    $count = $user["count"];
                    $code  = $user["code"];

                    $email_msg  = prepareEmail($fname, $lname, $type,$code);
                    emailReminder($fname, $email, $email_msg["body"], $email_msg["subject"]);

                    echo "An email was sent to $fname $lname ($email) ; $subject" . "<br>";
                    $this->emDebug("An email was sent to $fname $lname ($email) ; $subject" . "<br>");

                    $update_reminder_count[] =  array(  "id" => $uid
                                                        ,"email_reminders_count"    => $count+1
                                                );
                }
                $json_data      = json_encode($update_reminder_count);
                $response       = REDCap::saveData($pid, 'json', $json_data, 'overwrite');

                emailReminder("Katy Peng", "katypeng@stanford.edu", "", count($send_emails) .  " Daily email reminders sent count");
                emailReminder("Irvin Szeto", "irvins@stanford.edu", "", count($send_emails) .  " Daily email reminders sent count");
            }
        }
    }

    /**
     * Utility function for doing crons at a specified time
     * @param $cron_name        - name for recording last run timestamp
     * @param $start_times      - array of start times as in "08:00", "12:00"
     * @param $cron_freq        - cron_frequency in seconds
     * @return bool             - returns true/false telling you if the cron should be done
     */
    public function timeForCron($cron_name, $start_times, $cron_freq, $run_days) {
        // Name of key in external module settings for last-run timestamp
        $cron_status_key = $cron_name . "_cron_last_run_ts";

        // Get the current time (as a unix timestamp)
        $now_ts = time();
        $day    = strtolower(Date("D"));

        $this->emDebug("The days is " . $day);

        if(array_search($day,$run_days) !== false){
            $this->emDebug("the correct day : " . $day);
            foreach ($start_times as $start_time) {
                // Convert our hour:minute value into a timestamp
                $dt = new \DateTime($start_time);
                $start_time_ts = $dt->getTimeStamp();

                // Calculate the number of minutes since the start_time
                $delta_min = ($now_ts-$start_time_ts) / 60;
                $cron_freq_min = $cron_freq/60;

                $this->emDebug("now : $now_ts , deltamin : $delta_min, cronfreqmin : $cron_freq_min" );
                // To reduce database overhead, we will only check to see if we should run if we are between 0-2x the cron frequency
                if ($delta_min >= 0 && $delta_min <= $cron_freq_min) {

                    // Let's see if we have already run this cron by looking up the last-run value
                    $last_cron_run_ts = $this->getSystemSetting($cron_status_key);

                    $this->emDebug("delta time OK, last run $last_cron_run_ts");

                    // If the start of this cron zone is less than our last $start_time_ts, then we should run the cron job
                    if (empty($last_cron_run_ts) || $last_cron_run_ts < $start_time_ts) {

                        // Update our last_run timestamp
                        $this->setSystemSetting($cron_status_key, $now_ts);

                        // Call our actual cronjob method
                        $this->emDebug("timeForCron TRUE");
                        return true;
                    }
                }
            }
        }
        $this->emDebug("timeForCron FALSE");
        return false;
    }

}

function prepareEmail($fname, $lname, $type, $ACTIVATION_CODE = NULL){
    $email_greeting_a       = array();
    $email_greeting_a[]     = "Good morning, $fname!<br/>";
    $email_greeting_a[]     = "We noticed that is time for you to re-engage with where you left off with WELL for Life. Below are ways to get the most out of your experience:<br/>";

    $email_greeting_b       = array();
    $subject                = "Stanford WELL for Life wants to hear from you!";
    switch($type){
        case 0:
        $email_greeting_b[] = "Complete your WELL for Life registration: receive your custom well-being score after registering and completing the Stanford WELL for Life Survey! <br/>";
        $email_greeting_b[] = "Follow the link to continue your registration: <a href='https://wellforlife-portal.stanford.edu/register.php?activation=".$ACTIVATION_CODE."'></a>";
        $subject            = "Complete your WELL for Life registration";
        break;

        case 1:
        case 4:
        $email_greeting_b[] = "Start the Stanford WELL for Life Survey: receive your custom well-being score upon completion! <a href='https://wellforlife-portal.stanford.edu'>Login</a> to start.";
        $subject            = "Start the Stanford WELL for Life Survey";
        break;

        case 2:
        $email_greeting_b[] = "Finish the Stanford WELL for Life Survey: receive your custom well-being score upon completion! <a href='https://wellforlife-portal.stanford.edu'>Login</a> to finish.";
//        $email_greeting_b[] = "Join our mini-challenge: can you improve your well-being by focusing on changing one area of well-being? Visit <a href='https://wellforlife-portal.stanford.edu/'>WELL for Life</a> website to find out what the current mini-challenge is!<br/>";
        $subject            = "Finish the Stanford WELL for Life Survey";
        break;

        case 3:
        $email_greeting_b[] = "Receive your custom well-being score after completing the Stanford WELL for Life Scale!<br/>";
        $subject            = "Finish the Stanford WELL for Life Scale";
        break;
    }

    $email_greeting_z       = array();
    $email_greeting_z[]     = "For resources and other ways to get involved, visit our <a href='http://wellforlife.stanford.edu'>website</a> or </a><a href='https://wellforlife-portal.stanford.edu/'>login</a> to the portal to check out the WELL for Life newsfeed.  You can follow us on <a href='https://www.facebook.com/wellforlifeatstanford/'>Facebook</a>, <a href='https://www.instagram.com/well_for_life/'>Instagram</a> and <a href='https://twitter.com/well_for_life'>Twitter</a>!<br/>";
    $email_greeting_z[]     = "For questions, comments, concerns, or to unsubscribe please email: <a href='mailto:wellforlife@stanford.edu'>wellforlife@stanford.edu</a> <br/>";
    $email_greeting_z[]     = "Sincerely,";
    $email_greeting_z[]     = "The WELL for Life Team";
    $email_greeting_z[]     = "<i style='font-size:77%;'>Participant rights: contact our IRB at 1-866-680-2906</i>";
 
    $email_greeting         = array_merge($email_greeting_a, $email_greeting_b, $email_greeting_z);

    $email_msg              = array("subject" => $subject, "body" => implode("<br/>",$email_greeting));
    return $email_msg;
}

function emailReminder($fname, $email, $email_msg, $subject){
    $msg = new Message();

    $msg->setTo($email);

    // From Email:
    $from_name  = "Stanford Medicine WELL for Life";
    $from_email = "wellforlife@stanford.edu";
    $msg->setFrom($from_email);
    $msg->setFromName($from_name);
    $msg->setSubject($subject);
    $msg->setBody($email_msg);

    $result = $msg->send();

    if ($result) {
        REDCap::logEvent("Reminder Email sent to $email");
    }
}
