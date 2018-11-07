<?php
declare(strict_types=1);

namespace MVQN\UCRM\Plugins\Extensions;


final class SubjectExtension extends \Twig_Extension
{
    private $subject = "";

    public function getName() {
        return 'subjectExtension';
    }

    public function getFunctions() {
        return [
            new \Twig_Function("setSubject", [$this, "setSubject"])
        ];
    }

    public function setSubject($value)
    {
        $this->subject = $value;
    }

    public function getSubject()
    {
        return $this->subject;
    }
}