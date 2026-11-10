<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use App\Application\Settings\SettingsQueries;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SecurityHeaders
{
    public const string NOINDEX = 'noindex, nofollow';

    private const array ALWAYS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Frame-Options' => 'DENY',
    ];

    public function __construct(
        private SettingsQueries $settings,
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire(env: 'ACCOUNTS_URL')]
        private string $accountsUrl,
    ) {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;
        $headers->add(self::ALWAYS);
        if ($this->hiddenFromSearch($request)) {
            $headers->set('X-Robots-Tag', self::NOINDEX);
        }
        if ('dev' !== $this->environment) {
            $headers->set('Content-Security-Policy', $this->policy());
        }
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private function hiddenFromSearch(Request $request): bool
    {
        return 1 === preg_match('#^/(admin|api)(/|$)#', $request->getPathInfo()) || !$this->settings->current()->searchIndexing;
    }

    private function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'sha256-60LHlRjW/B3CtzIoE/Lf1/NEDvko9efWMFaGVhHu/cs='",
            "img-src 'self' data:",
            "media-src 'self'",
            "font-src 'self'",
            "connect-src 'self'",
            'frame-src https://www.youtube-nocookie.com https://open.spotify.com',
            "object-src 'none'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'".$this->accountsOrigin(),
        ]);
    }

    private function accountsOrigin(): string
    {
        $url = parse_url($this->accountsUrl);
        if (!isset($url['scheme'], $url['host'])) {
            return '';
        }

        return \sprintf(' %s://%s%s', $url['scheme'], $url['host'], isset($url['port']) ? ':'.$url['port'] : '');
    }
}
