<?php

// 010.webhook_lib.php

class Webhook_lib {
    
    # Settings
    # 
    public $ds_user_email = "***"; 
    public $ds_user_pw = "***"; 
    public $ds_integration_id = "***";
    public $ds_signer1_name = "***";  # Set signer info here or leave as is to use example signers
    public $ds_signer1_email = "***";
    public $ds_cc1_name = "***";  # Set a cc recipient here or leave as is to use example recipients
    public $ds_cc1_email = "***";
    public $ds_account_id; // Set during login process or explicitly by configuration here.
                           // Note that many customers have more than one account! 
                           // A username/pw can access multiple accounts!
    public $ds_base_url; // The base url associated with the account_id.
    public $ds_auth_header;
    public $my_url; // The url for this script. Must be accessible from the internet!
                    // Can be set here or determined dynamically
    public $webhook_url;
    public $doc_filename = "sample_documents_master/NDA.pdf";
    public $doc_document_name = "NDA.pdf";
    public $doc_filetype = "application/pdf";
    
    private $ds_recipe_lib; 
    private $webhook_suffix = "?op=webhook";
    
    private $xml_file_dir = "files/";
    private $doc_prefix = "doc_";

    private $apiClient;
    
    ########################################################################
    ########################################################################

    function __construct() {
        $this->ds_recipe_lib = new DS_recipe_lib($this->ds_user_email, $this->ds_user_pw, 
            $this->ds_integration_id, $this->ds_account_id);
        $this->my_url = $this->ds_recipe_lib->get_my_url($this->my_url);
    }    
    
    ########################################################################
    ########################################################################

    public function send_1() {
        # Prepares for sending the envelope
        $result = $this->login();
        if (! $result['ok']) {
            echo "<h3>" . $result['errMsg'] . "</h3>";
            return false;
        }
        
        $this->webhook_url = $this->my_url . $this->webhook_suffix;
        $this->ds_signer1_name = $this->ds_recipe_lib->get_signer_name($this->ds_signer1_name);
        $this->ds_signer1_email = $this->ds_recipe_lib->get_signer_email($this->ds_signer1_email);
        $this->ds_cc1_name = $this->ds_recipe_lib->get_signer_name($this->ds_cc1_name);
        $this->ds_cc1_email = $this->ds_recipe_lib->get_signer_email($this->ds_cc1_email);
        return true;
    }
    
    
    ########################################################################
    ########################################################################

