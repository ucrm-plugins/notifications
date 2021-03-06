<?php
declare(strict_types=1);
require_once __DIR__."/vendor/autoload.php";

use MVQN\Localization\Translator;
use MVQN\Localization\Exceptions\TranslatorException;
use MVQN\REST\RestClient;

use UCRM\Common\Plugin;
use UCRM\Common\Config;
use UCRM\Plugins\Notifications\Settings;
use UCRM\Twig\NotificationsExtension;

/**
 * bootstrap.php
 *
 * A common configuration and initialization file.
 *
 * @author Ryan Spaeth <rspaeth@mvqn.net>
 */

// IF there is a /.env file, THEN load it!
if(file_exists(__DIR__."/../.env"))
    (new \Dotenv\Dotenv(__DIR__."/../"))->load();

// Initialize the Plugin libraries using this directory as the plugin root!
Plugin::initialize(__DIR__);

// Regenerate the Settings class, in case anything has changed in the manifest.json file.
Plugin::createSettings("UCRM\\Plugins\\Notifications");

// Generate the REST API URL.
$restUrl = rtrim(getenv("UCRM_REST_URL_DEV") ?: Settings::UCRM_LOCAL_URL, "/")."/api/v1.0";

//echo "URL: $restUrl\n\n";
//echo Config::getSmtpHost()."\n";
//echo Config::getSmtpPort()."\n";
//echo Config::getSmtpAuthentication()."\n"; // <blank>
//echo Config::getSmtpUsername()."\n";
//echo (Config::getSmtpVerifySslCertificate() ? "T" : "F")."\n";
//echo Config::getSmtpEncryption()."\n";
//echo Config::getSmtpPassword()."\n\n";


// Configure the REST Client...
RestClient::setBaseUrl($restUrl); //Settings::UCRM_PUBLIC_URL . "api/v1.0");
RestClient::setHeaders([
    "Content-Type: application/json",
    "X-Auth-App-Key: " . Settings::PLUGIN_APP_KEY
]);

//$countries = \UCRM\REST\Endpoints\Country::get();
//echo $countries;
//echo "\n";


// Configure the language...
//$translations = include_once __DIR__."/translations/" . (Config::getLanguage() ?: "en_US") . ".php";

// Set the dictionary directory and "default" locale.
try
{
    Translator::setDictionaryDirectory(__DIR__ . "/translations/");
    Translator::setCurrentLocale(str_replace("_", "-", Config::getLanguage()) ?: "en-US", true);
    //Translator::setCurrentLocale("es-ES", true);
}
catch (TranslatorException $e)
{
    http_response_code(500);
    //die("The locale '$locale' is not currently supported!");
    die($e->getMessage());
}

// Configure the Twig template environment and pass it along in the global namespace as this is used often.
$twig = new Twig_Environment(new Twig_Loader_Filesystem(__DIR__ . "/twig/"),
[
    //"cache" => __DIR__."/twig/.cache/", // This will speed things up a bit, but will break the editable templates!
    "debug" => true,
]);

/** @var Twig_Extension_Core $core */
$core = $twig->getExtension("Twig_Extension_Core");
$core->setTimezone(Config::getTimezone());

$twig->addExtension(new Twig_Extension_Debug());
$twig->addExtension(new NotificationsExtension());

// Add the "translate" filter to the Twig environment, otherwise the |translate filter will not function!
$twig->addFilter(Translator::getTwigFilterTranslate());


