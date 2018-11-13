<?php
declare(strict_types=1);
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../bootstrap.php";

use UCRM\Common\Config;
use UCRM\Plugins\Notifications\Settings;

use UCRM\REST\Endpoints\Ticket;
use UCRM\REST\Endpoints\User;
use UCRM\REST\Endpoints\Client;
use UCRM\REST\Endpoints\ClientContact;

/** @var Ticket $ticket */
$ticket = Ticket::getById(14);
$clientId = $ticket->getClientId();

/** @var Client|null $client Get the actual Client from the UCRM. */
$client = $clientId !== null ? Client::getById($clientId) : null;

/** @var ClientContact[] $contacts Get the actual Client Contacts from the UCRM. */
$contacts = $client !== null ? $client->getContacts()->elements() : null;

/** @var User $user */
$user = $ticket->getAssignedUserId() ? User::getById($ticket->getAssignedUserId()) : null;

// TODO: Add TicketGroup endpoint!
//$group = $ticket->getAssignedGroupId() ? TicketGroup::getById($ticket->getAssignedGroupId()) : null;

$latestComment = $ticket->getActivity()->where("type", "comment")->last();

// Build some view data to be passed to the Twig template.
$viewData =
    [
        //"translations" => $translations,
        "ticket" => $ticket,
        "user" => $user,
        "latestComment" => $latestComment,
        "client" => $client,
        "contacts" => $contacts,
        "url" => Settings::UCRM_PUBLIC_URL,
        "googleMapsApiKey" => Config::getGoogleApiKey() ?: "",
    ];

// Generate the HTML version of the email, then minify and reformat cleanly!
echo $twig->render("ticket/delete.text.twig", $viewData);