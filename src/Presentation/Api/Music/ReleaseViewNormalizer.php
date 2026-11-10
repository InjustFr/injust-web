<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\ReleaseView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ReleaseViewNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        \assert($data instanceof ReleaseView);
        $normalized = $this->normalizer->normalize(get_object_vars($data), $format, $context);
        \assert(\is_array($normalized));

        return array_replace($normalized, ['clicks' => (object) $data->clicks, 'listenUrl' => $this->urls->generate('listen', ['slug' => $data->slug, '_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL)]);
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ReleaseView;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ReleaseView::class => true];
    }
}
