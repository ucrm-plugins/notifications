<?php
declare(strict_types=1);
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../bootstrap.php";

use UCRM\Common\Config;
use UCRM\Plugins\Notifications\Settings;

use UCRM\REST\Endpoints\Client;
use UCRM\REST\Endpoints\ClientContact;
use UCRM\REST\Endpoints\Job;
use UCRM\REST\Endpoints\JobAttachment;
//use UCRM\REST\Endpoints\JobComment;
use UCRM\REST\Endpoints\JobTask;
use UCRM\REST\Endpoints\Ticket;
use UCRM\REST\Endpoints\User;

/** @var Job $job Get the actual Job from the UCRM. */
$job = Job::get()->last();

/** @var JobAttachment[] $attachments */
$attachments = $job->getAttachments()->elements();

/** @var JobTask[] $tasks */
$tasks = $job->getTasks()->elements();

/** @var User $user */
$user = $job->getAssignedUser();

/** @var Client $client Get the actual Client from the UCRM. */
$client = $job->getClient();

/** @var ClientContact[] $contacts Get the actual Client Contacts from the UCRM. */
$contacts = $client->getContacts()->elements();

/** @var Ticket[] $tickets */
$tickets = $job->getTickets();

// Build some view data to be passed to the Twig template.
$viewData =
    [
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

// Generate the HTML version of the email, then minify and reformat cleanly!
echo $twig->render("job/edit.html.twig", $viewData);
