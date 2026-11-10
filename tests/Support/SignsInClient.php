<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait SignsInClient
{
    use ActsAsUser;

    protected static function signedInClient(?string $email = null): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(SecurityUser::fromUser(self::createUser($email)));

        return $client;
    }
}
