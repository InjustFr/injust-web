<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\AddSocialLink\AddSocialLink;
use App\Application\Music\AddSocialLink\AddSocialLinkHandler;
use App\Application\Music\EditMusicText\EditMusicText;
use App\Application\Music\EditMusicText\EditMusicTextHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use App\Domain\Music\Track;
use App\Domain\Shared\LocalizedText;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Ulid;

final class AppFixtures extends Fixture
{
    private const array ALBUM = [
        ['White Men are the Issue', 156],
        ["Don't tell me who to be", 225],
        ['She needs help', 273],
        ['Never good enough', 161],
        ['This is discrimination', 204],
        ['Little Soldiers', 234],
        ['Is it normal to be me', 257],
        ['Mr Greedy', 278],
        ["Don't know how to live my life", 182],
    ];

    private const array HOOKS = [
        "Don't tell me who to be",
        "We're past the point of no return",
        'How fucked is the system',
        'This is discrimination',
        'March against their power',
        'To bring down the ivory tower',
        'Come join the fight',
        'The time of oppression and tyrants is finally done',
    ];

    private const array SOCIAL = [
        [SocialNetwork::Instagram, 'https://instagram.com/injustmusic'],
        [SocialNetwork::Tiktok, 'https://tiktok.com/@injustmusic'],
        [SocialNetwork::Youtube, 'https://www.youtube.com/channel/UCxjrMeB6PABEF6Pg2ImBn2w'],
        [SocialNetwork::Spotify, 'https://open.spotify.com/artist/6RenEaL8VkR8isDsiHDGYw'],
        [SocialNetwork::Linktree, 'https://linktr.ee/injustmusic'],
    ];

    public function __construct(
        private readonly AddReleaseHandler $addRelease,
        private readonly FeatureReleaseHandler $featureRelease,
        private readonly EditMusicTextHandler $editMusicText,
        private readonly AddSocialLinkHandler $addSocialLink,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->release('White Men are the Issue', ReleaseType::Single, '2026-09-25', [self::ALBUM[0]]);
        $this->release("Don't tell me who to be", ReleaseType::Single, '2026-10-16', [self::ALBUM[1]]);
        $this->release('This is discrimination', ReleaseType::Single, '2026-11-06', [self::ALBUM[4]], 'https://ffm.to/this-is-discrimination');
        $album = $this->release('We are fucked', ReleaseType::Album, '2026-12-04', self::ALBUM);
        ($this->featureRelease)(new FeatureRelease(Ulid::fromString($album), true));

        ($this->editMusicText)(new EditMusicText(new LocalizedText('', 'Fuck the system'), new LocalizedText(), new LocalizedText(), self::HOOKS));

        foreach (self::SOCIAL as [$network, $url]) {
            ($this->addSocialLink)(new AddSocialLink($network, $url, null));
        }
    }

    /** @param list<array{string, int}> $tracks */
    private function release(string $title, ReleaseType $type, string $date, array $tracks, ?string $smartLink = null): string
    {
        $view = ($this->addRelease)(new AddRelease(new ReleaseDetails(
            $title,
            null,
            $type,
            new \DateTimeImmutable($date),
            new LocalizedText(),
            array_map(static fn (array $track, int $index) => new Track($index + 1, $track[0], $track[1]), $tracks, array_keys($tracks)),
            [],
            $smartLink,
            null,
        )));

        return $view->id;
    }
}
