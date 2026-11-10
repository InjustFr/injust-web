<?php

declare(strict_types=1);

namespace App\Infrastructure\Youtube;

use App\Application\Music\VideoThumbnails;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\ResizedImage;
use App\Domain\Music\YoutubeId;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpVideoThumbnails implements VideoThumbnails
{
    private const string URL = 'https://i.ytimg.com/vi/%s/%s.jpg';
    private const array SIZES = ['maxresdefault', 'hqdefault'];
    private const int MAX_SIDE = 1280;
    private const float TIMEOUT = 5.0;

    public function __construct(
        private HttpClientInterface $http,
        private ImageResizer $resizer,
    ) {
    }

    public function fetch(YoutubeId $id): ?ResizedImage
    {
        try {
            foreach (self::SIZES as $size) {
                $response = $this->http->request('GET', \sprintf(self::URL, $id->value, $size), ['timeout' => self::TIMEOUT]);
                if (200 === $response->getStatusCode()) {
                    return $this->resize($response->getContent());
                }
            }

            return null;
        } catch (TransportExceptionInterface) {
            return null;
        }
    }

    private function resize(string $bytes): ?ResizedImage
    {
        $filesystem = new Filesystem();
        $file = $filesystem->tempnam(sys_get_temp_dir(), 'thumbnail');
        $filesystem->dumpFile($file, $bytes);
        try {
            return $this->resizer->resize($file, self::MAX_SIDE);
        } catch (UnsupportedImage) {
            return null;
        } finally {
            $filesystem->remove($file);
        }
    }
}
