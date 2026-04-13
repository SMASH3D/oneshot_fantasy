<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\Tournament;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Doctrine automation fixture populating dummy/developer Tournament resources during dev environment boots.
 */
final class TournamentFixture extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $tournament = new Tournament();
        $tournament->setName('NBA Playoffs Mock');
        $tournament->setSlug('nba-playoffs-mock-fixture');
        $tournament->setSportKey('basketball');
        $tournament->setTimezone('America/New_York');
        $tournament->setStatus('in_progress');
        $tournament->setMetadata([
            'format' => 'elimination',
            'fixture' => true,
        ]);

        $manager->persist($tournament);
        $this->addReference(DevReferences::TOURNAMENT, $tournament);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return [DevReferences::GROUP];
    }
}
