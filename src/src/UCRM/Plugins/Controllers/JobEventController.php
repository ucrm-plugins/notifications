<?php
declare(strict_types=1);

namespace UCRM\Plugins\Controllers;

use MVQN\HTML\HTML;
use MVQN\Localization\Translator;

use UCRM\Plugins\Notifications\Settings;
use UCRM\REST\Endpoints\User;
use UCRM\Twig\NotificationsExtension;


//use UCRM\Common\Plugin;
use UCRM\Common\Config;

use UCRM\REST\Endpoints\Client;
use UCRM\REST\Endpoints\ClientContact;
use UCRM\REST\Endpoints\Job;
use UCRM\REST\Endpoints\JobAttachment;
//use UCRM\REST\Endpoints\JobComment;
use UCRM\REST\Endpoints\JobTask;
use UCRM\REST\Endpoints\Ticket;


/**
 * Class JobEventController
 *
 * @package MVQN\UCRM\Plugins\Controllers
 * @author Ryan Spaeth <rspaeth@mvqn.net>
 */
class JobEventController extends EventController
{
    /**
     * @param string $action
     * @param int $jobId
     * @return EmailActionResult[]
     * @throws \Exception
     */
    public function action(string $action, int $jobId): array
    {
        $results = [];
        $results["0"] = new EmailActionResult();
        $results["1"] = new EmailActionResult();

        // =============================================================================================================
        // ENTITIES
        // =============================================================================================================

        /** @var Job $job */
        $job = Job::getById($jobId);
        $results["0"]->debug[] = "Job\n" . json_encode($job, JSON_PRETTY_PRINT) . "\n";

        /** @var JobAttachment[] $attachments */
        $attachments = $job->getAttachments()->elements();
        $results["0"]->debug[] = "Job\n" . json_encode($attachments, JSON_PRETTY_PRINT) . "\n";

        /** @var JobTask[] $tasks */
        $tasks = $job->getTasks()->elements();
        $results["0"]->debug[] = "Job\n" . json_encode($tasks, JSON_PRETTY_PRINT) . "\n";

        /** @var User|null $user */
        $user = User::getById($job->getAssignedUserId());
        $results["0"]->debug[] = "User\n" . json_encode($user, JSON_PRETTY_PRINT) . "\n";

        /** @var Client|null $client */
        $client = Client::getById($job->getClientId());
        $results["0"]->debug[] = "Client\n" . json_encode($client, JSON_PRETTY_PRINT) . "\n";

        /** @var ClientContact[] $contacts */
        $contacts = $client !== null ? $client->getContacts()->elements() : null;
        $results["0"]->debug[] = "Contacts\n" . json_encode($contacts, JSON_PRETTY_PRINT) . "\n";

        /** @var Ticket[] $ticket */
        $tickets = $job->getTickets()->elements();
        $results["0"]->debug[] = "Tickets\n" . json_encode($tickets, JSON_PRETTY_PRINT) . "\n";

        $results["1"] = clone $results["0"];

        // =============================================================================================================
        // RECIPIENTS
        // =============================================================================================================

        array_map("trim", explode(",", $this->replaceVariables(
            Settings::getTicketJobRecipients(),
            [
                "JOB_ASSIGNED_USER" => $user !== null ? $user->getEmail() : ""
            ],
            $results["0"]->recipients, // Static Recipients
            $results["1"]->recipients  // Dynamic Recipients
        )));

        $results["0"]->recipients = array_filter($results["0"]->recipients);
        $results["1"]->recipients = array_filter($results["1"]->recipients);

        $results["0"]->debug[] = "Recipients\n".json_encode($results["0"]->recipients, JSON_PRETTY_PRINT)."\n";
        $results["1"]->debug[] = "Recipients\n".json_encode($results["1"]->recipients, JSON_PRETTY_PRINT)."\n";

        // =============================================================================================================
        // DATA
        // =============================================================================================================

        // Build some view data to be passed to the Twig template.
        $viewData =
            [
                "personalized" => false,
                "job" => $job,
                "attachments" => $attachments,
                "tasks" => $tasks,
                "user" => $user,
                "client" => $client,
                "contacts" => $contacts,
                "tickets" => $tickets,
                "url" => Settings::UCRM_PUBLIC_URL,
                "googleMapsApiKey" => Config::getGoogleApiKey() ?: "",
            ];

        // =============================================================================================================
        // HTML
        // =============================================================================================================

        $results["0"]->html = HTML::tidyHTML(HTML::minify($this->twig->render(
            $this->getTemplate("job", $action, "html"), $viewData)));
        $viewData["personalized"] = true;
        $results["1"]->html = HTML::tidyHTML(HTML::minify($this->twig->render(
            $this->getTemplate("job", $action, "html"), $viewData)));
        $viewData["personalized"] = false;

        // =============================================================================================================
        // TEXT
        // =============================================================================================================

        // Generate the TEXT version of the email, to be used as a fall back!
        $results["0"]->text = $this->twig->render($this->getTemplate("job", $action, "text"), $viewData);
        $viewData["personalized"] = true;
        $results["1"]->text = $this->twig->render($this->getTemplate("job", $action, "text"), $viewData);
        $viewData["personalized"] = false;

        // =============================================================================================================
        // SUBJECT
        // =============================================================================================================

        // Set the default subject line for this notification.
        switch ($action)
        {
            case "add":
                $results["0"]->subject = "Job Added";
                $results["1"]->subject = "Job Added";
                break;
            case "delete":
                $results["0"]->subject = "Job Delete";
                $results["1"]->subject = "Job Delete";
                break;
            case "edit":
                $results["0"]->subject = "Job Edited";
                $results["1"]->subject = "Job Edited";
                break;
            default:
                $results["0"]->subject = "";
                $results["1"]->subject = "";
                break;
        }

        /** @var NotificationsExtension $notificationsExtension */
        $notificationsExtension = $this->twig->getExtension(NotificationsExtension::class);

        $subject = $notificationsExtension->getSubject() !== "" ?
            $notificationsExtension->getSubject() : $results["0"]->subject;

        $subjectPersonalized = $notificationsExtension->getSubjectPersonalized() !== "" ?
            $notificationsExtension->getSubjectPersonalized() : $results["1"]->subject;

        $results["0"]->subject = Translator::learn($subject);
        $results["1"]->subject = Translator::learn($subjectPersonalized);

        $results["0"]->debug[] = "Subject\n".json_encode($results["0"]->subject, JSON_PRETTY_PRINT)."\n";
        $results["1"]->debug[] = "Subject\n".json_encode($results["1"]->subject, JSON_PRETTY_PRINT)."\n";

        // =============================================================================================================
        // RESULT
        // =============================================================================================================

        // Return the ActionResults!
        return $results;
    }

}