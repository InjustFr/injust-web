<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Beats;

use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait CallsBeatsApi
{
    private const array BEAT = ['title' => 'Riot Gear', 'bpm' => 140, 'key' => 'a-minor', 'tags' => ['punk']];
    private const string MP3 = "ID3\x04\x00\x00\x00\x00\x00\x00mp3";

    private static function addBeat(KernelBrowser $client): string
    {
        $client->jsonRequest('POST', '/api/admin/beats', self::BEAT);
        self::assertResponseStatusCodeSame(201);

        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'id');
    }

    /** @return array<mixed> */
    private static function uploadAudio(KernelBrowser $client, string $url, ?string $bytes, string $name = 'Riot Gear.mp3'): array
    {
        $files = [];
        if (null !== $bytes) {
            $path = tempnam(sys_get_temp_dir(), 'beat');
            self::assertIsString($path);
            file_put_contents($path, $bytes);
            $files['file'] = new UploadedFile($path, $name, null, test: true);
        }
        $client->request('POST', $url, files: $files, server: ['HTTP_ACCEPT' => 'application/json']);

        return Json::decode((string) $client->getResponse()->getContent());
    }

    private static function storedFiles(): int
    {
        $share = (string) self::$kernel?->getShareDir();

        return \count(array_filter([...glob($share.'/private/*') ?: [], ...glob($share.'/audio/*') ?: []], is_file(...)));
    }

    private static function audioIsServed(KernelBrowser $client, string $id): bool
    {
        $client->request('GET', '/media/audio/'.$id.'.mp3');

        return $client->getResponse()->isSuccessful();
    }
}
