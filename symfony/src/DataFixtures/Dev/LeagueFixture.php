<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\League;
use App\Entity\LeagueMembership;
use App\Entity\Tournament;
use App\Entity\UsageConstraintPolicy;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Doctrine automation fixture populating dummy/developer League resources during dev environment boots.
 */
final class LeagueFixture extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var Tournament $tournament */
        $tournament = $this->getReference(DevReferences::TOURNAMENT, Tournament::class);

        $league = new League();
        $league->setTournament($tournament);
        $league->setName('Dev Playoff League');
        $league->setStatus('forming');
        $league->setLineupTemplate([
            ['role' => 'starter_a'],
            ['role' => 'starter_b'],
        ]);
        $league->setSettings([
            'fixture' => true,
        ]);

        /** @var User $commissioner */
        $commissioner = $this->getReference(DevReferences::user(1), User::class);
        $league->setCommissioner($commissioner);

        $manager->persist($league);

        for ($i = 1; $i <= 4; ++$i) {
            /** @var User $user */
            $user = $this->getReference(DevReferences::user($i), User::class);
            $membership = new LeagueMembership();
            $membership->setLeague($league);
            $membership->setUser($user);
            $membership->setNickname(\sprintf('user%d', $i));
            $membership->setRole($i === 1 ? 'commissioner' : 'member');
            $manager->persist($membership);
            $this->addReference(DevReferences::membership($i), $membership);
        }

        $policy = new UsageConstraintPolicy();
        $policy->setLeague($league);
        $policy->setConstraintType(UsageConstraintPolicy::TYPE_USE_ONCE_GLOBAL);
        $policy->setParameters(['scope' => 'league_season']);
        $policy->setIsActive(true);
        $manager->persist($policy);

        $this->addReference(DevReferences::LEAGUE, $league);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
            TournamentFixture::class,
        ];
    }

    public static function getGroups(): array
    {
        return [DevReferences::GROUP];
    }
}
