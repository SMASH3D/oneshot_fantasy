<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\Round;
use App\Entity\Tournament;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class TournamentRoundFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Tournament $tournament */
        $tournament = $this->getReference(DevReferences::TOURNAMENT, Tournament::class);

        $rounds = [
            [DevReferences::ROUND_QF, 0, 'Quarterfinals', 'qf'],
            [DevReferences::ROUND_SF, 1, 'Semifinals', 'sf'],
            [DevReferences::ROUND_FINALS, 2, 'Finals', 'finals'],
        ];

        foreach ($rounds as [$ref, $order, $name, $key]) {
            $round = new Round();
            $round->setTournament($tournament);
            $round->setOrderIndex($order);
            $round->setName($name);
            $round->setCanonicalKey($key);
            $manager->persist($round);
            $this->addReference($ref, $round);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TournamentFixture::class];
    }

    public static function getGroups(): array
    {
        return [DevReferences::GROUP];
    }
}
