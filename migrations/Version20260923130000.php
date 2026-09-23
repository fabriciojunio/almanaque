<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tabela de sessão.
 *
 * Em produção a aplicação roda sem disco de escrita, então a sessão do
 * console não pode ficar em arquivo. Os nomes das colunas são os que o
 * PdoSessionHandler espera; só o nome da tabela é nosso.
 */
final class Version20260923130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sessão do console guardada no banco';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql(
                'CREATE TABLE sessoes ('
                .'sess_id VARBINARY(128) NOT NULL PRIMARY KEY, '
                .'sess_data BLOB NOT NULL, '
                .'sess_lifetime INTEGER UNSIGNED NOT NULL, '
                .'sess_time INTEGER UNSIGNED NOT NULL, '
                .'INDEX sessoes_expiracao (sess_lifetime)'
                .') COLLATE utf8mb4_bin, ENGINE = InnoDB'
            );

            return;
        }

        $this->addSql(
            'CREATE TABLE sessoes ('
            .'sess_id VARCHAR(128) NOT NULL PRIMARY KEY, '
            .'sess_data BYTEA NOT NULL, '
            .'sess_lifetime INTEGER NOT NULL, '
            .'sess_time INTEGER NOT NULL'
            .')'
        );
        $this->addSql('CREATE INDEX sessoes_expiracao ON sessoes (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sessoes');
    }
}
