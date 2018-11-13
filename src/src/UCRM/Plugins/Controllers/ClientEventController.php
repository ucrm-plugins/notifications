<?php
declare(strict_types=1);

namespace UCRM\Plugins\Controllers;

use MVQN\HTML\HTML;
use MVQN\Localization\Translator;

use UCRM\Plugins\Notifications\Settings;
use UCRM\Twig\NotificationsExtension;


//use UCRM\Common\Plugin;
use UCRM\Common\Config;

use UCRM\REST\Endpoints\Client;
use UCRM\REST\Endpoints\ClientContact;


/**
 * Class ClientEventController
 *
 * @package MVQN\UCRM\Plugins\Controllers
 * @author Ryan Spaeth <rspaeth@mvqn.net>
 */
class ClientEventController extends EventController
{
    /**
     * @param string $action
     * @param int $clientId
     * @return EmailActionResult[]
     * @throws \Exception
     */
    public function action(string $action, int $clientId): array
    {
        $results = [];
        $results["0"] = new EmailActionResult();
        //$results["1"] = new EmailActionResult();

        // =============================================================================================================
        // ENTITIES
        // =============================================================================================================

        /** @var Client|null $client */
        $client = Client::getById($clientId);
        $results["0"]->debug[] = "Client\n" . json_encode($client, JSON_PRETTY_PRINT) . "\n";

        /** @var ClientContact[] $contacts */
        $contacts = $client !== null ? $client->getContacts()->elements() : null;
        $results["0"]->debug[] = "Contacts\n" . json_encode($contacts, JSON_PRETTY_PRINT) . "\n";

        //$results["1"] = clone $results["0"];

        // =============================================================================================================
        // RECIPIENTS
        // =============================================================================================================

        array_map("trim", explode(",", $this->replaceVariables(
            Settings::getClientRecipients(),
            [
                //"TICKET_ASSIGNED_USER" => $user->getEmail()
            ],
            $results["0"]->recipients // Static Recipients
            //$results["1"]->recipients  // Dynamic Recipients
        )));

        $results["0"]->recipients = array_filter($results["0"]->recipients);
        //$results["1"]->recipients = array_filter($results["1"]->recipients);

        $results["0"]->debug[] = "Recipients\n".json_encode($results["0"]->recipients, JSON_PRETTY_PRINT)."\n";
        //$results["1"]->debug[] = "Recipients\n".json_encode($results["1"]->recipients, JSON_PRETTY_PRINT)."\n";

        // =============================================================================================================
        // DATA
        // =============================================================================================================

        // Build some view data to be passed to the Twig template.
        $viewData =
            [
                "client" => $client,
                "contacts" => $contacts,
                "url" => Settings::UCRM_PUBLIC_URL,
                "googleMapsApiKey" => Config::getGoogleApiKey() ?: "",
            ];

        // =============================================================================================================
        // HTML
        // =============================================================================================================

        /*
        $customTemplateFile = Plugin::getDataPath()."/twig/client/$action.html.twig";
        $copiedTemplateFile = Plugin::getRootPath()."/twig/client/$action.html-custom.twig";

        if(file_exists($customTemplateFile)) // Always copy to make certain we have the latest?
        {
            copy($customTemplateFile, Plugin::getRootPath()."/twig/client/$action.html-custom.twig");
            $useCustom = true;
        }
        else
        {
            $useCustom = false;
        }

        // Generate the HTML version of the email, then minify and reformat cleanly!
        //$results["0"]->html = HTML::tidyHTML(HTML::minify($this->twig->render("client/$action.html.twig", $viewData)));
        $results["0"]->html = HTML::tidyHTML(HTML::minify($this->twig->render(
            "client/$action.html".($useCustom ? "-custom" : "").".twig", $viewData)));
        */
        $results["0"]->html = HTML::tidyHTML(HTML::minify($this->twig->render(
            $this->getTemplate("client", $action, "html"), $viewData)));

        // =============================================================================================================
        // TEXT
        // =============================================================================================================



        // Generate the TEXT version of the email, to be used as a fall back!
        //$results["0"]->text = $this->twig->render("client/$action.text.twig", $viewData);
        $results["0"]->text = $this->twig->render($this->getTemplate("client", $action, "text"), $viewData);

        // =============================================================================================================
        // SUBJECT
        // =============================================================================================================




        // Set the appropriate subject line for this notification.
        switch ($action)
        {
            case "add":
                $results["0"]->subject = "Client Added";
                break;
            case "archive":
                $results["0"]->subject = "Client Archived";
                break;
            case "delete":
                $results["0"]->subject = "Client Delete";
                break;
            case "edit":
                $results["0"]->subject = "Client Edited";
                break;
            case "invite":
                $results["0"]->subject = "Client Invited";
                break;
            default:
                $results["0"]->subject = "";
                break;
        }

        //$results["0"]->subject = Translator::learn($results["0"]->subject);

        /** @var NotificationsExtension $notificationsExtension */
        $notificationsExtension = $this->twig->getExtension(NotificationsExtension::class);
        $subject = $notificationsExtension->getSubject() !== "" ?
            $notificationsExtension->getSubject() : $results["0"]->subject;

        $results["0"]->subject = Translator::learn($subject);

        $results["0"]->debug[] = "Subject\n".json_encode($results["0"]->subject, JSON_PRETTY_PRINT)."\n";

        // =============================================================================================================
        // RESULT
        // =============================================================================================================

        // Return the ActionResults!
        return $results;
    }

}