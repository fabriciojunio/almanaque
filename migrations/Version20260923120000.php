<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Esquema inicial do Almanaque.
 */
final class Version20260923120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Portais, guia, assinatura recorrente, identidade e atendimento';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE anuncios (id INT AUTO_INCREMENT NOT NULL, titulo VARCHAR(150) NOT NULL, apelido VARCHAR(160) NOT NULL, descricao LONGTEXT NOT NULL, situacao VARCHAR(20) NOT NULL, endereco VARCHAR(120) DEFAULT NULL, bairro VARCHAR(80) DEFAULT NULL, cidade VARCHAR(80) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, telefone VARCHAR(20) DEFAULT NULL, site VARCHAR(180) DEFAULT NULL, imagens JSON NOT NULL, destaque TINYINT NOT NULL, visualizacoes INT NOT NULL, publicado_em DATETIME DEFAULT NULL, expira_em DATETIME DEFAULT NULL, criado_em DATETIME NOT NULL, atualizado_em DATETIME NOT NULL, portal_id INT NOT NULL, categoria_id INT NOT NULL, INDEX anuncio_portal_situacao (portal_id, situacao), INDEX anuncio_expira_em (expira_em), UNIQUE INDEX anuncio_apelido_portal (portal_id, apelido), INDEX IDX_9AFBE206B887E1DD (portal_id), INDEX IDX_9AFBE2063397707A (categoria_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categorias (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(80) NOT NULL, apelido VARCHAR(80) NOT NULL, ordem INT NOT NULL, portal_id INT NOT NULL, pai_id INT DEFAULT NULL, UNIQUE INDEX categoria_apelido_portal (portal_id, apelido), INDEX IDX_5E9F836CB887E1DD (portal_id), INDEX IDX_5E9F836CC19C5634 (pai_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE assinaturas (id INT AUTO_INCREMENT NOT NULL, situacao VARCHAR(20) NOT NULL, proxima_cobranca_em DATE NOT NULL, tentativas_seguidas_recusadas INT NOT NULL, criada_em DATETIME NOT NULL, cancelada_em DATETIME DEFAULT NULL, anuncio_id INT NOT NULL, plano_id INT NOT NULL, UNIQUE INDEX UNIQ_4B24B9B7963066FD (anuncio_id), INDEX assinatura_proxima_cobranca (situacao, proxima_cobranca_em), INDEX IDX_4B24B9B79A8B86CC (plano_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cobrancas (id INT AUTO_INCREMENT NOT NULL, valor_em_centavos INT NOT NULL, competencia VARCHAR(7) NOT NULL, situacao VARCHAR(20) NOT NULL, motivo_da_recusa VARCHAR(200) DEFAULT NULL, identificador_no_gateway VARCHAR(60) DEFAULT NULL, aberta_em DATETIME NOT NULL, fechada_em DATETIME DEFAULT NULL, assinatura_id INT NOT NULL, INDEX cobranca_situacao (situacao), INDEX IDX_503F3B0A9757A0A7 (assinatura_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planos (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(60) NOT NULL, preco_em_centavos INT NOT NULL, ciclo VARCHAR(10) NOT NULL, inclui_destaque TINYINT NOT NULL, limite_de_imagens INT NOT NULL, ativo TINYINT NOT NULL, criado_em DATETIME NOT NULL, portal_id INT NOT NULL, INDEX IDX_C98178CB887E1DD (portal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tokens_acesso (id INT AUTO_INCREMENT NOT NULL, hash VARCHAR(64) NOT NULL, descricao VARCHAR(80) NOT NULL, expira_em DATETIME NOT NULL, revogado_em DATETIME DEFAULT NULL, usado_em DATETIME DEFAULT NULL, criado_em DATETIME NOT NULL, usuario_id INT NOT NULL, UNIQUE INDEX token_hash (hash), INDEX IDX_964D7E1CDB38439E (usuario_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuarios (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nome VARCHAR(120) NOT NULL, senha VARCHAR(255) NOT NULL, papel VARCHAR(30) NOT NULL, ativo TINYINT NOT NULL, ultimo_acesso DATETIME DEFAULT NULL, criado_em DATETIME NOT NULL, portal_id INT DEFAULT NULL, UNIQUE INDEX usuario_email (email), INDEX IDX_EF687F2B887E1DD (portal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE portais (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(120) NOT NULL, apelido VARCHAR(60) NOT NULL, dominio VARCHAR(180) DEFAULT NULL, situacao VARCHAR(20) NOT NULL, versao VARCHAR(20) NOT NULL, customizado TINYINT NOT NULL, criado_em DATETIME NOT NULL, INDEX portal_dominio (dominio), UNIQUE INDEX portal_apelido (apelido), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE anotacoes (id INT AUTO_INCREMENT NOT NULL, autor VARCHAR(120) NOT NULL, texto LONGTEXT NOT NULL, visivel_ao_cliente TINYINT NOT NULL, escrita_em DATETIME NOT NULL, chamado_id INT NOT NULL, INDEX IDX_9AF175729D9E8FBC (chamado_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chamados (id INT AUTO_INCREMENT NOT NULL, titulo VARCHAR(160) NOT NULL, relato LONGTEXT NOT NULL, autor VARCHAR(120) NOT NULL, situacao VARCHAR(20) NOT NULL, prioridade VARCHAR(20) NOT NULL, classificacao VARCHAR(30) DEFAULT NULL, identificador_de_requisicao VARCHAR(64) DEFAULT NULL, versao_do_portal VARCHAR(20) NOT NULL, reproduzido TINYINT NOT NULL, reproduzido_em_ambiente_limpo TINYINT NOT NULL, responsavel VARCHAR(120) DEFAULT NULL, aberto_em DATETIME NOT NULL, primeira_resposta_em DATETIME DEFAULT NULL, resolvido_em DATETIME DEFAULT NULL, portal_id INT NOT NULL, problema_conhecido_id INT DEFAULT NULL, INDEX chamado_fila (situacao, prioridade), INDEX chamado_portal (portal_id), INDEX chamado_requisicao (identificador_de_requisicao), INDEX IDX_C638B1C4AD18AE57 (problema_conhecido_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE problemas_conhecidos (id INT AUTO_INCREMENT NOT NULL, codigo VARCHAR(20) NOT NULL, titulo VARCHAR(160) NOT NULL, sintoma LONGTEXT NOT NULL, causa LONGTEXT NOT NULL, contorno LONGTEXT DEFAULT NULL, versao_afetada_de VARCHAR(20) DEFAULT NULL, corrigido_na_versao VARCHAR(20) DEFAULT NULL, registrado_em DATETIME NOT NULL, UNIQUE INDEX problema_codigo (codigo), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE publicacoes (id INT AUTO_INCREMENT NOT NULL, versao_anterior VARCHAR(20) NOT NULL, versao_nova VARCHAR(20) NOT NULL, publicada_por VARCHAR(120) NOT NULL, situacao VARCHAR(20) NOT NULL, verificacoes JSON NOT NULL, observacao LONGTEXT DEFAULT NULL, iniciada_em DATETIME NOT NULL, encerrada_em DATETIME DEFAULT NULL, portal_id INT NOT NULL, INDEX publicacao_portal (portal_id, iniciada_em), INDEX IDX_FF96F0BEB887E1DD (portal_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE anuncios ADD CONSTRAINT FK_9AFBE206B887E1DD FOREIGN KEY (portal_id) REFERENCES portais (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE anuncios ADD CONSTRAINT FK_9AFBE2063397707A FOREIGN KEY (categoria_id) REFERENCES categorias (id)');
        $this->addSql('ALTER TABLE categorias ADD CONSTRAINT FK_5E9F836CB887E1DD FOREIGN KEY (portal_id) REFERENCES portais (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE categorias ADD CONSTRAINT FK_5E9F836CC19C5634 FOREIGN KEY (pai_id) REFERENCES categorias (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assinaturas ADD CONSTRAINT FK_4B24B9B7963066FD FOREIGN KEY (anuncio_id) REFERENCES anuncios (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assinaturas ADD CONSTRAINT FK_4B24B9B79A8B86CC FOREIGN KEY (plano_id) REFERENCES planos (id)');
        $this->addSql('ALTER TABLE cobrancas ADD CONSTRAINT FK_503F3B0A9757A0A7 FOREIGN KEY (assinatura_id) REFERENCES assinaturas (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planos ADD CONSTRAINT FK_C98178CB887E1DD FOREIGN KEY (portal_id) REFERENCES portais (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tokens_acesso ADD CONSTRAINT FK_964D7E1CDB38439E FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE usuarios ADD CONSTRAINT FK_EF687F2B887E1DD FOREIGN KEY (portal_id) REFERENCES portais (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE anotacoes ADD CONSTRAINT FK_9AF175729D9E8FBC FOREIGN KEY (chamado_id) REFERENCES chamados (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chamados ADD CONSTRAINT FK_C638B1C4B887E1DD FOREIGN KEY (portal_id) REFERENCES portais (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chamados ADD CONSTRAINT FK_C638B1C4AD18AE57 FOREIGN KEY (problema_conhecido_id) REFERENCES problemas_conhecidos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE publicacoes ADD CONSTRAINT FK_FF96F0BEB887E1DD FOREIGN KEY (portal_id) REFERENCES portais (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publicacoes');
        $this->addSql('DROP TABLE chamados');
        $this->addSql('DROP TABLE problemas_conhecidos');
        $this->addSql('DROP TABLE anotacoes');
        $this->addSql('DROP TABLE cobrancas');
        $this->addSql('DROP TABLE assinaturas');
        $this->addSql('DROP TABLE tokens_acesso');
        $this->addSql('DROP TABLE usuarios');
        $this->addSql('DROP TABLE anuncios');
        $this->addSql('DROP TABLE planos');
        $this->addSql('DROP TABLE categorias');
        $this->addSql('DROP TABLE portais');
    }
}
