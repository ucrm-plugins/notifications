<?php
declare(strict_types=1);
namespace MVQN\Twig;

use MVQN\Localization\Translator;
use MVQN\Twig\TokenParsers\SwitchTokenParser;
use MVQN\UCRM\Plugins\Plugin;

/**
 * Class Extension
 *
 * @package MVQN\Twig
 * @author Ryan Spaeth <rspaeth@mvqn.net>
 * @final
 */
final class NotificationsExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    private $subject = "";

    public function setSubject(string $subject)
    {
        $this->subject = $subject;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    private $subjectPersonalized = "";

    public function setSubjectPersonalized(string $subject)
    {
        $this->subjectPersonalized = $subject;
    }

    public function getSubjectPersonalized()
    {
        return $this->subjectPersonalized;
    }


    public function getName(): string
    {
        return 'mvqn';
    }

    public function getTokenParsers(): array
    {
        return [
            new SwitchTokenParser(),
        ];
    }

    public function getFilters(): array
    {

        return [
            //new \Twig_SimpleFilter('without', [$this, 'withoutFilter']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            //new \Twig_SimpleFunction('getFootHtml', [$this, 'getFootHtml']),
            new \Twig_SimpleFunction("setSubject", [$this, "setSubject"]),
            new \Twig_SimpleFunction("setSubjectPersonalized", [$this, "setSubjectPersonalized"]),
            new \Twig_SimpleFunction("locale", [$this, "locale"]),
            new \Twig_SimpleFunction("loadTemplates", [$this, "loadTemplates"]),
            new \Twig_SimpleFunction("saveTemplates", [$this, "saveTemplates"]),
        ];
    }

    public function locale(): string
    {
        return Translator::getCurrentLocale();
    }

    public function loadTemplates (string $entity, string $action): array
    {
        $templates =
            [
                "htmlCustom" => "",
                "htmlNormal" => "",
                "textCustom" => "",
                "textNormal" => "",
            ];

        $customHtmlPath = Plugin::getDataPath()."/twig/$entity.$action.html.twig";
        $normalHtmlPath = Plugin::getRootPath()."/twig/$entity.$action.html.twig";

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

        $customTextPath = Plugin::getDataPath()."/twig/$entity.$action.text.twig";
        $normalTextPath = Plugin::getRootPath()."/twig/$entity.$action.text.twig";

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

    public function saveTemplates(string $path, string $data): bool
    {
        $customPath = Plugin::getDataPath()."/twig/$path";
        $normalPath = Plugin::getRootPath()."/twig/$path";

        if(!file_exists(dirname($customPath)))
            mkdir(dirname($customPath), 0755, true);

        file_put_contents($customPath, $data);

        return true;
    }



    public function getGlobals(): array
    {
        return [
            //'now' => new \DateTime(),
        ];
    }


}