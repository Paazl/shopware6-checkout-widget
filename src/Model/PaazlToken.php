<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\Model;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;

class PaazlToken extends Struct
{
    private string $token;

    public function __construct(
        string $token
    ) {
        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
