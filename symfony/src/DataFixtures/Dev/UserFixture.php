<?php

declare(strict_types=1);

namespace App\DataFixtures\Dev;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

final class UserFixture extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 4; ++$i) {
            $user = new User();
            $user->setEmail(\sprintf('user%d@fixture.local', $i));
            $user->setDisplayName(\sprintf('user%d', $i));

            $manager->persist($user);
            $this->addReference(DevReferences::user($i), $user);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return [DevReferences::GROUP];
    }
}
