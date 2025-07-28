<?php
declare(strict_types=1);

namespace PaazlCheckoutWidget\Subscriber;

use PaazlCheckoutWidget\PaazlCheckoutWidget;
use PaazlCheckoutWidget\RestApi\PaazlApiTokenProvider;
use PaazlCheckoutWidget\Service\GeneratePaazlScript;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class PaazlCheckoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaazlApiTokenProvider $apiTokenProvider,
        private GeneratePaazlScript $generatePaazlScript,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'paazlCheckoutFunction',
            KernelEvents::CONTROLLER_ARGUMENTS => 'onRequest',
        ];
    }

    public function paazlCheckoutFunction(CheckoutConfirmPageLoadedEvent $event): void
    {
        $shippingMethod = $event->getSalesChannelContext()->getShippingMethod();
        if ($shippingMethod->getCustomFieldsValue('paazlName') !== PaazlCheckoutWidget::SHIPPING_METHOD_NAME) {
            return;
        }

        $event->getPage()->assign([
            'paazlTokenData' => [
                'token' => $this->apiTokenProvider->getApiToken(
                    $event->getPage()->getCart(),
                    $event->getSalesChannelContext()
                ),
            ],
            'paazlConfig' => $this->generatePaazlScript->generatePaazlScript($event->getSalesChannelContext()),
        ]);
    }


    public function onRequest(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->query->has('paazlWidgetMissing')) {
            return;
        }

        $context = $request->attributes->get('sw-sales-channel-context');
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        $this->logger->warning('PAAZL_WIDGET_MISSING', [
            'ip' => $request->getClientIp(),
            'requestUri' => $request->getRequestUri(),
            'cookies' => $request->cookies->all(),
            'query' => $request->query->all(),
            'request' => $request->request->all(),
            'customerId' => $context->getCustomerId(),
            'token' => $context->getToken(),
            'paymentMethod' => $context->getPaymentMethod()->getTranslation('name'),
        ]);
    }
}