    public function send_2($params) {
        # Send the envelope
        # $params --
        #   "ds_signer1_name" 
        #   "ds_signer1_email"
        #   "ds_cc1_name"
        #   "ds_cc1_email"
        #   "webhook_url"
        #   "baseurl"

        $result = $this->login();
        if (! $result['ok']) {return ["ok" => false, "html" => "<h3>Problem</h3><p>Couldn't login to DocuSign: " .
            $result['errMsg'] . "</p>"];}

        $webhook_url = $params["webhook_url"];
        $ds_signer1_name = $params["ds_signer1_name"];
        $ds_signer1_email = $params["ds_signer1_email"];
        $ds_cc1_name = $params["ds_cc1_name"];
        $ds_cc1_email = $params["ds_cc1_email"];

        // *** This snippet is from file 010.webhook_lib.php ***
        // The envelope request includes a signer-recipient and their tabs object,
        // and an eventNotification object which sets the parameters for
        // webhook notifications to us from the DocuSign platform    
        $envelope_events = [
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("sent"),
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("delivered"),
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("completed"),
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("declined"),
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("voided"),
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("sent"),
            (new \DocuSign\eSign\Model\EnvelopeEvent())->setEnvelopeEventStatusCode("sent")
        ];
        
        $recipient_events = [
            (new \DocuSign\eSign\Model\RecipientEvent())->setRecipientEventStatusCode("Sent"),
            (new \DocuSign\eSign\Model\RecipientEvent())->setRecipientEventStatusCode("Delivered"),
            (new \DocuSign\eSign\Model\RecipientEvent())->setRecipientEventStatusCode("Completed"),
            (new \DocuSign\eSign\Model\RecipientEvent())->setRecipientEventStatusCode("Declined"),
            (new \DocuSign\eSign\Model\RecipientEvent())->setRecipientEventStatusCode("AuthenticationFailed"),
            (new \DocuSign\eSign\Model\RecipientEvent())->setRecipientEventStatusCode("AutoResponded")
        ];

        $event_notification = new \DocuSign\eSign\Model\EventNotification();
        $event_notification->setUrl($webhook_url);
        $event_notification->setLoggingEnabled("true");
        $event_notification->setRequireAcknowledgment("true");
        $event_notification->setUseSoapInterface("false");
        $event_notification->setIncludeCertificateWithSoap("false");
        $event_notification->setSignMessageWithX509Cert("false");
        $event_notification->setIncludeDocuments("true");
        $event_notification->setIncludeEnvelopeVoidReason("true");
        $event_notification->setIncludeTimeZone("true");
        $event_notification->setIncludeSenderAccountAsCustomField("true");
        $event_notification->setIncludeDocumentFields("true");
        $event_notification->setIncludeCertificateOfCompletion("true");
        $event_notification->setEnvelopeEvents($envelope_events);
        $event_notification->setRecipientEvents($recipient_events);

        $document = new \DocuSign\eSign\Model\Document();
        $document->setDocumentId("1");
        $document->setName($this->doc_document_name);
        $document->setDocumentBase64(base64_encode(file_get_contents($this->doc_filename)));

        $signer = new \DocuSign\eSign\Model\Signer();
        $signer->setEmail($ds_signer1_email);
        $signer->setName($ds_signer1_name);
        $signer->setRecipientId("1");
        $signer->setRoutingOrder("1");
        $signer->setTabs($this->nda_fields());

        $carbon_copy = new \DocuSign\eSign\Model\CarbonCopy();
        $carbon_copy->setEmail($ds_cc1_email);
        $carbon_copy->setName($ds_cc1_name);
        $carbon_copy->setRecipientId("2");
        $carbon_copy->setRoutingOrder("2");

        $recipients = new \DocuSign\eSign\Model\Recipients();
        $recipients->setSigners([$signer]);
        $recipients->setCarbonCopies([$carbon_copy]);

        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition();
        // We want to use the most friendly email subject line.
        // The regexp below removes the suffix from the file name. 
        $envelope_definition->setEmailSubject("Please sign the " . 
            preg_replace('/\\.[^.\\s]{3,4}$/', '', $this->doc_document_name) . " document");
        $envelope_definition->setDocuments([$document]);
        $envelope_definition->setRecipients($recipients);
        $envelope_definition->setEventNotification($event_notification);
        $envelope_definition->setStatus("sent");

        // Send the envelope:
        $envelopesApi = new \DocuSign\eSign\Api\EnvelopesApi($this->apiClient);
        $envelope_summary = $envelopesApi->createEnvelope($this->ds_account_id, $envelope_definition, null);
        if ( !isset($envelope_summary) || $envelope_summary->getEnvelopeId() == null ) {
            return ["ok" => false, html => "<h3>Problem</h3>" .
                "<p>Error calling DocuSign</p>"];
        }
        $envelope_id = $envelope_summary->getEnvelopeId();

        // Create instructions for reading the email
        $html = "<h2>Signature request sent!</h2>" .
            "<p>Envelope ID: " . $envelope_id . "</p>" .
            "<h2>Next steps</h2>" .
            "<h3>1. Open the Webhook Event Viewer</h3>" .
            "<p><a href='" . $params["baseurl"] . "?op=status&envelope_id=" . urlencode($envelope_id) . "'" .
                "  class='btn btn-primary' role='button' target='_blank' style='margin-right:1.5em;'>" .
                "View Events</a> (A new tab/window will be used.)</p>".
            "<h3>2. Respond to the Signature Request</h3>";

        $email_access = $this->ds_recipe_lib->get_temp_email_access($ds_signer1_email);
        if ($email_access !== false) {
            // A temp account was used for the email
            $html .= "<p>Respond to the request via your mobile phone by using the QR code: </p>" .
                "<p>" . $this->ds_recipe_lib->get_temp_email_access_qrcode($email_access) . "</p>" .
                "<p> or via <a target='_blank' href='" . $email_access . "'>your web browser.</a></p>";
        } else {
            // A regular email account was used
            $html .= "<p>Respond to the request via your mobile phone or other mail tool.</p>" .
                "<p>The email was sent to " . $ds_signer1_name . " &lt;" . $ds_signer1_email . "&gt;</p>";
        }

        return [
            'ok'  => true,
            'envelope_id' => $envelope_id,
            'html' => $html,
            'js' => [['disable_button' => 'sendbtn']]];  // js is an array of items
    }


