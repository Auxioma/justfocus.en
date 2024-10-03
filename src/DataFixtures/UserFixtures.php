<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        for ($i = 1; $i <= 3000; ++$i) {
            $user = new User();
            $user->setId($i);
            $user->setEmail('email'.$i.'@example.com');
            $user->setFirstName('First Name '.$i);
            $user->setLastName('Last Name '.$i);
            $user->setPseudo('Pseudo '.$i);
            $user->setPassword('0000'); // Mot de passe par défaut '0000'
            $user->setRoles(['ROLE_USER']);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
