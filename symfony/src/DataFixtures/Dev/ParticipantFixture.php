<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\Participant;
use App\Entity\Tournament;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Doctrine automation fixture populating dummy/developer Participant resources during dev environment boots.
 */
final class ParticipantFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Tournament $tournament */
        $tournament = $this->getReference(DevReferences::TOURNAMENT, Tournament::class);

        for ($i = 1; $i <= 16; ++$i) {
            $participant = new Participant();
            $participant->setExternalId(\sprintf('fixture-player-%02d', $i));
            $participant->setName(\sprintf('Player %d', $i));
            $participant->setSport($tournament->getSportKey());
            $participant->setType('team');
            $participant->setTeamName('Fixture Team');
            $participant->setPosition(null);
            $participant->setMetadata(['fixture' => true]);

            $manager->persist($participant);
            $this->addReference(DevReferences::participant($i), $participant);
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
