<?php declare(strict_types=1);

namespace PaazlCheckoutWidget\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1664092345PaazlTokenTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664092345;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
        CREATE TABLE `paazl_token` (
            `cart_token` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `paazl_reference` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`cart_token`)
        );
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
