<?php declare(strict_types=1);

namespace PaazlCheckoutWidget\Core\Subscriber;

use Exception;
use PaazlCheckoutWidget\RestApi\PaazlClient;
use PaazlCheckoutWidget\Service\CartPaazlTokenService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaidOrderSubscriber implements EventSubscriberInterface
{
    private PaazlClient $paazlClient;

    public EntityRepositoryInterface $transactionRepository;

    private CartPaazlTokenService $cartPaazlTokenService;

    private SystemConfigService $systemConfigService;

    public function __construct(
        paazlClient $paazlClient,
        EntityRepositoryInterface $transactionRepository,
        CartPaazlTokenService $cartPaazlTokenService,
        SystemConfigService $systemConfigService
    ) {
        $this->paazlClient = $paazlClient;
        $this->transactionRepository = $transactionRepository;
        $this->cartPaazlTokenService = $cartPaazlTokenService;
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionStateChange'
        ];
    }

    /**
     * @throws Exception
     */
    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        $status = $event->getNextState();
        $orderTransactionId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.orderCustomer.customer');
        $criteria->addAssociation('order.orderCustomer.customer.addresses');
        $criteria->addAssociation('order.orderCustomer.customer.addresses.country');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('order.deliveries');
        $criteria->addAssociation('order.customFields');
        $criteria->addAssociation('order.lineItems');
        $criteria->getAssociation('order.documents')
            ->setLimit(1)
            ->addSorting(new FieldSorting('createdAt', 'DESC'));

        /** @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->transactionRepository->search($criteria, $context)->first();
        $customFields = $orderTransaction->getOrder()->getCustomFields();
        if ($customFields != null) {
            if ($status->getTechnicalName() === OrderTransactionStates::STATE_PAID && \array_key_exists('paazlData', $customFields)) {
                $this->paazlClient->createPaazlPaidOrder($orderTransaction, $context);
            }
        }
    }
}
