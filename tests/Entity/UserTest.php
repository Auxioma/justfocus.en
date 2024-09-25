<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Articles;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testInitialRoles(): void
    {
        $user = new User();

        $this->assertEquals(['ROLE_USER'], $user->getRoles(), 'Every user should have at least the ROLE_USER.');
    }

    public function testSetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertEquals(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles(), 'User roles should include ROLE_ADMIN and guarantee ROLE_USER.');
    }

    public function testEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('test@example.com', $user->getUserIdentifier());
    }

    public function testPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        $this->assertEquals('hashed_password', $user->getPassword());
    }

    public function testAddArticle(): void
    {
        $user = new User();
        $article = new Articles();

        $user->addArticle($article);

        $this->assertCount(1, $user->getArticles());
        $this->assertTrue($user->getArticles()->contains($article));
    }

    public function testRemoveArticle(): void
    {
        $user = new User();
        $article = new Articles();

        $user->addArticle($article);
        $user->removeArticle($article);

        $this->assertCount(0, $user->getArticles());
        $this->assertFalse($user->getArticles()->contains($article));
    }

    public function testIsVerified(): void
    {
        $user = new User();
        $this->assertFalse($user->isVerified());

        $user->setVerified(true);
        $this->assertTrue($user->isVerified());
    }
}
