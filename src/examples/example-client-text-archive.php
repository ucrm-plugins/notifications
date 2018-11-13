<?php
declare(strict_types=1);
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../bootstrap.php";

use UCRM\Common\Config;
use UCRM\Plugins\Notifications\Settings;

use UCRM\REST\Endpoints\Client;
use UCRM\REST\Endpoints\ClientContact;

/** @var Client $client Get the actual Client from the UCRM. */
$client = Client::getById(1);

/** @var ClientContact[] $contacts Get the actual Client Contacts from the UCRM. */
$contacts = $client->getContacts()->elements();

// Build some view data to be passed to the Twig template.
$viewData =
    [
        //"translations" => $translations,
        "client" => $client,
        "contacts" => $contacts,
        "url" => Settings::UCRM_PUBLIC_URL,
        "googleMapsApiKey" => Config::getGoogleApiKey() ?: "",
    ];

// Generate the HTML version of the email, then minify and reformat cleanly!
echo $twig->render("client/archive.text.twig", $viewData);
