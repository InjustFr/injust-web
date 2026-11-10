<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Beats\BeatsQueries;
use App\Application\Beats\BeatView;
use App\Domain\Shared\Price;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class BeatsStructuredData
{
    public function __construct(
        private BeatsQueries $beats,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param list<BeatView> $beats
     *
     * @return list<array<string, mixed>>
     */
    public function recordings(array $beats): array
    {
        $page = $this->urls->generate('beats', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $band = ['@id' => $this->urls->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL).'#band'];

        return array_map(fn (BeatView $beat): array => $this->recording($beat, $page.'#beat-'.$beat->slug, $band), $beats);
    }

    /**
     * @param array{'@id': string} $band
     *
     * @return array<string, mixed>
     */
    private function recording(BeatView $beat, string $url, array $band): array
    {
        $node = ['@type' => 'MusicRecording', '@id' => $url, 'name' => $beat->title, 'url' => $url, 'byArtist' => $band];
        if (null !== $beat->cover) {
            $node['image'] = $this->urls->generate('media_image', ['file' => $beat->cover.'.webp'], UrlGeneratorInterface::ABSOLUTE_URL);
        }
        $price = $this->beats->fromPrice($beat->id);
        if (null !== $price) {
            $node['offers'] = ['@type' => 'Offer', 'price' => \sprintf('%d.%02d', intdiv($price->cents, 100), $price->cents % 100), 'priceCurrency' => Price::CURRENCY, 'url' => $url];
        }

        return $node;
    }
}
