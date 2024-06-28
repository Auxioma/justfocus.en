<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240628121917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE articles_category (articles_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_A7D8EFDB1EBAF6CC (articles_id), INDEX IDX_A7D8EFDB12469DE2 (category_id), PRIMARY KEY(articles_id, category_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE articles_category ADD CONSTRAINT FK_A7D8EFDB1EBAF6CC FOREIGN KEY (articles_id) REFERENCES articles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE articles_category ADD CONSTRAINT FK_A7D8EFDB12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles_category DROP FOREIGN KEY FK_A7D8EFDB1EBAF6CC');
        $this->addSql('ALTER TABLE articles_category DROP FOREIGN KEY FK_A7D8EFDB12469DE2');
        $this->addSql('DROP TABLE articles_category');
    }
}