    ########################################################################
    ########################################################################

    public function webhook_listener() {
        // Process the incoming webhook data. See the DocuSign Connect guide
        // for more information
        //
        // Strategy: examine the data to pull out the envelope_id and time_generated fields.
        // Then store the entire xml on our local file system using those fields.
        //
        // If the envelope status=="Completed" then store the files as doc1.pdf, doc2.pdf, etc
        //
        // This function could also enter the data into a dbms, add it to a queue, etc.
        // Note that the total processing time of this function must be less than
        // 100 seconds to ensure that DocuSign's request to your app doesn't time out.
        // Tip: aim for no more than a couple of seconds! Use a separate queuing service
        // if need be.
    
        $data = file_get_contents('php://input');
        $xml = simplexml_load_string ($data, "SimpleXMLElement", LIBXML_PARSEHUGE);
        $envelope_id = (string)$xml->EnvelopeStatus->EnvelopeID;
        $time_generated = (string)$xml->EnvelopeStatus->TimeGenerated;
    
        // Store the file. Create directories as needed
        // Some systems might still not like files or directories to start with numbers.
        // So we prefix the envelope ids with E and the timestamps with T
        $files_dir = getcwd() . '/' . $this->xml_file_dir;
        if(! is_dir($files_dir)) {mkdir ($files_dir, 0755);}
        $envelope_dir = $files_dir . "E" . $envelope_id;
        if(! is_dir($envelope_dir)) {mkdir ($envelope_dir, 0755);}

        $filename = $envelope_dir . "/T" . 
            str_replace (':' , '_' , $time_generated) . ".xml"; // substitute _ for : for windows-land
        $ok = file_put_contents ($filename, $data);
    
        if ($ok === false) {
            // Couldn't write the file! Alert the humans!
            error_log ("!!!!!! PROBLEM DocuSign Webhook: Couldn't store $filename !");
            exit (1);
        }
        // log the event
        error_log ("DocuSign Webhook: created $filename");
        
        if ((string)$xml->EnvelopeStatus->Status === "Completed") {
            // Loop through the DocumentPDFs element, storing each document.
            foreach ($xml->DocumentPDFs->DocumentPDF as $pdf) {
                $filename = $this->doc_prefix . (string)$pdf->DocumentID . '.pdf';
                $full_filename = $envelope_dir . "/" . $filename;
                file_put_contents($full_filename, base64_decode ( (string)$pdf->PDFBytes ));
            }
        }
    }


    ########################################################################
    ########################################################################
    
    public function status_info($params){
        // Info about the envelope
        // Calls /accounts/{accountId}/envelopes/{envelopeId}
        $result = $this->login();
        if (! $result['ok']) {
            return ["ok" => false, "html" => "<h3>Problem</h3><p>Couldn't login to DocuSign: " .
            $result['errMsg'] . "</p>"]; // early return
        }

        $envelopesApi = new DocuSign\eSign\Api\EnvelopesApi($this->apiClient);
        $envelope = $envelopesApi->getEnvelope($this->ds_account_id, $params["envelope_id"], null);

        if (!isset($envelope) || $envelope->getEnvelopeId() == null ) {
            return ["ok" => false, html => "<h3>Problem</h3>" . // early return
                "<p>Error calling DocuSign</p>"];
        }
        return json_decode($envelope, true);
    }  

    ########################################################################
    ########################################################################

