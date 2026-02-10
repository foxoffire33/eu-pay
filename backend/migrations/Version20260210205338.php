<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210205338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE webauthn_credentials (id UUID NOT NULL, credentialId TEXT NOT NULL, credentialPublicKey TEXT NOT NULL, signCount INT NOT NULL, aaguid VARCHAR(36) NOT NULL, deviceName VARCHAR(255) NOT NULL, transports JSON NOT NULL, attestationType VARCHAR(20) NOT NULL, createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updatedAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, lastUsedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DFEA8490A76ED395 ON webauthn_credentials (user_id)');
        $this->addSql('CREATE INDEX idx_credential_id ON webauthn_credentials (credentialId)');
        $this->addSql('ALTER TABLE webauthn_credentials ADD CONSTRAINT FK_DFEA8490A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE');
        $this->addSql('DROP INDEX idx_email_blind');
        $this->addSql('ALTER TABLE app_user ADD displayName VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER encryptedEmail DROP NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER emailIndex DROP NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER encryptedFirstName DROP NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER encryptedLastName DROP NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER password DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webauthn_credentials DROP CONSTRAINT FK_DFEA8490A76ED395');
        $this->addSql('DROP TABLE webauthn_credentials');
        $this->addSql('ALTER TABLE app_user DROP displayName');
        $this->addSql('ALTER TABLE app_user ALTER encryptedemail SET NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER emailindex SET NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER encryptedfirstname SET NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER encryptedlastname SET NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER password SET NOT NULL');
        $this->addSql('CREATE INDEX idx_email_blind ON app_user (emailindex)');
    }
}
