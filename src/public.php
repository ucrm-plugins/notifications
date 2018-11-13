<?php
declare(strict_types=1);
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/bootstrap.php";

use UCRM\REST\Endpoints\WebhookEvent;

use UCRM\Common\Log;
use UCRM\Common\Config;
use UCRM\Plugins\Notifications\Settings;

use UCRM\Plugins\Controllers\DeleteEventController;
use UCRM\Plugins\Controllers\ClientEventController;
use UCRM\Plugins\Controllers\JobEventController;
use UCRM\Plugins\Controllers\TicketEventController;
use UCRM\Plugins\Controllers\TicketCommentEventController;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use UCRM\Sessions\PluginSession;

/**
 * public.php
 *
 * Handles webhook events of any selected entity changes and then notifies the appropriate people as configured in the
 * plugin settings.
 *
 * When no payload is provided, like when visiting the Plugin's Public URL, this file will display the Template Editor.
 *
 * Use an immediately invoked function here to prevent pollution of the global namespace.
 *
 * @author Ryan Spaeth <rspaeth@mvqn.net>
 */
(function() use ($twig)
{
    // Parse the input received from Webhook events.
    $data = file_get_contents("php://input");

    // Parse the JSON payload into an array for further handling.
    $dataArray = json_decode($data, true);

    // =================================================================================================================
    // TEMPLATE EDITOR: SAVE
    // =================================================================================================================

    // IF a template is selected and a "save" is requested...
    if(isset($_GET["save"]) && isset($_GET["template"]))
    {
        // THEN, attempt to save the template...
        try
        {
            // Split the requested template string into entity and action.
            $template = explode(".", $_GET["template"]);
            $entity = $template[0];
            $action = $template[1];

            // ---------------------------------------------------------------------------------------------------------
            // HTML TEMPLATE
            // ---------------------------------------------------------------------------------------------------------

            // Fix the line-endings from the ACE editor.
            $htmlContent = str_replace("\r\n", "\n", $_POST["html"]);

            // Build the custom and default paths for the HTML template.
            $customHtmlPath = __DIR__ . "/data/twig/$entity/$action.html.twig";
            $normalHtmlPath = __DIR__ . "/twig/$entity/$action.html.twig";

            // IF a custom HTML template directory does not exist, THEN create it!
            if (!file_exists(dirname($customHtmlPath)))
                mkdir(dirname($customHtmlPath), 0755, true);

            // IF the revised template matches the default AND a custom template exists...
            if (file_get_contents($normalHtmlPath) === $htmlContent && file_exists($customHtmlPath))
                // THEN remove the custom template, so that the default is used!
                unlink($customHtmlPath);
            else
                // OTHERWISE save the custom template, overwriting as necessary!
                file_put_contents($customHtmlPath, $htmlContent);

            // ---------------------------------------------------------------------------------------------------------
            // TEXT TEMPLATE
            // ---------------------------------------------------------------------------------------------------------

            // Fix the line-endings from the ACE editor.
            $textContent = str_replace("\r\n", "\n", $_POST["text"]);

            // Build the custom and default paths for the TEXT template.
            $customTextPath = __DIR__ . "/data/twig/$entity/$action.text.twig";
            $normalTextPath = __DIR__ . "/twig/$entity/$action.text.twig";

            // IF a custom TEXT template directory does not exist, THEN create it!
            if (!file_exists(dirname($customTextPath)))
                mkdir(dirname($customTextPath), 0755, true);

            // IF the revised template matches the default AND a custom template exists...
            if (file_get_contents($normalTextPath) === $textContent && file_exists($customTextPath))
                // THEN remove the custom template, so that the default is used!
                unlink($customTextPath);
            else
                // OTHERWISE save the custom template, overwriting as necessary!
                file_put_contents($customTextPath, $textContent);

            // ---------------------------------------------------------------------------------------------------------

            // Build the return path and then redirect to the editor, notifying the user of saving success!
            $query = "public.php?template=" . $_GET["template"] . "&saved=success";
            header("Location: $query");
        }
        catch (\Exception $e)
        {
            // Build the return path and then redirect to the editor, notifying the user of saving failure!
            $query = "public.php?template=" . $_GET["template"] . "&saved=failure";
            header("Location: $query");
        }
    }

    // =================================================================================================================
    // TEMPLATE EDITOR
    // =================================================================================================================

    // IF the array/payload is empty...
    if (!$dataArray)
    {
        // THEN display the Template Editor system!

        // -------------------------------------------------------------------------------------------------------------
        // AUTHENTICATION
        // -------------------------------------------------------------------------------------------------------------

        // IF a Session is not already started, THEN start one!
        if (session_status() == PHP_SESSION_NONE)
            session_start();

        // Display an error if no user is authenticated!
        if(!PluginSession::getCurrentlyAuthenticated())
            Log::http("No User is currently Authenticated!", 401);

        // Display an error if the authenticated user is NOT an Admin!
        if(!PluginSession::isAuthenticatedAdmin())
            Log::http("Currently Authenticated User is not an Admin!", 401);

        // ---------------------------------------------------------------------------------------------------------
        // TEMPLATE EDITOR: TEMPLATE SELECTOR
        // ---------------------------------------------------------------------------------------------------------

        function loadTemplates (?string $entity, ?string $action): array
        {
            // Create an empty set of template data...
            $templates =
                [
                    "htmlCustom" => "",
                    "htmlNormal" => "",
                    "textCustom" => "",
                    "textNormal" => "",
                ];

            // IF no entity is provided OR no action is provided, THEN return the empty template data!
            if($entity === null || $action === null)
                return $templates;

            // ---------------------------------------------------------------------------------------------------------
            // HTML TEMPLATE
            // ---------------------------------------------------------------------------------------------------------

            // Build the custom and default paths for the HTML template.
            $customHtmlPath = __DIR__."/data/twig/$entity/$action.html.twig";
            $normalHtmlPath = __DIR__."/twig/$entity/$action.html.twig";

            // IF a custom HTML template exists, THEN add it to the template data!
            if(file_exists($customHtmlPath))
            {
                $realHtmlPath = realpath($customHtmlPath);
                $htmlTwig = $realHtmlPath ? file_get_contents($realHtmlPath) : "";
                $templates["htmlCustom"] = $htmlTwig;
            }

            // IF the default HTML template exists (which should be ALWAYS), THEN add it to the template data!
            if(file_exists($normalHtmlPath))
            {
                $realHtmlPath = realpath($normalHtmlPath);
                $htmlTwig = $realHtmlPath ? file_get_contents($realHtmlPath) : "";
                $templates["htmlNormal"] = $htmlTwig;
            }
            else
            {
                // OTHERWISE, the default HTML template file is missing, return a HTTP 500 - Internal Server Error!
                Log::http("A required template file '$entity.$action.html.twig' could not be found at ".
                    "'$normalHtmlPath'!", 500);
            }

            // ---------------------------------------------------------------------------------------------------------
            // TEXT TEMPLATE
            // ---------------------------------------------------------------------------------------------------------

            // Build the custom and default paths for the TEXT template.
            $customTextPath = __DIR__."/data/twig/$entity/$action.text.twig";
            $normalTextPath = __DIR__."/twig/$entity/$action.text.twig";

            // IF a custom TEXT template exists, THEN add it to the template data!
            if(file_exists($customTextPath))
            {
                $realTextPath = realpath($customTextPath);
                $textTwig = $realTextPath ? file_get_contents($realTextPath) : "";
                $templates["textCustom"] = $textTwig;
            }

            // IF the default HTML template exists (which should be ALWAYS), THEN add it to the template data!
            if(file_exists($normalTextPath))
            {
                $realTextPath = realpath($normalTextPath);
                $textTwig = $realTextPath ? file_get_contents($realTextPath) : "";
                $templates["textNormal"] = $textTwig;
            }
            else
            {
                // OTHERWISE, the default HTML template file is missing, return a HTTP 500 - Internal Server Error!
                Log::http("A required template file '$entity.$action.text.twig' could not be found at ".
                    "'$normalTextPath'!", 500);
            }

            // ---------------------------------------------------------------------------------------------------------

            // Return the template data for the Editor to display!
            return $templates;
        };

        // ---------------------------------------------------------------------------------------------------------

        // Get the requested template from the query string and then separate it into it's entity and action!
        $template = isset($_GET["template"]) ? $_GET["template"] : null;
        $entity = $template !== null ? explode(".", $template)[0] : null;
        $action = $template !== null ? explode(".", $template)[1] : null;

        // Get all of the template data.
        $templates = loadTemplates($entity, $action);

        // ---------------------------------------------------------------------------------------------------------
        // TEMPLATE EDITOR: RENDERING
        // ---------------------------------------------------------------------------------------------------------

        // Render the Twig template for the Template Editor, passing the template data to the view!
        echo $twig->render("editor.html.twig",
            [
                "entity" => $entity,
                "action" => $action,
                "saved" => isset($_GET["saved"]) ? $_GET["saved"] : null,
                "htmlCustom" => $templates["htmlCustom"] !== "" ?
                    $templates["htmlCustom"] :
                    $templates["htmlNormal"], "htmlNormal" => $templates["htmlNormal"],
                "textCustom" => $templates["textCustom"] !== "" ?
                    $templates["textCustom"] :
                    $templates["textNormal"], "textNormal" => $templates["textNormal"],
            ]
        );

        // Log an access to the Template Editor and return HTTP 200 - OK
        Log::info("Template editor accessed by an Admin User.");
        http_response_code(200);
        die();
    }

    // =================================================================================================================
    // PAYLOAD: WEBHOOK EVENTS
    // =================================================================================================================

    // Attempt to get the UUID from the payload.
    $uuid = array_key_exists("uuid", $dataArray) ? $dataArray["uuid"] : "";

    // IF the data does not include a valid UUID, THEN return a "Bad Request" response and skip this event!
    if (!$uuid)
        Log::http("The Webhook Event payload did not contain a valid UUID field!\n$data", 400);

    // OTHERWISE, attempt to get the Webhook Event from the UCRM system for validation...
    $event = WebhookEvent::getByUuid($uuid);

    // IF the Webhook Event exists in the UCRM...
    if ($event->getUuid() === $uuid)
    {
        // THEN we should be good to continue, as this is our verification of a valid event!

        // -------------------------------------------------------------------------------------------------------------
        // WEBHOOK REQUEST
        // -------------------------------------------------------------------------------------------------------------

        // Get the individual values from the payload.
        $changeType = $dataArray["changeType"]; // edit
        $entityType = $dataArray["entity"]; // client
        $entityId = $dataArray["entityId"]; // 1
        $eventName = $dataArray["eventName"]; // client.edit

        // Initialize a results array for request handling.
        $results = [];

        // Configure an universal Controller for all entity.delete events.
        $deleteController = new DeleteEventController($twig);

        // Handle the different Webhook Event types...
        switch ($entityType)
        {
            // ---------------------------------------------------------------------------------------------------------
            // CLIENT EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "client":
                // Instantiate a new EventController and determine the correct type of action to take...
                $controller =               new ClientEventController($twig);

                switch ($changeType)
                {
                    case "insert":/* add */ $results = $controller->action("add", $entityId);                   break;
                    case "archive":         $results = $controller->action("archive", $entityId);               break;
                    case "delete":          $results = $deleteController->action("client", $entityId);          break;
                    case "edit":            $results = $controller->action("edit", $entityId);                  break;
                    case "invitation":      $results = $controller->action("invite", $entityId);                break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // INVOICE EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "invoice":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new InvoiceEventController($twig);

                switch ($changeType)
                {
                    case "insert":/* add */ Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "add_draft":       Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "delete":          $results = $deleteController->action("invoice", $entityId);         break;
                    case "edit":            Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "near_due":        Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "overdue":         Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // JOB EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "job":
                // Instantiate a new EventController and determine the correct type of action to take...
                $jobController =             new JobEventController($twig);

                switch ($changeType)
                {
                    case "insert":/* add */ $results = $jobController->action("add", $entityId);                break;
                    case "delete":          $results = $deleteController->action("job", $entityId);             break;
                    case "edit":            $results = $jobController->action("edit", $entityId);               break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // PAYMENT EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "payment":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new PaymentEventController($twig);

                switch ($changeType)
                {
                    case "insert":/* add */ Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "delete":          $results = $deleteController->action("payment", $entityId);         break;
                    case "edit":            Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "unmatch":         Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // QUOTE EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "quote":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new QuoteEventController($twig);

                switch ($changeType)
                {
                    case "insert":/* add */ Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "delete":          $results = $deleteController->action("quote", $entityId);           break;
                    case "edit":            Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // SERVICE EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "service":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new ServiceEventController($twig);

                switch ($changeType)
                {
                    case "activate":        Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "insert":/* add */ Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "archive":         Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "edit":            Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "end":             Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "postpone":        Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "suspend":         Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "suspend_cancel":  Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // SUBSCRIPTION EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "subscription":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new ServiceEventController($twig);

                switch ($changeType)
                {
                    case "delete":          Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    case "edit":            Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // TICKET COMMENT EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "ticketComment":
                // Instantiate a new EventController and determine the correct type of action to take...
                $controller =               new TicketCommentEventController($twig);

                switch ($changeType)
                {
                    case "comment":         $results = $controller->action("comment", $entityId);               break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // TICKET EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "ticket":
                // Instantiate a new EventController and determine the correct type of action to take...
                $controller =               new TicketEventController($twig);

                switch ($changeType)
                {
                    case "insert":/* add */ $results = $controller->action("add", $entityId);                   break;
                    case "delete":          $results = $deleteController->action("ticket", $entityId);          break;
                    case "edit":            $results = $controller->action("edit", $entityId);                  break;
                    case "status_change":   $results = $controller->action("status_change", $entityId);         break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // USER EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "user":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new UserEventController($twig);

                switch ($changeType)
                {
                    case "reset_password":  Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // WEBHOOK EVENT
            // ---------------------------------------------------------------------------------------------------------
            case "webhook":
                // Instantiate a new EventController and determine the correct type of action to take...
                //$controller =             new WebhookEventController($twig);

                switch ($changeType)
                {
                    case "test":            Log::http("The Event: '$eventName' is not supported!", 501);        break;
                    default:                Log::http("The Event: '$eventName' is not supported!", 501);        break;
                }   break;

            // ---------------------------------------------------------------------------------------------------------
            // OTHER EVENTS...
            // ---------------------------------------------------------------------------------------------------------
            default:                        Log::http("The Entity: '$entityType' is not supported!", 501);      break;
        }

        // -------------------------------------------------------------------------------------------------------------
        // DEBUGGING
        // -------------------------------------------------------------------------------------------------------------

        // Check to see if "Verbose Debugging" is enabled.
        $verboseDebug = Settings::getVerboseDebug();

        echo "\n";

        // DEBUG: Echo any debug messages from the EventController to the Webhook Request Log...
        if($verboseDebug)
            print_r($results);

        // -------------------------------------------------------------------------------------------------------------
        // EMAIL SENDING
        // -------------------------------------------------------------------------------------------------------------

        // Attempt to send the email notification...
        try
        {
            // Initialize an instance of the mailer!
            $mail = new PHPMailer(true);

            // DEBUG: Setup the mailer for debugging, if "Verbose Debugging" is enabled.
            if($verboseDebug)
            {
                $mail->Debugoutput = "echo";
                $mail->SMTPDebug = 2;
            }

            // Configure the SMTP Settings...
            $mail->isSMTP();
            $mail->Host = Config::getSmtpHost();
            $mail->SMTPAuth = true;
            $mail->Username = Config::getSmtpUsername();
            $mail->Password = Config::getSmtpPassword();
            if (Config::getSmtpEncryption() !== "")
                $mail->SMTPSecure = Config::getSmtpEncryption();
            $mail->Port = Config::getSmtpPort();
            $mail->setFrom(Config::getSmtpSenderEmail());
            $mail->addReplyTo(Config::getSmtpSenderEmail());

            // Setup for HTML emails, if desired.
            $mail->isHTML(Settings::getSmtpUseHTML());

            // Compose a separate email message for each email configuration (currently Normal and Personalized)...
            foreach($results as $result)
            {
                // If no recipients match this email configuration, THEN skip this one!
                if($result->recipients === [])
                    continue;

                // Clear any previous email addresses.
                $mail->clearAddresses();

                // Loop through each email recipient and add it to the current email configuration...
                foreach ($result->recipients as $email)
                    $mail->addAddress($email);

                // Add the email Subject Line.
                $mail->Subject = $result->subject;

                // IF "Use HTML?" is set, THEN add both the HTML and TEXT email bodies, OTHERWISE add the TEXT body!
                if(Settings::getSmtpUseHTML())
                {
                    $mail->Body = $result->html;
                    $mail->AltBody = $result->text;
                }
                else
                {
                    $mail->Body = $result->text;
                }

                // Finally, attempt to send the message!
                $mail->send();
            }

            // Append an extra newline when "Verbose Debugging" is enabled to properly format the log!
            if($verboseDebug)
                echo "\n";

            // IF we've made it this far, the message should have sent successfully, notify the system.
            Log::http("A valid Webhook Event was received and a notification message sent successfully!", 200);
        }
        catch (Exception $e)
        {
            // Append an extra newline when "Verbose Debugging" is enabled to properly format the log!
            if($verboseDebug)
                echo "\n";

            // OTHERWISE, something went wrong, so notify the system of failure.
            Log::http("A valid Webhook Event was received and a notification message sent successfully!\n{$mail->ErrorInfo}", 400);
        }
    }

    // SHOULD NEVER REACH HERE!

})();
