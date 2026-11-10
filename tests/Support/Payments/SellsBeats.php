<?php

declare(strict_types=1);

namespace App\Tests\Support\Payments;

use App\Application\Shared\PrivateFileStorage;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierDetails;
use App\Domain\Beats\MusicalKey;
use App\Domain\Sales\Order;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use Doctrine\ORM\EntityManagerInterface;

trait SellsBeats
{
    /** @param list<Deliverable> $deliverables */
    private static function tier(string $name, int $cents, array $deliverables, bool $exclusive = false, int $position = 0): LicenceTier
    {
        $tier = LicenceTier::add(new LicenceTierDetails(new LocalizedText($name.' FR', $name), new Price($cents), new LocalizedText(), $deliverables, $exclusive, $exclusive ? null : 10000, null, 1), $position);
        self::persist($tier);

        return $tier;
    }

    /** @param list<Deliverable> $files */
    private static function beatOnSale(array $files = [Deliverable::Mp3, Deliverable::Wav], bool $visible = true, string $slug = 'riot'): Beat
    {
        $beat = Beat::add(new BeatDetails(ucfirst($slug), $slug, 140, MusicalKey::AMinor, ['punk'], $visible), 0);
        $beat->usePreview(new BeatFile('01JCPREV1EW000000000000000', 'preview.mp3', 1, 'audio/mpeg'));
        foreach ($files as $deliverable) {
            $path = tempnam(sys_get_temp_dir(), 'beat');
            self::assertIsString($path);
            file_put_contents($path, self::fileBytes($deliverable));
            $stored = self::getContainer()->get(PrivateFileStorage::class)->store($path, 'Riot.'.$deliverable->format()->value);
            $beat->useFile($deliverable, new BeatFile($stored->id, $stored->originalName, $stored->size, $stored->mimeType));
        }
        self::persist($beat);

        return $beat;
    }

    private static function fileBytes(Deliverable $deliverable): string
    {
        return $deliverable->value.' bytes of the beat';
    }

    private static function persist(object ...$entities): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($entities as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
    }

    private static function storeOrder(Order $order): Order
    {
        self::persist($order);

        return $order;
    }
}
