<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\DraftPick;
use App\Entity\DraftSession;
use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\Participant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Completed snake draft: 4 teams × 4 picks = 16 participants taken.
 */
final class DraftFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var League $league */
        $league = $this->getReference(DevReferences::LEAGUE, League::class);

        /** @var list<LeagueMembership> $memberships */
        $memberships = [];
        for ($i = 1; $i <= 4; ++$i) {
            $memberships[] = $this->getReference(DevReferences::membership($i), LeagueMembership::class);
        }

        $orderIds = array_map(static fn (LeagueMembership $m) => (string) $m->getId(), $memberships);

        $session = new DraftSession();
        $session->setStatus('completed');
        $session->setConfig([
            'snake' => true,
            'order_membership_ids' => $orderIds,
        ]);
        $league->setDraftSession($session);
        $league->setStatus('active');
        $manager->persist($session);

        /** @var list<Participant> $participants */
        $participants = [];
        for ($i = 1; $i <= 16; ++$i) {
            $participants[] = $this->getReference(DevReferences::participant($i), Participant::class);
        }

        $pickIndex = 0;
        for ($draftRound = 0; $draftRound < 4; ++$draftRound) {
            $positions = [0, 1, 2, 3];
            if ($draftRound % 2 === 1) {
                $positions = [3, 2, 1, 0];
            }
            foreach ($positions as $pos) {
                $pick = new DraftPick();
                $pick->setDraftSession($session);
                $pick->setPickIndex($pickIndex);
                $pick->setLeagueMembership($memberships[$pos]);
                $pick->setParticipant($participants[$pickIndex]);
                $session->addPick($pick);
                $manager->persist($pick);
                ++$pickIndex;
            }
        }

        $this->addReference(DevReferences::DRAFT_SESSION, $session);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            LeagueFixture::class,
            ParticipantFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return [DevReferences::GROUP];
    }
}
