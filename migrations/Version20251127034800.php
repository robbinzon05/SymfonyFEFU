<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add roles, password and api_token fields to user entity.
 */
final class Version20251127034800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles (JSON), password and api_token columns to "user" table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE "user" ADD roles JSON DEFAULT \'["ROLE_USER"]\'::json'
        );

        $this->addSql(
            'UPDATE "user" SET roles = \'["ROLE_USER"]\'::json WHERE roles IS NULL'
        );

        $this->addSql(
            'ALTER TABLE "user" ALTER COLUMN roles SET NOT NULL'
        );

        $this->addSql(
            'ALTER TABLE "user" ADD password VARCHAR(255) DEFAULT NULL'
        );
        $this->addSql(
            'ALTER TABLE "user" ADD api_token VARCHAR(64) DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP api_token');
        $this->addSql('ALTER TABLE "user" DROP password');
        $this->addSql('ALTER TABLE "user" DROP roles');
    }
}
