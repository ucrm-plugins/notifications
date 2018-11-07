<?php
declare(strict_types=1);
require_once __DIR__."/vendor/autoload.php";

use MVQN\Localization\Translator;
use MVQN\REST\RestClient;

use MVQN\UCRM\Plugins\Plugin;
use MVQN\UCRM\Plugins\Config;
use MVQN\UCRM\Plugins\Settings;

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
Plugin::createSettings();

// Generate the REST API URL.
$restUrl = (getenv("UCRM_REST_URL_DEV") ?: "http://localhost")."/api/v1.0";

// Configure the REST Client...
RestClient::setBaseUrl($restUrl); //Settings::UCRM_PUBLIC_URL . "api/v1.0");
RestClient::setHeaders([
    "Content-Type: application/json",
    "X-Auth-App-Key: " . Settings::PLUGIN_APP_KEY
]);

// Configure the language...
//$translations = include_once __DIR__."/translations/" . (Config::getLanguage() ?: "en_US") . ".php";

// Set the dictionary directory and "default" locale.
try
{
    Translator::setDictionaryDirectory(__DIR__ . "/translations/");
    Translator::setCurrentLocale(str_replace("_", "-", Config::getLanguage()) ?: "en-US", true);
    //Translator::setCurrentLocale("es-ES", true);
}
catch (\MVQN\Localization\Exceptions\TranslatorException $e)
{
    //$locale = Config::getLanguage();

    http_response_code(500);
    //die("The locale '$locale' is not currently supported!");
    die($e->getMessage());
}

// Configure the Twig template environment and pass it along in the global namespace as this is used often.
$twig = new Twig_Environment(new Twig_Loader_Filesystem(__DIR__ . "/twig/"),
[
    //"cache" => __DIR__."/twig/.cache/", // Can optionally be enabled after development is complete!
]);

$localeFunction = new Twig_Function("locale",
    function()
    {
        return Translator::getCurrentLocale();
    }
);

$loadTemplatesFunction = new Twig_Function("loadTemplates",
    function(string $entity, string $action): array
    {
        $templates =
            [
                "htmlCustom" => "",
                "htmlNormal" => "",
                "textCustom" => "",
                "textNormal" => "",
            ];

        $customHtmlPath = __DIR__."/data/twig/$entity.$action.html.twig";
        $normalHtmlPath = __DIR__."/twig/$entity.$action.html.twig";

        if(file_exists($customHtmlPath))
        {
            $realHtmlPath = realpath($customHtmlPath);
            $htmlTwig = $realHtmlPath ? file_get_contents($realHtmlPath) : "";
            $templates["htmlCustom"] = json_encode($htmlTwig, JSON_UNESCAPED_SLASHES);
        }
        else if(file_exists($normalHtmlPath))
        {
            $realHtmlPath = realpath($normalHtmlPath);
            $htmlTwig = $realHtmlPath ? file_get_contents($realHtmlPath) : "";
            $templates["htmlNormal"] = json_encode($htmlTwig, JSON_UNESCAPED_SLASHES);
        }
        else
        {
            die("A required template file '$entity.$action.html.twig' could not be found at either '$customHtmlPath' ".
                "or '$normalHtmlPath'!");
        }

        $customTextPath = __DIR__."/data/twig/$entity.$action.text.twig";
        $normalTextPath = __DIR__."/twig/$entity.$action.text.twig";

        if(file_exists($customTextPath))
        {
            $realTextPath = realpath($customTextPath);
            $textTwig = $realTextPath ? file_get_contents($realTextPath) : "";
            $templates["textCustom"] = json_encode($textTwig, JSON_UNESCAPED_SLASHES);
        }
        else if(file_exists($normalTextPath))
        {
            $realTextPath = realpath($normalTextPath);
            $textTwig = $realTextPath ? file_get_contents($realTextPath) : "";
            $templates["textNormal"] = json_encode($textTwig, JSON_UNESCAPED_SLASHES);
        }
        else
        {
            die("A required template file '$entity.$action.text.twig' could not be found at either '$customTextPath' ".
                "or '$normalTextPath'!");
        }

        return $templates;
    }
);

$saveTemplatesFunction = new Twig_Function("saveTemplates",
    function(string $path, string $data): bool
    {
        $customPath = __DIR__."/data/twig/$path";
        $normalPath = __DIR__."/twig/$path";

        if(!file_exists(dirname($customPath)))
            mkdir(dirname($customPath), 0755, true);

        file_put_contents($customPath, $data);

        return true;
    }
);



$twig->addFunction($localeFunction);
$twig->addFunction($loadTemplatesFunction);
$twig->addFunction($saveTemplatesFunction);

// Add the "translate" filter to the Twig environment, otherwise the |translate filter will not function!
$twig->addFilter(Translator::getTwigFilterTranslate());



