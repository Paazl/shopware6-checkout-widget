<?php declare(strict_types=1);

namespace PaazlCheckoutWidget\Service\Logger;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class PaazlLogger
{
    private EntityRepositoryInterface $logEntryRepository;

    private LoggerInterface $logger;

    public function __construct(
        EntityRepositoryInterface $logEntryRepository,
        LoggerInterface $logger
    ) {
        $this->logEntryRepository = $logEntryRepository;
        $this->logger = $logger;
    }

    public function addEntry(
        $message,
        Context $context,
        ?\Throwable $exception = null,
        ?array $additionalData = null,
        int $level = Logger::DEBUG
    ): void {
        if (!is_array($additionalData)) {
            $additionalData = [];
        }

        // Add exception to array
        if ($exception !== null) {
            $additionalData['error'] = [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ];
        }

        $this->logger->log($level, $message, $additionalData);

        $this->logEntryRepository->create([[
            'id' => Uuid::randomHex(),
            'message' => mb_substr($message, 0, 255),
            'level' => $level,
            'channel' => 'Paazl',
            'context' => [
                'source' => 'Paazl',
                'additionalData' => $additionalData,
                'shopContext' => $context->getVars(),
            ],
        ]], $context);
    }
}
