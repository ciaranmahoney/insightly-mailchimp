*Insightly Mailchimp*
===================

This gets opportunity data from Insightly and sends to Mailchimp. It is designed for a very specific use case (for a small college) but may be useful for others who need to hack together a quick connector for marketing automation, like I did.

The script is run as a nightly cron job to get all opportunities that were changed/added by the sales team during the day.

It basically gets a list of opportunities that were updated "today", gets the related contact and finds that contact's contact info, along with a few other pipeline related data points. Finally, it sends the data to Mailchimp - Mailchimp is setup to  triggers a series of emails depending on data updated by this script.
