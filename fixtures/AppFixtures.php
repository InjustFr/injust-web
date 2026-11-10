<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Lessons\EditLessonsPage\EditLessonsPage;
use App\Application\Lessons\EditLessonsPage\EditLessonsPageHandler;
use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\AddSocialLink\AddSocialLink;
use App\Application\Music\AddSocialLink\AddSocialLinkHandler;
use App\Application\Music\EditMusicText\EditMusicText;
use App\Application\Music\EditMusicText\EditMusicTextHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Domain\Contact\ContactTopic;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use App\Domain\Music\Track;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
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
        private readonly AddLessonOfferHandler $addLessonOffer,
        private readonly EditLessonsPageHandler $editLessonsPage,
        private readonly SendContactMessageHandler $sendContactMessage,
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

        $this->lessons();
        $this->messages();
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

    private function lessons(): void
    {
        $offers = [
            ['Single lesson at my place', 'Leçon à domicile chez le professeur', OfferKind::Single, 1, LessonFormat::AtTeacher, 60, 4000],
            ['Single online lesson', 'Leçon en ligne', OfferKind::Single, 1, LessonFormat::Online, 60, 3500],
            ['Pack of 5 lessons at your home', 'Forfait 5 leçons à votre domicile', OfferKind::Pack, 5, LessonFormat::AtHome, 60, 22500],
        ];
        foreach ($offers as [$en, $fr, $kind, $sessions, $format, $minutes, $cents]) {
            ($this->addLessonOffer)(new AddLessonOffer(new LessonOfferDetails(
                new LocalizedText($fr, $en),
                new LocalizedText('Guitare rock, punk et hard rock, tous niveaux.', 'Rock, punk and hard rock guitar, all levels.'),
                $kind,
                $sessions,
                $format,
                $minutes,
                new Price($cents),
            )));
        }

        ($this->editLessonsPage)(new EditLessonsPage(
            new LocalizedText('Apprenez la guitare par les morceaux que vous aimez : riffs, accords de puissance, rythmique et solos.', 'Learn guitar through the songs you love: riffs, power chords, rhythm and solos.'),
            new LocalizedText(
                "### Faut-il avoir sa propre guitare ?\n\nOui, une guitare électrique ou acoustique pour commencer.\n\n### Quel niveau faut-il avoir ?\n\nAucun : on part de là où vous en êtes.",
                "### Do I need my own guitar?\n\nYes, an electric or acoustic guitar to begin with.\n\n### What level do I need?\n\nNone: we start from where you are.",
            ),
        ));
    }

    private function messages(): void
    {
        ($this->sendContactMessage)(new SendContactMessage('Alex Example', 'alex@example.com', ContactTopic::General, 'Hello, when is the album out? Looking forward to it.', 'en'));
        ($this->sendContactMessage)(new SendContactMessage('Camille Exemple', 'camille@example.com', ContactTopic::Lessons, 'Bonjour, je cherche des cours de guitare le samedi matin.', 'fr'));
    }
}
