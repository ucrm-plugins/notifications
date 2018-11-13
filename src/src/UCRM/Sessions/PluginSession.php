<?php
declare(strict_types=1);

namespace UCRM\Sessions;



use MVQN\REST\RestClient;
use UCRM\REST\Endpoints\Client;
use UCRM\REST\Endpoints\User;

class PluginSession
{
    public static function getCurrentlyAuthenticated(): ?array
    {
        if(!isset($_COOKIE["PHPSESSID"]))
            return null;

        $sessionId = $_COOKIE["PHPSESSID"];
        $cookie = "PHPSESSID=" . preg_replace('~[^a-zA-Z0-9]~', '', $_COOKIE['PHPSESSID']);

        RestClient::pushHeader("Cookie: ".$cookie);

        $restUrl = RestClient::getBaseUrl();
        $tempUrl = (getenv("UCRM_REST_URL_DEV") ?: "http://localhost");

        RestClient::setBaseUrl($tempUrl);

        $current = RestClient::get("/current-user");

        RestClient::setBaseUrl($restUrl);

        RestClient::popHeader();

        return $current;
    }

    public static function isUserAuthenticated(): bool
    {
        return self::getAuthenticatedUser() !== null;
    }

    public static function isClientAuthenticated(): bool
    {
        return self::getAuthenticatedClient() !== null;
    }



    public static function getAuthenticatedUser(): ?User
    {
        $authenticated = self::getCurrentlyAuthenticated();

        if($authenticated === null || $authenticated["isClient"] || $authenticated["userId"] === null)
            return null;

        /** @var User $user */
        $user = User::getById($authenticated["userId"]);

        return $user;
    }

    public static function getAuthenticatedClient(): ?Client
    {
        $authenticated = self::getCurrentlyAuthenticated();

        if($authenticated === null || !$authenticated["isClient"] || $authenticated["clientId"] === null)
            return null;

        /** @var Client $client */
        $client = Client::getById($authenticated["clientId"]);

        return $client;
    }

    public static function isAuthenticatedAdmin(): bool
    {
        $authenticated = self::getCurrentlyAuthenticated();

        return $authenticated["userGroup"] === "Admin Group";
    }



    public static function loginForm(): string
    {
        return file_get_contents(__DIR__ . "/forms/login.html");
    }








}