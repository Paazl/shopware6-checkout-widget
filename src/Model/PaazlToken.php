<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Model;

use Shopware\Core\Framework\Struct\Struct;

class PaazlToken extends Struct
{
    public function __construct(
        public readonly string $token
    ) {
    }
}
