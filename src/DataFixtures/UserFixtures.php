<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $usersData = [
            ['id' => 1, 'pseudo' => 'guillaume2vo', 'email' => 'SUPPORT@AUXIOMA.EU', 'firstName' => '', 'lastName' => ''],
            ['id' => 2, 'pseudo' => 'alex', 'email' => 'david.duchene@titobulle.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 168, 'pseudo' => 'xavier', 'email' => 'chezxavier@hotmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 278, 'pseudo' => 'marine', 'email' => 'marine96.guillot@orange.fr', 'firstName' => 'Marine', 'lastName' => 'GUILLOT'],
            ['id' => 337, 'pseudo' => 'hugo', 'email' => 'hugo.turlan@orange.fr', 'firstName' => '', 'lastName' => ''],
            ['id' => 355, 'pseudo' => 'corentin', 'email' => 'tom.savidan@laposte.net', 'firstName' => 'Thomas', 'lastName' => 'Savidan'],
            ['id' => 488, 'pseudo' => 'Clemence Waller', 'email' => 'clemence.waller@outlook.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 570, 'pseudo' => 'Barbara Silvera', 'email' => 'barbarasilverasonigo@gmail.com', 'firstName' => 'Barbara', 'lastName' => 'S'],
            ['id' => 601, 'pseudo' => 'Ewen Linet', 'email' => 'ewenlinet@outlook.fr', 'firstName' => 'Ewen', 'lastName' => 'Linet'],
            ['id' => 643, 'pseudo' => 'Nino', 'email' => 'sernaolivier@yahoo.fr', 'firstName' => '', 'lastName' => ''],
            ['id' => 645, 'pseudo' => 'Elisabeth BOP', 'email' => 'eli.bop@hotmail.fr', 'firstName' => '', 'lastName' => ''],
            ['id' => 675, 'pseudo' => 'Margaux', 'email' => 'zaniolm@yahoo.com', 'firstName' => 'Margaux', 'lastName' => 'Zaniol'],
            ['id' => 679, 'pseudo' => 'Therealfolkblues', 'email' => 'herve.jazz@wanadoo.fr', 'firstName' => '', 'lastName' => ''],
            ['id' => 684, 'pseudo' => 'Kael', 'email' => 'klemarchand23420@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 686, 'pseudo' => 'Pauline Youssouf', 'email' => 'pauline.youssouf.py@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 688, 'pseudo' => 'Agathe Devignot', 'email' => 'agathedevignot@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 699, 'pseudo' => 'Guillaume Devaux', 'email' => 'guillaume2vo@yandex.ru', 'firstName' => 'gui', 'lastName' => 'gui'],
            ['id' => 700, 'pseudo' => 'Jeremy Bagnato', 'email' => 'jeremybagnato@gmail.com', 'firstName' => 'Jérémy', 'lastName' => 'Bagnato'],
            ['id' => 702, 'pseudo' => 'Louis Verdoux', 'email' => 'louis-verdoux@orange.fr', 'firstName' => '', 'lastName' => ''],
            ['id' => 703, 'pseudo' => 'lily', 'email' => 'vangjuju14@gmail.com', 'firstName' => 'julie', 'lastName' => ''],
            ['id' => 705, 'pseudo' => 'Miimii', 'email' => 'Miryam.allag13@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 726, 'pseudo' => 'Nawel Meliani', 'email' => 'nawel.meliani12@gmail.com', 'firstName' => 'Nawel', 'lastName' => 'Meliani'],
            ['id' => 731, 'pseudo' => 'Enjoy Little thing', 'email' => 'jpouppi@gmail.com', 'firstName' => 'Julie', 'lastName' => 'de FOURMESTRAUX'],
            ['id' => 734, 'pseudo' => 'Ines Tir', 'email' => 'inestir783@gmail.com', 'firstName' => 'Inès', 'lastName' => 'Tir'],
            ['id' => 735, 'pseudo' => 'Jakinboaz', 'email' => 'gevartguillaume@gmail.com', 'firstName' => 'David', 'lastName' => 'Charrier'],
            ['id' => 736, 'pseudo' => 'Dyslexi', 'email' => 'lejoueursilencieux83@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 737, 'pseudo' => 'Shana M', 'email' => 'shana.maouche@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 738, 'pseudo' => 'Ambre Marion', 'email' => 'Ambreg84.pro@gmail.com', 'firstName' => 'Ambre', 'lastName' => 'Marion'],
            ['id' => 739, 'pseudo' => 'Laetitia Grimaldi', 'email' => 'laetitia.grimaldi27@gmail.com', 'firstName' => '', 'lastName' => ''],
            ['id' => 740, 'pseudo' => 'Honorine et Julie', 'email' => 'honorine_mini-sucre@hotmail.fr', 'firstName' => '', 'lastName' => ''],
            ['id' => 741, 'pseudo' => 'Wienna', 'email' => 'wiennarazafind@gmail.com', 'firstName' => 'Wienna', 'lastName' => 'Razafindramiadana'],
        ];

        foreach ($usersData as $userData) {
            $user = new User();
            $user->setId($userData['id']);
            $user->setEmail($userData['email']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setPseudo($userData['pseudo']);
            $user->setPassword('0000'); // Mot de passe par défaut '0000'
            $user->setRoles(['ROLE_USER']);

            $manager->persist($user);
        }

        $manager->flush();
    }
}
