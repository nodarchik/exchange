<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827111722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rates table for cryptocurrency exchange rates';
    }

    public function up(Schema $schema): void
    {
        // Create rates table
        $this->addSql('CREATE TABLE rates (
            id INT AUTO_INCREMENT NOT NULL, 
            pair VARCHAR(10) NOT NULL, 
            price DECIMAL(20, 8) NOT NULL, 
            recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create indexes for better query performance
        $this->addSql('CREATE INDEX idx_pair_recorded_at ON rates (pair, recorded_at)');
        $this->addSql('CREATE INDEX idx_recorded_at ON rates (recorded_at)');
    }

    public function down(Schema $schema): void
    {
        // Drop the rates table
        $this->addSql('DROP TABLE rates');
    }
}
