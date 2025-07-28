<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class CartPaazlTokenService
{
    private array $references = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly PaazlConfiguration $paazlConfiguration
    ) {
    }

    public function getReference(string $cartToken, string $salesChannelId, bool $createNewReference = false): ?string
    {
        if (array_key_exists($cartToken, $this->references)) {
            return $this->references[$cartToken];
        }

        $lifetime = $this->paazlConfiguration->tokenReferenceLifetime($salesChannelId);
        $reference = $this->connection->fetchOne(
            'SELECT paazl_reference FROM paazl_token WHERE `cart_token` = :cartToken AND `date` > NOW() - INTERVAL ' . $lifetime . ' DAY',
            ['cartToken' => $cartToken]
        );

        if (!is_string($reference) && $createNewReference) {
            return $this->createNewReference($cartToken);
        }

        if (!is_string($reference)) {
            return null;
        }

        $this->references[$cartToken] = $reference;

        return $reference;
    }

    public function createNewReference(string $cartToken): string
    {
        $reference = substr(Uuid::randomHex(), 0, 20);

        $this->connection->executeStatement(
            'INSERT INTO paazl_token (cart_token, paazl_reference, `date`) VALUES (:cartToken, :paazlReference, NOW()) ON DUPLICATE KEY UPDATE paazl_reference = :paazlReference',
            [
                'cartToken' => $cartToken,
                'paazlReference' => $reference,
            ]
        );

        $this->references[$cartToken] = $reference;

        return $reference;
    }

    public function deleteReference(?string $oldReference): void
    {
        if (!$oldReference) {
            return;
        }

        if (($key = array_search($oldReference, $this->references, true)) !== false) {
            unset($this->references[$key]);
        }

        $this->connection->executeStatement(
            'DELETE FROM paazl_token WHERE paazl_reference = :paazlReference',
            [
                'paazlReference' => $oldReference,
            ]
        );
    }

    public function deleteToken(string $token): void
    {
        unset($this->references[$token]);
        $this->connection->executeStatement(
            'DELETE FROM paazl_token WHERE cart_token = :token',
            [
                'token' => $token,
            ]
        );
    }

    /**
     * Deletes all Paazl references for carts that don't exist anymore.
     */
    public function cleanup(): void
    {
        $this->connection->executeStatement('DELETE FROM paazl_token p WHERE NOT EXISTS (SELECT * FROM cart c WHERE c.token = p.cart_token);');
    }
}
