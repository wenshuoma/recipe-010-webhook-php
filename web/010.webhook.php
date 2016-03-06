<?php
#
# 010.webhook.php recipe
#
# This recipe demonstrates use of the DocuSign webhook feature for 
# status reports about an envelope's transactions.
#
# Run this script on a webserver that is accessible from the public
# internet. If you run this script on a machine that can't be called
# from the internet, the recipe won't work since the DocuSign platform
# needs to call *to* this script/this url with status updates.
#
# This script expects an "op" query parameter. Values for "op" --
#   send1    -- The script shows the parameters that will be used for the 
#               envelope.
#   send2    -- The script will send an envelope to a recipient to sign.
#               The envelope create call will use the webhook setting
#               so we'll be called by DocuSign when there's an event.
#   status   -- Show the status events that we've received from DocuSign.
#	status_info  -- Info about the envelope
#	status_items -- List of info about the envelope's event items received
#   webhook  -- An incoming status event from DocuSign platform.
#
#
#
###############################################################################
###############################################################################

# See 010.webhook_lib.php for the settings and guts of the
# recipe

###############################################################################
###############################################################################

function show_options() {
	show_header();
	show_welcome();
	show_footer(['navbar' => 'li_home']);
}

###############################################################################
###############################################################################

function do_send1() {
	# Show a button that sends the signature request
	# When pressed, we're called with op=send2
	show_header();
	$webhook_lib = new Webhook_lib();
	$ok = $webhook_lib->send_1();
	if ($ok) {
		echo "<h5>DocuSign Account id: " . $webhook_lib->ds_account_id . "</h5>";
		echo "<h5>Signer: " . $webhook_lib->ds_signer1_name . " &lt;" . $webhook_lib->ds_signer1_email . "&gt;</h5>";
		echo "<h5>Webhook url: " . $webhook_lib->webhook_url . "</h5>";	
?>
    <p class="margintop"><button id="sendbtn" type="button" class="btn btn-primary">Send the signature request!</button>
		<span style="margin-left:3em;"><a href="<?php echo basename(__FILE__); ?>?op=send1">Reset</a></span></p>
	<div class="margintop" id="target"></div>
<?php
	} else {
		# not ok
		echo "<h5 class='margintop'>Please solve the problem and retry.</h5>";
	}
	
	show_footer(['navbar' => 'li_send', 
		'send_param' => ["ds_signer1_name"  => $webhook_lib->ds_signer1_name,
						 "ds_signer1_email" => $webhook_lib->ds_signer1_email,
				 		 "ds_cc1_name"  => $webhook_lib->ds_cc1_name,
				 		 "ds_cc1_email" => $webhook_lib->ds_cc1_email,
				 		 "webhook_url" => $webhook_lib->webhook_url,						 
						 "button" => "sendbtn",
						 "url" => basename(__FILE__) . "?op=send2",
						 "target" => "target"]
	]);
}

###############################################################################
###############################################################################

function do_send2() {
	# Do the send!
	# This request comes in via Ajax
	$params = json_decode(file_get_contents('php://input'), true);
	if ($params !== null) {
		$params['baseurl'] = basename(__FILE__);
		$webhook_lib = new Webhook_lib();
		// The result includes the html for showing the View status button, and more
		$result = $webhook_lib->send_2($params);
	} else {
		$result = ["ok" => false, "html" => "<h2>Bad JSON input!</h2>"];		
	}
	header('Content-type: application/json');
	echo json_encode($result, JSON_PRETTY_PRINT);
}

###############################################################################
###############################################################################

function webhook() {
	// An incoming call from the DocuSign platform
	// See the Connect guide: 
	//    https://www.docusign.com/sites/default/files/connect-guide_0.pdf
	$webhook_lib = new Webhook_lib();
	$webhook_lib->webhook_listener();
}

###############################################################################
###############################################################################

function do_status() {
	// Shows the empty status screen.
	// All the hard work is done in the 010.webhook.js file
	// Required query parameter: envelope_id
	show_header();
	show_status();
	if (array_key_exists ("envelope_id", $_GET)) {
		$envelope_id = $_GET["envelope_id"];
	} else {
		$envelope_id = false;
		echo "<h3>Missing envelope_id query parameter!</h3>";
	}
	show_footer([
		'status_envelope_id' => $envelope_id,
		'url' => basename(__FILE__)
	]);
}

###############################################################################
###############################################################################

function do_ajax($op) {
	# Request coming in via Ajax using JSON request body
	$webhook_lib = new Webhook_lib();
	$result = $webhook_lib->$op(json_decode(file_get_contents('php://input'), true));
	header('Content-type: application/json');
	echo json_encode($result, JSON_PRETTY_PRINT);
}
###############################################################################
###############################################################################

