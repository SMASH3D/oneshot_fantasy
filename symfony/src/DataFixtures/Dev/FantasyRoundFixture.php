<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\FantasyRound;
use App\Entity\League;
use App\Entity\Round;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Doctrine automation fixture populating dummy/developer FantasyRound resources during dev environment boots.
 */
final class FantasyRoundFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var League $league */
        $league = $this->getReference(DevReferences::LEAGUE, League::class);

        $pairs = [
            [DevReferences::FANTASY_ROUND_QF, DevReferences::ROUND_QF, 0, 'Quarterfinals'],
            [DevReferences::FANTASY_ROUND_SF, DevReferences::ROUND_SF, 1, 'Semifinals'],
            [DevReferences::FANTASY_ROUND_FINALS, DevReferences::ROUND_FINALS, 2, 'Finals'],
        ];

        foreach ($pairs as [$fantasyRef, $roundRef, $order, $name]) {
            /** @var Round $tournamentRound */
            $tournamentRound = $this->getReference($roundRef, Round::class);

            $fr = new FantasyRound();
            $fr->setLeague($league);
            $fr->setTournamentRound($tournamentRound);
            $fr->setOrderIndex($order);
            $fr->setName($name);
            $manager->persist($fr);
            $this->addReference($fantasyRef, $fr);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            LeagueFixture::class,
            TournamentRoundFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return [DevReferences::GROUP];
    }
}