    public function status_items($params){
        // List of info about the envelope's event items received
        $files_dir_url = substr($this->my_url, 0, strrpos($this->my_url, '/') + 1) .
            $this->xml_file_dir;
        // remove http or https
        $files_dir_url = str_replace(["http:", "https:"], ["", ""], $files_dir_url);
        
        $files_dir = getcwd() . '/' . $this->xml_file_dir . "E" . $params["envelope_id"];
        
        $results = [];
        if(! is_dir($files_dir)) {
            return $results; // no results!
        }
        
        $dir = new DirectoryIterator($files_dir);
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot() && $fileinfo->isFile() 
              && $fileinfo->getExtension() === "xml") {
                $results[] = $this->status_item($fileinfo->getPathname(), 
                    $fileinfo->getFilename(), $files_dir_url);
            }
        }
        return $results;
    }  
    
    ########################################################################
    ########################################################################

    private function status_item($filepath, $filename, $files_dir_url){
        //  summary info about the notification
        $result = [];
        $xml = simplexml_load_file ($filepath, "SimpleXMLElement", LIBXML_PARSEHUGE);
                
        // iterate through the recipients
        $recipients = [];
        foreach ($xml->EnvelopeStatus->RecipientStatuses->children() as $Recipient) {
            $recipients[] = [
                "type" => (string)$Recipient->Type,
                "email" => (string)$Recipient->Email,
                "user_name" => (string)$Recipient->UserName,
                "routing_order" => (string)$Recipient->RoutingOrder,
                "sent_timestamp" => (string)$Recipient->Sent,
                "delivered_timestamp" => (string)$Recipient->Delivered,
                "signed_timestamp" => (string)$Recipient->Signed,
                "status" => (string)$Recipient->Status
            ];
        }
        
        $documents = [];
        $envelope_id =     (string)$xml->EnvelopeStatus->EnvelopeID;
        // iterate through the documents if the envelope is Completed
        if ((string)$xml->EnvelopeStatus->Status === "Completed") {
            // Loop through the DocumentPDFs element, noting each document.
            foreach ($xml->DocumentPDFs->DocumentPDF as $pdf) {
                $doc_filename = $this->doc_prefix . (string)$pdf->DocumentID . '.pdf';
                $documents[] = [
                    "document_ID" => (string)$pdf->DocumentID,
                    "document_type" => (string)$pdf->DocumentType,
                    "name" => (string)$pdf->Name,
                    "url" => $files_dir_url . 'E' . $envelope_id . '/' . $doc_filename];
            }
        }
        
        $result = [
            "envelope_id" => $envelope_id,
            "xml_url" => $files_dir_url . 'E' . $envelope_id . '/' . $filename,
            "time_generated" => (string)$xml->EnvelopeStatus->TimeGenerated,
            "subject" => (string)$xml->EnvelopeStatus->Subject,
            "sender_user_name" => (string)$xml->EnvelopeStatus->UserName,
            "sender_email" => (string)$xml->EnvelopeStatus->Email,
            "envelope_status" => (string)$xml->EnvelopeStatus->Status,
            "envelope_sent_timestamp" => (string)$xml->EnvelopeStatus->Sent,
            "envelope_created_timestamp" => (string)$xml->EnvelopeStatus->Created,
            "envelope_delivered_timestamp" => (string)$xml->EnvelopeStatus->Delivered,
            "envelope_signed_timestamp" => (string)$xml->EnvelopeStatus->Signed,
            "envelope_completed_timestamp" => (string)$xml->EnvelopeStatus->Completed,
            "timezone" => (string)$xml->TimeZone,
            "timezone_offset" => (string)$xml->TimeZoneOffset,
            "recipients" => $recipients,
            "documents" => $documents];
        return $result;
    }

    ########################################################################
    ########################################################################

    private function nda_fields(){
        // The fields for the sample document "NDA"
        // Create 4 fields, using anchors 
        //   * signer1sig
        //   * signer1name
        //   * signer1company
        //   * signer1date
        
        // This method uses the SDK to create the $fields data structure
        
        $sign_here_tab = new \DocuSign\eSign\Model\SignHere();
        $sign_here_tab->setAnchorString("signer1sig");
        $sign_here_tab->setAnchorXOffset("0");
        $sign_here_tab->setAnchorYOffset("0");
        $sign_here_tab->setAnchorUnits("mms");
        $sign_here_tab->setRecipientId("1");
        $sign_here_tab->setName("Please sign here");
        $sign_here_tab->setOptional("false");
        $sign_here_tab->setScaleValue(1);
        $sign_here_tab->setTabLabel("signer1sig");

        $full_name_tab = new \DocuSign\eSign\Model\FullName();
        $full_name_tab->setAnchorString("signer1name");
        $full_name_tab->setAnchorYOffset("-6");
        $full_name_tab->setFontSize("Size12");
        $full_name_tab->setRecipientId("1");
        $full_name_tab->setTabLabel("Full Name");
        $full_name_tab->setName("Full Name");

        $text_tab = new \DocuSign\eSign\Model\Text();
        $text_tab->setAnchorString("signer1company");
        $text_tab->setAnchorYOffset("-8");
        $text_tab->setFontSize("Size12");
        $text_tab->setRecipientId("1");
        $text_tab->setTabLabel("Company");
        $text_tab->setName("Company");
        $text_tab->setRequired("false");

        $date_signed_tab = new \DocuSign\eSign\Model\DateSigned();
        $date_signed_tab->setAnchorString("signer1date");
        $date_signed_tab->setAnchorYOffset("-6");
        $date_signed_tab->setFontSize("Size12");
        $date_signed_tab->setRecipientId("1");
        $date_signed_tab->setName("Date Signed");
        $date_signed_tab->setTabLabel("Company");

        $fields = new \DocuSign\eSign\Model\Tabs();
        $fields->setSignHereTabs([$sign_here_tab]);
        $fields->setFullNameTabs([$full_name_tab]);
        $fields->setTextTabs([$text_tab]);
        $fields->setDateSignedTabs([$date_signed_tab]);

        return $fields;
    }

    private function nda_fields_raw(){
        // The fields for the sample document "NDA"
        // Create 4 fields, using anchors 
        //   * signer1sig
        //   * signer1name
        //   * signer1company
        //   * signer1date
        
        // This method shows how to create the identical set 
        // of fields by creating the raw associative array
        
        $fields = [
        "signHereTabs" => [[
            "anchorString" => "signer1sig",
            "anchorXOffset" => "0",
             "anchorYOffset" => "0",
            "anchorUnits" => "mms",
            "recipientId" => "1",
            "name" => "Please sign here",
            "optional" => false,
            "scaleValue" => 1,
            "tabLabel" => "signer1sig"]],
        "fullNameTabs" => [[
            "anchorString" => "signer1name",
             "anchorYOffset" => "-6",
            "fontSize" => "Size12",
            "recipientId" => "1",
            "tabLabel" => "Full Name",
            "name" => "Full Name"]],
        "textTabs" => [[
            "anchorString" => "signer1company",
             "anchorYOffset" => "-8",
            "fontSize" => "Size12",
            "recipientId" => "1",
            "tabLabel" => "Company",
            "name" => "Company",
            "required" => false]],
        "dateSignedTabs" => [[
            "anchorString" => "signer1date",
             "anchorYOffset" => "-6",
            "fontSize" => "Size12",
            "recipientId" => "1",
            "name" => "Date Signed",
              "tabLabel" => "Company"]]
        ];
        return $fields;
    }

    ########################################################################
    ########################################################################

    public function login() {
        // Logs into DocuSign    
        $result = $this->ds_recipe_lib->login();
        if ($result['ok']) {
            $this->ds_account_id = $this->ds_recipe_lib->ds_account_id;
            $this->ds_base_url = $this->ds_recipe_lib->ds_base_url;
            $this->ds_auth_header = $this->ds_recipe_lib->ds_auth_header;

            $config = new DocuSign\eSign\Configuration();
            $config->setHost($this->ds_recipe_lib->ds_api_url);
            $config->addDefaultHeader("X-DocuSign-Authentication", $this->ds_recipe_lib->ds_auth_header);
            $this->apiClient = new DocuSign\eSign\ApiClient($config);

            return ['ok' => true];
        } else {
            return ['ok' => false, 'errMsg' => $result['errMsg']]; // early return
        }
    }
          
    
    ## FIN ##
}    
    
