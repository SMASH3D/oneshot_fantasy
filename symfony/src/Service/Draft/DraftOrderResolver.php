<?php

declare(strict_types=1);

namespace App\Service\Draft;

/**
 * Pure snake / linear draft order math (no I/O — easy to unit test).
 */
final class DraftOrderResolver
{
    /**
     * @param non-empty-list<string> $membershipIds UUID strings in base pick order (round 1 forward)
     */
    public function membershipIdAtPickIndex(array $membershipIds, int $pickIndex, bool $snake): string
    {
        $n = \count($membershipIds);
        $round = intdiv($pickIndex, $n);
        $pos = $pickIndex % $n;
        if ($snake && $round % 2 === 1) {
            $pos = $n - 1 - $pos;
        }

        return $membershipIds[$pos];
    }
}
