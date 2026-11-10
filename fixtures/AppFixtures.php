<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use App\Application\Lessons\EditLessonsPage\EditLessonsPage;
use App\Application\Lessons\EditLessonsPage\EditLessonsPageHandler;
use App\Application\Links\AddBioLink\AddBioLink;
use App\Application\Links\AddBioLink\AddBioLinkHandler;
use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\AddSocialLink\AddSocialLink;
use App\Application\Music\AddSocialLink\AddSocialLinkHandler;
use App\Application\Music\EditMusicText\EditMusicText;
use App\Application\Music\EditMusicText\EditMusicTextHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Application\Production\AddPortfolioItem\AddPortfolioItem;
use App\Application\Production\AddPortfolioItem\AddPortfolioItemHandler;
use App\Application\Production\AddProductionService\AddProductionService;
use App\Application\Production\AddProductionService\AddProductionServiceHandler;
use App\Application\Production\EditProductionPage\EditProductionPage;
use App\Application\Production\EditProductionPage\EditProductionPageHandler;
use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\StartCheckout\StartCheckout;
use App\Application\Sales\StartCheckout\StartCheckoutHandler;
use App\Application\Seo\EditPageSeo\EditPageSeo;
use App\Application\Seo\EditPageSeo\EditPageSeoHandler;
use App\Application\Settings\EditLegalIdentity\EditLegalIdentity;
use App\Application\Settings\EditLegalIdentity\EditLegalIdentityHandler;
use App\Application\Settings\InstallLegalDefaults\InstallLegalDefaults;
use App\Application\Settings\InstallLegalDefaults\InstallLegalDefaultsHandler;
use App\Application\Settings\SettingsQueries;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckout;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckoutHandler;
use App\Application\Settings\SwitchSections\SwitchSections;
use App\Application\Settings\SwitchSections\SwitchSectionsHandler;
use App\Domain\Contact\ContactTopic;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use App\Domain\Music\Track;
use App\Domain\Production\PortfolioItemDetails;
use App\Domain\Production\Pricing;
use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Production\ServiceKind;
use App\Domain\Sales\ConsentKind;
use App\Domain\Sales\ProductType;
use App\Domain\Seo\PageKey;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    private const array LINKS = [
        ['Listen on Spotify', 'Écouter sur Spotify', SocialNetwork::Spotify, 'https://open.spotify.com/artist/6RenEaL8VkR8isDsiHDGYw', true],
        ['Watch on YouTube', 'Regarder sur YouTube', SocialNetwork::Youtube, 'https://www.youtube.com/channel/UCxjrMeB6PABEF6Pg2ImBn2w', false],
        ['Follow on Instagram', 'Suivre sur Instagram', SocialNetwork::Instagram, 'https://instagram.com/injustmusic', false],
        ['Follow on TikTok', 'Suivre sur TikTok', SocialNetwork::Tiktok, 'https://tiktok.com/@injustmusic', false],
    ];

    public function __construct(
        private readonly AddReleaseHandler $addRelease,
        private readonly FeatureReleaseHandler $featureRelease,
        private readonly EditMusicTextHandler $editMusicText,
        private readonly AddSocialLinkHandler $addSocialLink,
        private readonly AddBioLinkHandler $addBioLink,
        private readonly EditPageSeoHandler $editPageSeo,
        private readonly AddLessonOfferHandler $addLessonOffer,
        private readonly EditLessonsPageHandler $editLessonsPage,
        private readonly AddProductionServiceHandler $addProductionService,
        private readonly EditProductionPageHandler $editProductionPage,
        private readonly AddPortfolioItemHandler $addPortfolioItem,
        private readonly SendContactMessageHandler $sendContactMessage,
        private readonly EditLegalIdentityHandler $editLegalIdentity,
        private readonly SwitchOnlineCheckoutHandler $switchOnlineCheckout,
        private readonly SwitchSectionsHandler $switchSections,
        private readonly InstallLegalDefaultsHandler $installLegalDefaults,
        private readonly StartCheckoutHandler $startCheckout,
        private readonly ConfirmPaymentHandler $confirmPayment,
        private readonly HttpClientInterface $httpClient,
        private readonly SettingsQueries $settings,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
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

        foreach (self::LINKS as [$en, $fr, $network, $url, $highlighted]) {
            ($this->addBioLink)(new AddBioLink(new LocalizedText($fr, $en), $url, $network, $highlighted, true));
        }

        ($this->editPageSeo)(new EditPageSeo(PageKey::Lessons, new LocalizedText('Cours de guitare rock et punk', 'Rock and punk guitar lessons'), new LocalizedText('Cours de guitare à domicile, chez le professeur ou en ligne.', 'Guitar lessons at your home, at the teacher or online.')));
        ($this->editPageSeo)(new EditPageSeo(PageKey::Production, new LocalizedText('Production et mix musical', 'Music production and mixing'), new LocalizedText('Enregistrement, mix et mastering pour groupes rock et punk.', 'Recording, mixing and mastering for rock and punk bands.')));

        $this->lessons();
        $this->production();
        $this->messages();

        if ('dev' === $this->environment) {
            $this->shop($manager);
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

    private function production(): void
    {
        $services = [
            ['Mixing', 'Mixage', ServiceKind::Mix, Pricing::Fixed, 12000, 7, 2],
            ['Mastering', 'Mastering', ServiceKind::Master, Pricing::Fixed, 4000, null, null],
            ['Full production', 'Production complète', ServiceKind::Production, Pricing::Quote, 30000, 30, 3],
            ['Arrangement', 'Arrangement', ServiceKind::Arrangement, Pricing::Quote, 0, null, null],
        ];
        foreach ($services as [$en, $fr, $kind, $pricing, $cents, $days, $revisions]) {
            ($this->addProductionService)(new AddProductionService(new ProductionServiceDetails(
                new LocalizedText($fr, $en),
                new LocalizedText('Rock, punk et hard rock, du maquette au master.', 'Rock, punk and hard rock, from demo to master.'),
                $kind,
                $pricing,
                new Price($cents),
                $days,
                $revisions,
            )));
        }

        ($this->editProductionPage)(new EditProductionPage(
            new LocalizedText('Mixage, mastering et production de vos morceaux rock, punk et hard rock.', 'Mixing, mastering and production for your rock, punk and hard rock tracks.'),
            new LocalizedText(
                "1. **Brief** Vous m'envoyez vos pistes et vos références.\n2. **Mix** Je vous envoie une première version.\n3. **Révisions** On ajuste ensemble.\n4. **Livraison** Fichiers finaux.",
                "1. **Brief** Send me your stems and references.\n2. **Mix** I send you a first version.\n3. **Revisions** We adjust together.\n4. **Delivery** Final files.",
            ),
            new LocalizedText(
                "### Quels fichiers fournir ?\n\nLes pistes séparées en WAV.\n\n### Combien de révisions ?\n\nSelon l'offre, de 2 à 3.",
                "### Which files should I send?\n\nSeparate WAV stems.\n\n### How many revisions?\n\nDepending on the offer, 2 to 3.",
            ),
        ));

        $works = [
            ['White Men are the Issue', [ServiceKind::Production, ServiceKind::Mix], 2026],
            ["Don't tell me who to be", [ServiceKind::Mix, ServiceKind::Master], 2026],
            ['We are fucked', [ServiceKind::Recording, ServiceKind::MixMaster], 2026],
        ];
        foreach ($works as [$title, $roles, $year]) {
            ($this->addPortfolioItem)(new AddPortfolioItem(new PortfolioItemDetails($title, 'Injust', $roles, $year, null)));
        }
    }

    private function messages(): void
    {
        ($this->sendContactMessage)(new SendContactMessage('Alex Example', 'alex@example.com', ContactTopic::General, 'Hello, when is the album out? Looking forward to it.', 'en'));
        ($this->sendContactMessage)(new SendContactMessage('Camille Exemple', 'camille@example.com', ContactTopic::Lessons, 'Bonjour, je cherche des cours de guitare le samedi matin.', 'fr'));
    }

    private function shop(ObjectManager $manager): void
    {
        ($this->editLegalIdentity)(new EditLegalIdentity(
            'Demo Artist',
            'EI',
            '123456782',
            "1 rue de l'Exemple\n75000 Paris",
            'demo@example.com',
            '+33 1 23 45 67 89',
            'Demo Artist',
            'Demo Mediator',
            'https://mediator.example.com',
            'Demo Host',
            "2 avenue de l'Hébergeur\n75000 Paris",
            '+33 1 98 76 54 32',
        ));
        ($this->installLegalDefaults)(new InstallLegalDefaults());
        ($this->switchSections)(new SwitchSections(true, true, false));
        ($this->switchOnlineCheckout)(new SwitchOnlineCheckout(true));

        $this->paidOrder(ProductType::Lesson, $this->first($manager, LessonOffer::class, [])->id()->toBase32(), 'buyer-one@example.com', 'fr');
        $this->paidOrder(ProductType::Production, $this->first($manager, ProductionService::class, ['pricing' => Pricing::Fixed])->id()->toBase32(), 'buyer-two@example.com', 'en');
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $criteria
     *
     * @return T
     */
    private function first(ObjectManager $manager, string $class, array $criteria): object
    {
        return $manager->getRepository($class)->findOneBy($criteria, ['position' => 'ASC']) ?? throw new \LogicException($class);
    }

    private function paidOrder(ProductType $type, string $productId, string $email, string $locale): void
    {
        $started = ($this->startCheckout)(new StartCheckout($type, $productId, $locale, $email, array_map(static fn (ConsentKind $kind) => $kind->value, $type->requiredConsents()), $this->settings->legalDocument($type->cgvKind())->version));
        $session = basename((string) parse_url($started->url, \PHP_URL_PATH));
        $this->httpClient->request('POST', 'https://api.stripe.com/v1/test_helpers/checkout/sessions/'.$session.'/pay')->getContent();
        ($this->confirmPayment)(new ConfirmPayment($session));
    }
}
