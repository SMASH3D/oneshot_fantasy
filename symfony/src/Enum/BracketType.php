<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Represents different types of tournament bracket formats.
 *
 * Each case corresponds to a specific type of bracket used in competitions.
 */
enum BracketType: string
{
    case NbaPostseason = 'nba_postseason';
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';
    case GroupStage = 'group_stage';

    public function label(): string
    {
        return match($this) {
            self::NbaPostseason => 'NBA Postseason tournament',
            self::SingleElimination => 'Single-Elimination Bracket',
            self::DoubleElimination => 'Double-Elimination Bracket',
            self::GroupStage => 'Group Stage Bracket',
        };
    }

    /** @return array<string, string> */
    public static function choices(): array
    {
        return array_combine(
            array_map(fn(self $case) => $case->label(), self::cases()),
            array_map(fn(self $case) => $case->value, self::cases()),
        );
    }
}
