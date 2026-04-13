<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\StatDefinition;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Main organizational Doctrine fixture handling core or baseline system bootstrapping for StatDefinitionFixtures.
 */
class StatDefinitionFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['stats'];
    }

    public function load(ObjectManager $manager): void
    {
        $defs = [
            ['code' => 'points', 'name' => 'Points', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'assists', 'name' => 'Assists', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'rebounds', 'name' => 'Rebounds', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'blocks', 'name' => 'Blocks', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'steals', 'name' => 'Steals', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'turnovers', 'name' => 'Turnovers', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'three_point_pct', 'name' => '3PT %', 'sport' => 'basketball', 'unit' => 'percentage'],
            ['code' => 'field_goal_pct', 'name' => 'FG %', 'sport' => 'basketball', 'unit' => 'percentage'],
            ['code' => 'free_throw_pct', 'name' => 'FT %', 'sport' => 'basketball', 'unit' => 'percentage'],
            ['code' => 'games_played', 'name' => 'Games Played', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'games_started', 'name' => 'Games Started', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'minutes_played', 'name' => 'Minutes Played', 'sport' => 'basketball', 'unit' => 'count'],
            ['code' => 'plus_minus', 'name' => '+/-', 'sport' => 'basketball', 'unit' => 'count'],
        ];

        foreach ($defs as $data) {
            $def = $manager->getRepository(StatDefinition::class)->findOneBy([
                'code' => $data['code'],
                'sport' => $data['sport'],
            ]);

            if (!$def) {
                $def = new StatDefinition();
            }

            $def->setCode($data['code'])
                ->setName($data['name'])
                ->setSport($data['sport'])
                ->setUnit($data['unit']);

            $manager->persist($def);
        }

        $manager->flush();
    }
}
