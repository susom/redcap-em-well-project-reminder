# proj-well-reminders

A cron-task to send weekly reminder emails based on criteria


# Consolidation email list for weekly email (please send on Sunday mornings at 11:30am) through identifying people with cron job
* Consented but not setup password yet (portal_email_verified + !portal_consent_ts)
* Consented but not started the survey yet (portal_consent_ts + !core_fitness_level)
* Started the survey but not completed yet (core_fitness_level + !core_mail_zip both INFERRED)
* On the second year and not completed yet
* 2nd long survey
