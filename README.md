*Insightly Mailchimp*
===================

This get opportunity data from Insightly and send to Mailchimp. It is designed for a very specific use case (for a small college) but may be useful for others who need to hack together a quick connector.

It basically gets a list of opportunities that were updated "today", gets the related contact and finds that contact's contact info, along with a few other pipeline reated data points. Finally, it sends the data to Mailchimp, which then triggers a series of emails depending on various pipeline stages and states.

It is run as a nightly cron job to get all opportunities that were changed/added by the sales team during the day.

