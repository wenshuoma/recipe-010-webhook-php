# Webhook-PHP recipe
This directory includes the source for the PHP Webhook recipe example and enables it to be run on a free Heroku or Microsoft Azure App Service server.

The /web directory holds the complete example including assets, sample documents, etc.

The top level files are used to manage and configure the example on the [Heroku](https://www.heroku.com/) or MS Azure platforms.


## Run the recipe on Heroku 
The recipe source can be run on [Heroku](https://www.heroku.com/) using the free service level. No credit card needed!

[![Deploy](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy)

Click the Deploy button, then enter your DocuSign Developer Sandbox credentials on the form in the Heroku dashboard. Then press the View button at the bottom of the dashboard screen when it is enabled by the dashboard.

## Run the recipe on your own server

### Get Ready
Your server needs PHP 5.5 or later.

Your server **must** have an address that is visible and accessible from the public internet. Unless that is the case, the DocuSign platform will not be able to post the notification messages *to* your server.

You need an email address and password registered with the free DocuSign Developer Sandbox system. You also need a free Integration Key for your DocuSign account. See the [DocuSign Developer Center](https://www.docusign.com/developer-center) to sign up.

### How to do it
Make the /web directory available on your web server and navigate to /web/index.php from a JavaScript enabled browser.

## Run the recipe on MS Azure
The recipe source, as is, works on the [MS Azure App Service](https://azure.microsoft.com/en-us/services/app-service/) using the free service level. No credit card needed!

Please see the instructions on the recipe page on the DocuSign DevCenter for running the example MS Azure

