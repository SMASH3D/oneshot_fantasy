<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

/**
 * Stable reference names shared across dev fixtures.
 */
final class DevReferences
{
    public const GROUP = 'dev';

    public const TOURNAMENT = 'dev.tournament.main';

    public const LEAGUE = 'dev.league.main';

    public const ROUND_QF = 'dev.round.qf';

    public const ROUND_SF = 'dev.round.sf';

    public const ROUND_FINALS = 'dev.round.finals';

    public const FANTASY_ROUND_QF = 'dev.fantasy_round.qf';

    public const FANTASY_ROUND_SF = 'dev.fantasy_round.sf';

    public const FANTASY_ROUND_FINALS = 'dev.fantasy_round.finals';

    public const DRAFT_SESSION = 'dev.draft_session.main';

    public static function user(int $n): string
    {
        return 'dev.user.'.$n;
    }

    public static function membership(int $n): string
    {
        return 'dev.membership.'.$n;
    }

    public static function participant(int $n): string
    {
        return sprintf('dev.participant.%02d', $n);
    }

    public static function lineup(int $membershipSlot): string
    {
        return 'dev.lineup.m'.$membershipSlot.'.qf';
    }

    private function __construct()
    {
    }
}
