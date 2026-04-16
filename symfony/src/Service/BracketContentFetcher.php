<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches the ESPN playoff bracket page and generates CMS content for a tournament.
 *
 * ESPN bracket pages are JavaScript-rendered, so full bracket HTML cannot be extracted
 * server-side. The service generates a clean HTML block with a direct ESPN link instead,
 * which is reliable and works without a headless browser.
 *
 * Usage:
 *   - Symfony command: app:tournament:fetch-bracket (via console)
 *   - EasyAdmin action: "Fetch ESPN Bracket" button on the Tournament list/detail
 */
final class BracketContentFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Generate CMS content for the tournament's bracket page and persist it.
     *
     * Returns a human-readable result message.
     */
    public function fetchAndStore(Tournament $tournament): string
    {
        $meta        = $tournament->getMetadata();
        $bracketUrl  = $meta['bracket_url'] ?? null;

        if (!$bracketUrl) {
            // Auto-derive URL from slug (e.g. nba-playoffs-2025 → season 2025)
            $bracketUrl = $this->deriveEspnUrl($tournament->getSlug() ?? '');
            if ($bracketUrl) {
                $meta['bracket_url'] = $bracketUrl;
                $tournament->setMetadata($meta);
            }
        }

        if (!$bracketUrl) {
            return 'No bracket_url found in tournament metadata and could not derive one from the slug.';
        }

        // Try fetching the page to confirm it's reachable
        $fetchedOk = false;
        try {
            $response  = $this->httpClient->request('GET', $bracketUrl, [
                'timeout'         => 8,
                'headers'         => ['User-Agent' => 'Mozilla/5.0 (compatible; OneshotFantasy/1.0)'],
            ]);
            $fetchedOk = ($response->getStatusCode() === 200);
        } catch (\Throwable) {
            // Proceed with link block even if fetch fails
        }

        $year = $this->extractYear($bracketUrl);

        $cms = $this->buildCmsBlock($bracketUrl, $year, $fetchedOk);
        $tournament->setCmsContent($cms);
        $this->entityManager->flush();

        return sprintf(
            'CMS content updated with ESPN bracket link for %s (URL reachable: %s).',
            $tournament->getName(),
            $fetchedOk ? 'yes' : 'no / blocked by ESPN — link still embedded',
        );
    }

    // -------------------------------------------------------------------------

    private function deriveEspnUrl(string $slug): ?string
    {
        // Matches: nba-playoffs-2025, nba-playoffs-2026, …
        if (preg_match('/nba-playoffs-(\d{4})/', $slug, $m)) {
            return 'https://www.espn.com/nba/playoff-bracket/_/season/' . $m[1];
        }

        return null;
    }

    private function extractYear(string $url): ?string
    {
        if (preg_match('/season\/(\d{4})/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function buildCmsBlock(string $url, ?string $year, bool $reachable): string
    {
        $label = $year ? "NBA {$year} Playoff Bracket" : 'NBA Playoff Bracket';

        return <<<HTML
            <div class="d-flex align-items-center gap-3 flex-wrap py-2">
                <a href="{$url}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="ui-btn ui-btn--primary">
                    <i class="bi bi-diagram-3 me-2"></i>{$label} on ESPN
                    <i class="bi bi-box-arrow-up-right ms-2 small"></i>
                </a>
                <span class="small ui-muted">Interactive bracket — opens on ESPN</span>
            </div>
            HTML;
    }
}
