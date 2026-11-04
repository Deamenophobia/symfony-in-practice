<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ordering, synthetic token, fulfillment, and Messenger lab tables.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf('symfony_practice_test' !== $this->connection->getDatabase(), 'Use the isolated symfony_practice_test database.');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, account_id INT NOT NULL, sku VARCHAR(40) NOT NULL, price_cents INT NOT NULL, PRIMARY KEY(id), UNIQUE INDEX product_account_sku (account_id, sku), CHECK (price_cents >= 0)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, account_id INT NOT NULL, sku VARCHAR(40) NOT NULL, quantity INT NOT NULL, unit_price_cents INT NOT NULL, total_cents INT NOT NULL, reference VARCHAR(80) DEFAULT NULL, status VARCHAR(20) NOT NULL, PRIMARY KEY(id), INDEX orders_account (account_id, id), CHECK (quantity BETWEEN 1 AND 20), CHECK (unit_price_cents >= 0), CHECK (total_cents = quantity * unit_price_cents)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE api_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, account_id INT NOT NULL, token_hash CHAR(64) NOT NULL, abilities JSON NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, PRIMARY KEY(id), UNIQUE INDEX token_hash_unique (token_hash)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE fulfillment_tasks (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, PRIMARY KEY(id), UNIQUE INDEX fulfillment_order (order_id), CONSTRAINT fulfillment_order_fk FOREIGN KEY(order_id) REFERENCES orders(id)) ENGINE=InnoDB');
        $this->addSql('CREATE TABLE delivery_attempts (order_id INT NOT NULL, attempts INT NOT NULL, PRIMARY KEY(order_id), CONSTRAINT attempts_order_fk FOREIGN KEY(order_id) REFERENCES orders(id)) ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('symfony_practice_test' !== $this->connection->getDatabase(), 'Use the isolated symfony_practice_test database.');
        foreach (['delivery_attempts', 'fulfillment_tasks', 'api_tokens', 'orders', 'products'] as $table) {
            $this->addSql('DROP TABLE '.$table);
        }
    }
}
