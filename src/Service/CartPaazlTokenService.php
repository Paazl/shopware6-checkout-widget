<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class CartPaazlTokenService
{
    private Connection $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    public function getReference(string $cartToken, bool $createNewReference = false): ?string
    {
        $reference = $this->connection->fetchOne(
            'SELECT paazl_reference FROM paazl_token WHERE cart_token = :cartToken',
            [':cartToken' => $cartToken]
        );

        if (!is_string($reference) && $createNewReference) {
            return $this->createNewReference($cartToken);
        }

        if (!is_string($reference)) {
            return null;
        }

        return $reference;
    }

    public function createNewReference(string $cartToken): string
    {
        $reference = substr(Uuid::randomHex(), 0, 20);

        $this->connection->executeStatement(
            <<<SQL
INSERT INTO paazl_token (cart_token, paazl_reference) VALUES (:cartToken, :paazlReference) ON DUPLICATE KEY UPDATE paazl_reference = :paazlReference;
SQL,
            [
                ':cartToken' => $cartToken,
                ':paazlReference' => $reference,
            ]
        );

        return $reference;
    }

    public function deleteReference(string $oldReference): void
    {
        $this->connection->executeStatement(
            <<<SQL
DELETE FROM paazl_token WHERE paazl_reference = :paazlReference
SQL,
            [
                ':paazlReference' => $oldReference,
            ]
        );
    }

    public function deleteToken(string $token): void
    {
        $this->connection->executeStatement(
            <<<SQL
DELETE FROM paazl_token WHERE cart_token = :token
SQL,
            [
                ':token' => $token,
            ]
        );
    }
}
