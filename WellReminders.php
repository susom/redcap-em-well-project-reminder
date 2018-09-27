<?php
namespace Stanford\WellReminders;

// Load trait
require_once "emLoggerTrait.php";
require_once "class.mail.php";

use ExternalModules\ExternalModules;
use REDCap;


class WellReminders extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    /**
     * This is the cron task specified in the config.json
     */
    public function startCron() {
        $start_times = array("11:30");
        $cron_freq = 604800; //weekly

        $this->emDebug("Starting Cron : Check if its in the right time range");
        if ($this->timeForCron(__FUNCTION__, $start_times, $cron_freq)) {
            // DO YOUR CRON TASK
            $this->emDebug("DoCron");

            $db_enabled = ExternalModules::getEnabledProjects($this->PREFIX);

            while ($proj = db_fetch_assoc($db_enabled)) {
                $pid = $proj['project_id'];
                $this->emDebug("Processing " . $pid);

                $conditions = array(
                     "Consented but not setup password yet (portal_email_verified + !portal_consent_ts)"
                    ,"Consented but not started the survey yet (portal_consent_ts + !core_fitness_level)"
                    ,"Started the survey but not completed yet (core_fitness_level + !core_mail_zip both INFERRED)"
                    ,"On the second year and not completed yet"
                    ,"Not completed the long anniversary survey"
                );

                $send_emails = array();

                // √ consented but no password set up
                // we know they clicked on the link in their email
                // we know they have not set up password
                // we are just assuming they clicked on the 'i consent/agree' button
                $consented_no_pw = REDCap::getData('array', null, array('id'
                                                            ,'portal_consent_ts' //filled out security questions
                                                            ,'portal_email_verified_ts'
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ), null
                                                            , null, false, true, false
                                                            , '[portal_consent_ts] = "" 
                                                            AND [portal_email_verified_ts] != "" 
                                                            AND [portal_unsubscribe] != 1 
                                                            AND [user_test_data] != 1
                                                            AND [email_reminders_count] < 3'
                                                            , true, true ); 
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
                $consented_pw_notstart = REDCap::getData('array', null, array('id'
                                                            ,'portal_consent_ts' //filled out security questions
                                                            ,'portal_email_verified_ts'
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ), array('enrollment_arm_1')
                                                            , null, false, true, false
                                                            , '[portal_consent_ts] != "" 
                                                            AND [core_fitness_level] = "" 
                                                            AND [core_physical_illness] = "" 
                                                            AND [core_feedback] = "" 
                                                            AND [portal_unsubscribe] != 1 
                                                            AND [user_test_data] != 1
                                                            AND [email_reminders_count] < 3'
                                                            , true, true ); 
                foreach($consented_pw_notstart as $user){
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
                                            ,"type"     => 1 
                                        );
                }

                //√ STARTED BUT NOT COMPLETE
                $surveystart_nofinish = REDCap::getData('array', null, array('id'
                                                            ,'portal_consent_ts' //filled out security questions
                                                            ,'portal_email_verified_ts'
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ), array('enrollment_arm_1')
                                                            , null, false, true, false
                                                            , '[portal_consent_ts] != "" 
                                                            AND [core_fitness_level] != "" 
                                                            AND [core_feedback] = "" 
                                                            AND [portal_unsubscribe] != 1 
                                                            AND [user_test_data] != 1
                                                            AND [email_reminders_count] < 3'
                                                            , true, true ); 
                foreach($surveystart_nofinish as $user){
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
                                            ,"type"     => 2 
                                        );
                }

                //√ SECOND YEAR, BUT BRIEF NOT STARTED COMPLETED
                //use well_score because short survey is in different project
                $anniversary_end    = date('Y-m-d', strtotime('-1 years'));
                $anniversary_start  = date('Y-m-d', strtotime('-2 years'));
                $brief_reminder     = REDCap::getData('array', null, array('id'
                                                            ,'portal_consent_ts' //clicked on consent  + filled out password security qs
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ,'well_score'
                                                            ), array('enrollment_arm_1', 'short_anniversary_arm_1')
                                                            , null, false, true, false
                                                            , '[enrollment_arm_1][portal_consent_ts] >= "' . $anniversary_start . '" 
                                                            AND [enrollment_arm_1][portal_consent_ts] < "' . $anniversary_end . '" 
                                                            AND [short_anniversary_arm_1][well_score] = "" 
                                                            AND [enrollment_arm_1][portal_unsubscribe] != 1 
                                                            AND [enrollment_arm_1][user_test_data] != 1
                                                            AND [enrollment_arm_1][email_reminders_count] < 3'
                                                            , true, true ); 
                foreach($brief_reminder as $user){
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
                                            ,"type"     => 3 
                                        );
                }

                //√ THIRD ANNIVERSARY , NOT STARTED
                $anniversary_end    = date('Y-m-d', strtotime('-2 years'));
                $anniversary_start  = date('Y-m-d', strtotime('-3 years'));
                $secondlong         = REDCap::getData('array', null, array('id'
                                                            ,'portal_consent_ts' //clicked on consent  + filled out password security qs
                                                            ,'portal_firstname'
                                                            ,'portal_lastname'
                                                            ,'portal_email'
                                                            ,'email_reminders_count'
                                                            ,'well_score'
                                                            ), array('enrollment_arm_1', 'anniversary_2_arm_1')
                                                            , null, false, true, false
                                                            , '[enrollment_arm_1][portal_consent_ts] >= "' . $anniversary_start . '" 
                                                            AND [enrollment_arm_1][portal_consent_ts] < "' . $anniversary_end . '" 
                                                            AND [anniversary_2_arm_1][core_feedback] = "" 
                                                            AND [enrollment_arm_1][portal_unsubscribe] != 1 
                                                            AND [enrollment_arm_1][user_test_data] != 1
                                                            AND [enrollment_arm_1][email_reminders_count] < 3'
                                                            , true, true ); 
                foreach($secondlong as $user){
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
                                            ,"type"     => 4 
                                        );
                }

                $this->emDebug("Gathering records that match criteria");

                // Export ALL data in ARRAY forma
                $subject        = "Stanford WELL for Life wants to hear from you!";
                $websiteName    = "WELL For Life";
                $emailAddress   = "wellforlife@stanford.edu";

                $update_reminder_count = array();
                foreach($send_emails as $uid => $user){
                    $fname = $user["fname"];
                    $lname = $user["lname"];
                    $email = $user["email"];
                    $type  = $user["type"];
                    $count = $user["count"];

                    $email_msg  = prepareEmail($fname, $lname, $type);
                    emailReminder($fname, $uid, array(), $email, null, $subject, $email_msg);   
                    $this->emDebug("An email was sent to $fname $lname ($email) ; $subject" . "<br>");

                    $update_reminder_count[] =  array(  "id" => $uid
                                                        ,"email_reminders_count"    => $count+1
                                                );
                }
                $json_data      = json_encode($update_reminder_count);
                $response       = REDCap::saveData('json', $json_data, 'overwrite');
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
    public function timeForCron($cron_name, $start_times, $cron_freq) {
        // Name of key in external module settings for last-run timestamp
        $cron_status_key = $cron_name . "_cron_last_run_ts";

        // Get the current time (as a unix timestamp)
        $now_ts = time();

        foreach ($start_times as $start_time) {
            // Convert our hour:minute value into a timestamp
            $dt = new \DateTime($start_time);
            $start_time_ts = $dt->getTimeStamp();

            // Calculate the number of minutes since the start_time
            $delta_min = ($now_ts-$start_time_ts) / 60;
            $cron_freq_min = $cron_freq/60;

            // To reduce database overhead, we will only check to see if we should run if we are between 0-2x the cron frequency
            if ($delta_min >= 0 && $delta_min <= $cron_freq_min) {

                // Let's see if we have already run this cron by looking up the last-run value
                $last_cron_run_ts = $this->getSystemSetting($cron_status_key);

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
        $this->emDebug("timeForCron FALSE");
        return false;
    }

}