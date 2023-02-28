<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\Model;

use PaazlCheckoutWidget\Service\PaazlConfiguration;

class AddressParser
{
    private PaazlConfiguration $paazlConfiguration;

    public function __construct(
        PaazlConfiguration $paazlConfiguration
    ) {
        $this->paazlConfiguration = $paazlConfiguration;
    }

    public function parseAddress(string $street, string $salesChannelId): array
    {

        $street = trim(str_replace("\t", ' ', $street));
        $houseExtensionPattern
            = '(?<houseNumber>\d{1,5})[[:punct:]\-\/\s]*(?<houseNumberExtension>[^[:space:]]{1,2})?';
        $streetPattern = '(?<street>.+)';

        $patterns = [
            "/^{$streetPattern}[\s[:space:]]+{$houseExtensionPattern}$/",
            "/^{$houseExtensionPattern}[\s[:space:]]+{$streetPattern}$/",
        ];
        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $street, $matches)) {
                continue;
            }

            return [
                'street' => $matches['street'] ?? null,
                'houseNumber' => $matches['houseNumber'] ?? null,
                'houseNumberExtension' => $matches['houseNumberExtension'] ??
                        null,
            ];
        }

        $streetToParse = explode(' ', $street);
        $probably = [
            'street' => [],
            'houseNumber' => [],
            'houseNumberExtension' => [],
        ];

        if (ctype_digit(end($streetToParse))) {
            $probably = [
                'houseNumber' => array_pop($streetToParse),
                'street' => implode(' ', $streetToParse),
                'houseNumberExtension' => '',
            ];
            if ($probably['street'] && $probably['houseNumber']) {
                return $probably;
            }
        }
        foreach ($streetToParse as $parser) {
            if (!trim($parser)) {
                continue;
            }
            if (!preg_match('~[0-9]+~', $parser)
                && strlen($parser) > 2
            ) {
                $probably['street'][] = $parser;
            } elseif (ctype_digit($parser)) {
                $probably['houseNumber'][] = $parser;
            } else {
                $num = '';
                foreach (str_split($parser) as $index => $char) {
                    if (is_numeric($char)) {
                        $num .= $char;
                    } else {
                        $probably['houseNumber'][] = $num;
                        $probably['houseNumberExtension'][] = substr(
                            $parser,
                            $index,
                            strlen($parser) - $index
                        );
                        break;
                    }
                }
            }
        }

        $probably = [
            'street' => is_array($probably['street'])
                ? implode(' ', $probably['street'])
                : $probably['street'],
            'houseNumber' => is_array($probably['houseNumber'])
                ? implode('', $probably['houseNumber'])
                : $probably['houseNumber'],
            'houseNumberExtension' => is_array(
                $probably['houseNumberExtension']
            )
                ? implode('', $probably['houseNumberExtension'])
                : $probably['houseNumberExtension'],
        ];
        if ($probably['street'] && $probably['houseNumber']) {
            return $probably;
        }

        if ($this->paazlConfiguration->getHouseNumberDefaultOption(
            $salesChannelId
        )
        ) {
            return [
                'street' => $street,
                'houseNumber' => null,
                'houseNumberExtension' => null,
            ];
        }

        return [
            'street' => null,
            'houseNumber' => null,
            'houseNumberExtension' => null,
        ];
    }
}