function show_header() {
	?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <link rel="icon" href="https://www.docusign.com/sites/all/themes/custom/docusign/favicons/favicon.ico">
    <title>DocuSign Webhook recipe</title>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" 	integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">

	<!-- Optional theme -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" 	integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="assets_master/ie10-viewport-bug-workaround.css" rel="stylesheet">
    <link href="assets_master/recipes.css" rel="stylesheet">  <!-- Some custom styles -->
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body class="recipes">
    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">DocuSign Webhook Recipe</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li id="li_home"><a href="010.webhook.php">Home</a></li>
            <li id="li_send"><a href="010.webhook.php?op=send1">Send Signature Request</a></li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </nav>

    <div class="container">	
<?php
}

###############################################################################
###############################################################################

function show_welcome(){
?>
<div class="intro">
	<h1>DocuSign Webhook Recipe</h1>
	<h2>No More Polling!</h2>
	<p class="lead">Please use the navigation bar, above, to first send the signature request, then view the signature events via a webhook.</p>
</div>

<?php
}

###############################################################################
###############################################################################

function show_status(){
?>
<h1>DocuSign Webhook Recipe: Envelope Status</h1>
<div id="env_info"></div>
<div class="row fill">
	<div class="wrapper">
  	  <div id="status_left" class="col-md-3">
		  <p id="working">Working</p>
		  <ul id="toc" class="list-unstyled"></ul>
		  <ul class="margintop"><li>Click on an entry to view it</li><li>Italics indicate a change</li></ul>
	  </div>
  	  <div id="right_column" class="col-md-9">
		  <div id="xml_info">
  		  	<div id="countdown"><h2>Waiting for first results… <span id="counter"></span></h2></div>
		  </div>
		  <div id="editor"></div>
		  <p class="margintop">&nbsp;</p>
	  </div>
	<div>
</div>	
<?php	
}

###############################################################################
###############################################################################

function show_footer($params){
	// Params: ['navbar' => 'li_home']
	echo "\n<script>ds_params = " . json_encode($params) . ";</script>\n";
	
	?>
</div><!-- /.container -->

<!-- Mustache template for toc entries -->
<!-- See https://github.com/janl/mustache.js -->
<script id="toc_item_template" type="x-tmpl-mustache">
<li class="toc_item">
	<h4 class="{{envelope_status_class}}">Envelope: {{envelope_status}}</h4>
	{{#recipients}}
		<p>{{type}}: {{user_name}}<br/>
			<span class="{{status_class}}">Status: {{status}}</span>
		</p>
	{{/recipients}}
</li>
</script>

<!-- Mustache template for displaying xml file -->
<!-- XML in Ace editor, see http://stackoverflow.com/a/16147926/64904 -->
<script id="xml_file_template" type="x-tmpl-mustache">
	<h3>XML Notification Content</h3>
	<h5 class="{{envelope_status_class}}">Envelope status: {{envelope_status}}</h5>
	<p class="margintop">Recipients</p>
	<ul>
	{{#recipients}}
		<li>{{type}}: {{user_name}} <span class="{{status_class}}">Status: {{status}}</span></li>
	{{/recipients}}
	</ul>
	<h5><a href='{{xml_url}}' target='_blank'>Download the XML file</a></h5>
	{{#documents.length}}
		<p>Documents<ul>
			{{#documents}}
				<li>Document ID {{document_ID}}: {{name}} <a href='{{url}}' target='_blank'>Download</a></li>
			{{/documents}}
		</ul></p>
	{{/documents.length}}
</script>

<!-- Bootstrap core JavaScript -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<!-- Latest compiled and minified JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script><!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="assets_master/ie10-viewport-bug-workaround.js"></script>
<script src="bower_components/mustache.js/mustache.min.js"></script>
<script src="https://cdn.jsdelivr.net/g/ace@1.2.2(noconflict/ace.js+noconflict/mode-xml.js+noconflict/theme-chrome.js)"></script>

<script src="010.webhook.js"></script> <!-- nb. different assets directory -->
</body>
</html>
<?php	
}

###############################################################################
###############################################################################

# Mainline
ob_start("ob_gzhandler");
include "lib_master_php/ds_recipe_lib.php";
include "010.webhook_lib.php";

if (! array_key_exists ("op", $_GET)) {
	show_options();
} else {
	$op = $_GET["op"];
	switch ($op) {
	    case "send1":
	        do_send1();
	        break;
	    case "send2":
		    do_send2();
		    break;
	    case "status":
	        do_status();
	        break;
	    case "status_info":
			do_ajax($op);
	        break;
	    case "status_items":
	        do_ajax($op);
	        break;
	    case "webhook":
	        webhook();
	        break;
	}
}
