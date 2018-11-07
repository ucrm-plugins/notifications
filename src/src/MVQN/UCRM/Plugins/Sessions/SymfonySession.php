<?php
declare(strict_types=1);

namespace MVQN\UCRM\Plugins\Sessions;

final class SymfonySession
{

    public static function isAnyUserLoggedIn(): bool
    {
        if (session_status() == PHP_SESSION_NONE)
            session_start();

        if (!isset($_SESSION['_sf2_attributes']))
            return false;

        if (!isset($_SESSION['_sf2_attributes']['_security_main']))
            return false;

        $path = "/usr/src/ucrm/vendor/autoload.php";

        // Will not exist in development!
        if(!file_exists($path))
            throw new \Exception("Could not find the required file at '$path'!");

        return true;
    }



    public static function getCurrentUserRoles(): ?array
    {
        if(!self::isAnyUserLoggedIn())
            return null;

        $path = "/usr/src/ucrm/vendor/autoload.php";

        // Will not exist in development!
        if(!file_exists($path))
            return null;

        require_once $path;

        /**
         * @var \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken
         */
        $security = unserialize($_SESSION['_sf2_attributes']['_security_main']);

        $roles = $security->getRoles();

        $rolesArray = [];

        /** @var \Symfony\Component\Security\Core\Role\Role[] $roles */
        foreach($roles as $role)
            $rolesArray[] = $role->getRole();

        //$user = $security->getUser();
        return $rolesArray;
    }



}