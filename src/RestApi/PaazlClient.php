<?php

declare(strict_types=1);

namespace PaazlCheckoutWidget\RestApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use PaazlCheckoutWidget\Model\AddressParser;
use PaazlCheckoutWidget\Model\PaazlToken;
use PaazlCheckoutWidget\Service\CartPaazlTokenService;
use PaazlCheckoutWidget\Service\Logger\PaazlLogger;
use PaazlCheckoutWidget\Service\PaazlConfiguration;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class PaazlClient
{
    private const BASE_URL_LIVE = 'https://api.paazl.com/v1/';
    private const BASE_URL_TEST = 'https://api-acc.paazl.com/v1/';

    /**
     * @var SystemConfigService
     */
    public $systemConfigService;

    /**
     * @var SessionInterface
    */
    private $session;

    private EntityRepositoryInterface $languageRepository;

    private CartService $cartService;

    private EntityRepositoryInterface $countryRepository;

    private EntityRepositoryInterface $orderRepository;

    protected DeliveryCalculator $deliveryCalculator;

    protected DeliveryBuilder $builder;

    public PaazlLogger $paazlLogger;

    public Client $restClient;

    private PaazlConfiguration $paazlConfiguration;

    private AddressParser $addressParser;

    private CartPersisterInterface $cartPersister;

    private CartPaazlTokenService $cartPaazlTokenService;

    /**
     * @var LoggerInterface
    */
    public $logger;

    public function __construct(
        SystemConfigService       $systemConfigService,
        SessionInterface          $session,
        EntityRepositoryInterface $languageRepository,
        CartService $cartService,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $orderRepository,
        DeliveryBuilder $builder,
        DeliveryCalculator $deliveryCalculator,
        PaazlLogger $paazlLogger,
        PaazlConfiguration $paazlConfiguration,
        AddressParser $addressParser,
        CartPersisterInterface $cartPersister,
        CartPaazlTokenService $cartPaazlTokenService,
        LoggerInterface           $logger
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->session = $session;
        $this->languageRepository = $languageRepository;
        $this->cartService = $cartService;
        $this->countryRepository = $countryRepository;
        $this->orderRepository = $orderRepository;
        $this->builder = $builder;
        $this->deliveryCalculator = $deliveryCalculator;
        $this->paazlLogger = $paazlLogger;
        $this->restClient = new Client();
        $this->paazlConfiguration = $paazlConfiguration;
        $this->addressParser = $addressParser;
        $this->cartPersister = $cartPersister;
        $this->cartPaazlTokenService = $cartPaazlTokenService;
        $this->logger = $logger;
    }

    public function getBaseUrl($salesChannelId): string
    {
        return $this->paazlConfiguration->getMode($salesChannelId)
        === 'production'
            ? self::BASE_URL_LIVE
            : self::BASE_URL_TEST;
    }

    private function request(
        string $method,
        string $salesChannelId,
        string $url,
        array $options = []
    ): ResponseInterface {
        $apiKey = $this->paazlConfiguration->getApiKey($salesChannelId);
        $secretKey = $this->paazlConfiguration->getSecretKey($salesChannelId);
        $url = $this->getBaseUrl($salesChannelId) . $url;

        $options = array_merge_recursive([
            'headers' => [
                'content-type' => 'application/json',
                'authorization' => 'Bearer ' . $apiKey . ':' . $secretKey,
            ],
        ], $options);

        return $this->restClient->request($method, $url, $options);
    }

    public function order(
        array $orderData,
        string $salesChannelId
    ): ResponseInterface {
        return $this->request('PUT', $salesChannelId, 'order', [
            'headers' => [
                'Accept' => 'application/json;charset=UTF-8',
            ],
            'json' => $orderData,
        ]);
    }

    public function getApiTokenValidate($salesChannelId, $context, $endPoint)
    {
        if (($endPoint === 'front') && $this->session->get('paazlToken')) {
            return $this->session->get('paazlToken');
        }

        $apiKey = $this->getApiKey($salesChannelId);
        $secretKey = $this->getSecretKey($salesChannelId);
        $apiURL = $this->getCheckoutTokenUrl($salesChannelId);
        $orderNumber = $this->getReferencePrefix($salesChannelId);

        try {
            $response = $this->restClient->post(
                $apiURL,
                [
                    'headers' => [
                        'content-type' => 'application/json',
                        'authorization' => 'Bearer ' . $apiKey . ':' . $secretKey
                    ],
                    'json' => ['reference' => $orderNumber],
                ]
            );
        } catch (GuzzleException $e) {
            if (($endPoint === 'front') && $this->debug($salesChannelId)) {
                $this->logger->info('Paazl apiToken guzzleError : {message}', ['message' => $e->getMessage()]);
                $this->paazlLogger->addEntry(
                    'Paazl apiToken guzzleError :',
                    $context->getContext(),
                    null,
                    [$e->getMessage()]
                );
            }
            return ['type' => 'error', 'message' => $e->getMessage()];
        }
        if ($response->getStatusCode() !== 200) {
            return ['type' => 'error', 'message' => 'API error'];
        }
        try {
            $responseToken = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
            $this->session->set('paazlToken', $responseToken['token']);
            return $responseToken['token'];
        } catch (JsonException $e) {
            if (($endPoint === 'front') && $this->debug($salesChannelId)) {
                $this->logger->info('Paazl apiToken jsonError : {message}', ['message' => $e->getMessage()]);

                $this->paazlLogger->addEntry(
                    'Paazl apiToken jsonError :',
                    $context->getContext(),
                    null,
                    [$e->getMessage()]
                );
            }
            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getApiKey(string $salesChannelId)
    {
        return $this->systemConfigService->get('PaazlCheckoutWidget.config.apiKey', $salesChannelId);
    }

    public function getSecretKey(string $salesChannelId)
    {
        return $this->systemConfigService->get('PaazlCheckoutWidget.config.SecretKey', $salesChannelId);
    }

    public function getCheckoutTokenUrl($salesChannelId): string
    {
        return $this->getBaseUrl($salesChannelId) . 'checkout/token';
    }

    private function getPrefix(string $salesChannelId)
    {
        return $this->systemConfigService->get('PaazlCheckoutWidget.config.referencePrefix', $salesChannelId);
    }

    public function getReferencePrefix($salesChannelId): string
    {
        if ($this->session->get('paazlReference')) {
            return $this->session->get('paazlReference');
        }
        $paazlReference = $this->getPrefix($salesChannelId) . $this->getOrderNumber() . rand(1, 9999);
        $this->session->set('paazlReference', $paazlReference);
        return $paazlReference;
    }

    public function debug(string $salesChannelId)
    {
        return $this->systemConfigService->get('PaazlCheckoutWidget.config.debugMode', $salesChannelId);
    }

    public function getApiToken(
        ?Cart $cart,
        string $salesChannelId,
        Context $context,
        string $endPoint,
        ?SalesChannelContext $salesChannelContext = null
    ) {
        if ($endPoint === 'front' && $cart
            && $cart->hasExtension(
                'paazlToken'
            )
        ) {
            $reference = $this->cartPaazlTokenService->getReference(
                $cart->getToken()
            );

            if ($reference) {
                /** @var PaazlToken $paazlToken */
                $paazlToken = $cart->getExtensionOfType(
                    'paazlToken',
                    PaazlToken::class
                );

                return $paazlToken->getToken();
            }
        }

        try {
            $response = $this->request(
                'POST',
                $salesChannelId,
                'checkout/token',
                [
                    'json' => [
                        'reference' => $this->cartPaazlTokenService->getReference(
                            $cart->getToken(),
                            true
                        ),
                    ],
                ]
            );
        } catch (GuzzleException $e) {
            if (($endPoint === 'front')
                && $this->paazlConfiguration->debug(
                    $salesChannelId
                )
            ) {
                $this->paazlLogger->addEntry(
                    'Paazl apiToken guzzleError :',
                    $context,
                    $e
                );
            }

            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        if ($response->getStatusCode() !== 200) {
            return ['type' => 'error', 'message' => 'API error'];
        }

        try {
            $responseToken = json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                \JSON_THROW_ON_ERROR
            );

            if ($salesChannelContext) {
                $cart->addExtension(
                    'paazlToken',
                    new PaazlToken($responseToken['token'])
                );
                $this->cartPersister->save($cart, $salesChannelContext);
            }

            return $responseToken['token'];
        } catch (JsonException $e) {
            if (($endPoint === 'front')
                && $this->paazlConfiguration->debug(
                    $salesChannelId
                )
            ) {
                $this->paazlLogger->addEntry(
                    'Paazl apiToken jsonError :',
                    $context,
                    null,
                    [$e->getMessage()]
                );
            }

            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function generatePaazlScript(
        SalesChannelContext $salesChannelContext,
        ?Request $request = null
    ): array {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $languageId = $salesChannelContext->getSalesChannel()->getLanguageId();
        $shippingAddress = $salesChannelContext->getCustomer()
            ? $salesChannelContext->getCustomer()->getDefaultShippingAddress()
            : null;
        $cart = $this->cartService->getCart(
            $salesChannelContext->getToken(),
            $salesChannelContext
        );

        $numberOfProcessingDays = [0];
        $weight = $qty = $goods = [];
        $item = [];
        foreach ($cart->getLineItems() as $item) {
            if ($item->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $qty[] = $item->getQuantity();

            $payload = $item->getpayload();

            if ($payload['customFields'] !== null
                && array_key_exists('customFields', $payload)
                && array_key_exists('paazl', $payload['customFields'])
            ) {
                $numberOfProcessingDays[]
                    = $payload['customFields']['paazl']['numberOfProcessingDays']
                    ?? 0;
            }
            $deliveryInformation = $item->getDeliveryInformation();
            $priceConfiguration = $this->paazlConfiguration->getPrice($salesChannelId);
            $shippingPrice = $cart->getShippingCosts()->getTotalPrice();
            $totalTax = $cart->getPrice()->getCalculatedTaxes()->first()->getTax();

            if ($priceConfiguration == 'TotalInclTax') {
                $price =  $cart->getPrice()->getTotalPrice();
            } else {
                $price =  $cart->getPrice()->getTotalPrice()  - $totalTax;
            }

            if (!$deliveryInformation) {
                $weight[] = 0.00;
                $goods[] = [
                    "quantity" => $item->getQuantity(),
                    "weight" => 0.00,
                    "length" => 0.00,
                    "width" => 0.00,
                    "height" => 0.00,
                    "price" => (float)(number_format($price, 2, ".", "")),
                ];
                continue;
            }

            $weight[] = $item->getQuantity()
                * ((float)$deliveryInformation->getWeight());

            $goods[] = [
                "quantity" => $item->getQuantity(),
                "weight" => (float)$deliveryInformation->getWeight(),
                "length" => (float)$deliveryInformation->getLength(),
                "width" => (float)$deliveryInformation->getWidth(),
                "height" => (float)$deliveryInformation->getHeight(),
                "price" => (float)(number_format($price, 2, ".", "")),
            ];
        }

        if ($request instanceof Request) {
            $postcode = $request->get(
                'postalCode',
                $this->paazlConfiguration->getDefaultPostcode($salesChannelId)
            );
            $countryIso = $request->get(
                'countryId',
                $this->paazlConfiguration->getDefaultCountry($salesChannelId)
            );
        } elseif ($shippingAddress && $shippingAddress->getCountryId()
            && $shippingAddress->getZipcode()
        ) {
            $postcode = $shippingAddress->getZipcode();
            $countryIso = $shippingAddress->getCountryId();
        } else {
            $postcode = $this->paazlConfiguration->getDefaultPostcode(
                $salesChannelId
            );
            $countryIso = $this->paazlConfiguration->getDefaultCountry(
                $salesChannelId
            );
        }

        $paazlConfig = [
            "mountElementId" => "paazl-checkout",
            "apiKey" => $this->paazlConfiguration->getApiKey($salesChannelId),
            "token" => $this->getApiToken(
                $cart,
                $salesChannelId,
                $context,
                'front',
                $salesChannelContext
            ),
            "loadPaazlBasedData" => true,
            "loadCarrierBasedData" => true,
            "availableTabs" => $this->paazlConfiguration->getAvailableTabs(
                $salesChannelId
            ),
            "defaultTab" => $this->paazlConfiguration->getDefaultTab(
                $salesChannelId
            ),
            "headerTabType" => $this->paazlConfiguration->getWidgetSectionToggle(
                $salesChannelId
            ),
            "style" => $this->paazlConfiguration->getWidgetTheme(
                $salesChannelId
            ),
            "nominatedDateEnabled" => (bool)$this->paazlConfiguration->getNominatedDateEnabled(
                $salesChannelId
            ),
            "consigneeCountryCode" => $this->getCountryIso(
                $countryIso,
                $context
            ),
            "consigneePostalCode" => $postcode,
            "deliveryType" => $this->paazlConfiguration->getDefaultTab(
                $salesChannelId
            ),
            "customerNumber" => $salesChannelContext->getCustomer()->getId(),
            "numberOfProcessingDays" => (int)max($numberOfProcessingDays),
            "deliveryDateOptions" => [
                "startDate" => date("Y-m-d"),
                "numberOfDays" => (int)$this->paazlConfiguration->getNumberOfDays(
                    $salesChannelId
                ),
            ],
            "language" => $this->getLocalName($languageId, $context),
            "currency" => $salesChannelContext->getCurrency()->getIsoCode(),
            "isPricingEnabled" => (bool)$this->paazlConfiguration->getIsPricingEnabled(
                $salesChannelId
            ),
            "isShowAsExtraCost" => (bool)$this->paazlConfiguration->getIsShowAsExtraCost(
                $salesChannelId
            ),
            "deliveryRangeFormat" => $this->paazlConfiguration->getDeliveryRangeFormat(
                $salesChannelId
            ),
            "deliveryOptionDateFormat" => $this->paazlConfiguration->getDeliveryOptionDateFormat(
                $salesChannelId
            ),
            "deliveryEstimateDateFormat" => $this->paazlConfiguration->getDeliveryEstimateDateFormat(
                $salesChannelId
            ),
            "pickupOptionDateFormat" => $this->paazlConfiguration->getPickupOptionDateFormat(
                $salesChannelId
            ),
            "pickupEstimateDateFormat" => $this->paazlConfiguration->getPickupEstimateDateFormat(
                $salesChannelId
            ),
            "sortingModel" => [
                "orderBy" => $this->paazlConfiguration->getOrderBy(
                    $salesChannelId
                ),
                "sortOrder" => $this->paazlConfiguration->getSortOrder(
                    $salesChannelId
                ),
            ],
            'price'  => isset($price) ? $price : $item->getPrice() ,
            "shipmentParameters" => [
                "totalWeight" => (float)array_sum($weight),
                "totalPrice" => isset($price) ? $price : $cart->getPrice()->getTotalPrice(),
                "numberOfGoods" => (int)array_sum($qty),
                "goods" => $goods,
            ],
            "shippingOptionsLimit" => (int)$this->paazlConfiguration->getShippingOptionsLimit(
                $salesChannelId
            ),
            "pickupLocationsPageLimit" => (int)$this->paazlConfiguration->getPickupLocationsPageLimit(
                $salesChannelId
            ),
            "pickupLocationsLimit" => (int)$this->paazlConfiguration->getPickupLocationsLimit(
                $salesChannelId
            ),
            "initialPickupLocations" => (int)$this->paazlConfiguration->getInitialPickupLocations(
                $salesChannelId
            ),
            "paazlOrderNumber" => $cart->getToken(),
        ];

        if ($this->paazlConfiguration->getFreeShipping($salesChannelId)
            === 'yes'
        ) {
            $paazlConfig['shipmentParameters']['startMatrix']
                = $this->paazlConfiguration->getStartMatrix($salesChannelId);
        }
        return $paazlConfig;
    }

    public function getPaazlCheckoutDetails(
        Cart $cart,
        SalesChannelContext $context
    ) {
        $salesChannelId = $context->getSalesChannelId();

        try {
            $response = $this->request('GET', $salesChannelId, 'checkout', [
                'query' => [
                    'reference' => $this->cartPaazlTokenService->getReference(
                        $cart->getToken()
                    ),
                ],
            ]);
        } catch (GuzzleException $e) {
            if ($this->paazlConfiguration->debug($salesChannelId)) {
                $this->paazlLogger->addEntry(
                    'Paazl getCheckout guzzleError :',
                    $context->getContext(),
                    null,
                    [$e->getMessage()]
                );
            }

            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        if ($response->getStatusCode() !== 200) {
            return ['type' => 'error', 'message' => 'API error'];
        }
        try {
            return json_decode(
                $response->getBody()->getContents(),
                true,
                512,
                \JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            if ($this->paazlConfiguration->debug($salesChannelId)) {
                $this->paazlLogger->addEntry(
                    'Paazl getCheckout jsonError :',
                    $context->getContext(),
                    $e
                );
            }

            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @throws JsonException
     */
    public function createPaazlOrder(CheckoutOrderPlacedEvent $event): array
    {
        $salesChannelId = $event->getSalesChannelId();
        $orderData = $this->getPayloadPaazl($event);

        try {
            $response = $this->request('POST', $salesChannelId, 'order', [
                'json' => $orderData,
            ]);
        } catch (GuzzleException $e) {
            if ($this->paazlConfiguration->debug($salesChannelId)) {
                $this->paazlLogger->addEntry(
                    'Paazl orderApi guzzleError :',
                    $event->getcontext(),
                    null,
                    [$e->getMessage()]
                );
            }

            $this->orderRepository->upsert([
                [
                    'id' => $event->getOrder()->getId(),
                    'customFields' => ['PaazlPost' => 'Unsuccessful'],
                ],
            ], $event->getContext());

            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        $this->cartPaazlTokenService->deleteReference(
            $event->getOrder()->getCustomFields()['paazlOrderReference']
        );

        if ($response->getStatusCode() !== 200) {
            return ['type' => 'error', 'message' => 'API error'];
        }

        $date = date("Y-m-d h:i:sa");
        $this->orderRepository->upsert([
            [
                'id' => $event->getOrder()->getId(),
                'customFields' => [
                    'PaazlPost' => 'Successfully Updated at ' . $date,
                ],
            ],
        ], $event->getContext());

        if (empty($response->getBody()->getContents())) {
            return [
                'type' => 'success',
                'message' => 'API Data Post SuccessFully',
            ];
        }

        return json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    /**
     * @throws JsonException
     */
    public function createPaazlPaidOrder($order, $context): array
    {
        $salesChannelId = $order->getOrder()->getSalesChannelId();
        $orderData = $this->getPayloadPaidPaazl($order, $context);
        try {
            $response = $this->request('POST', $salesChannelId, 'order', [
                'json' => $orderData,
            ]);
        } catch (GuzzleException $e) {
            if ($this->paazlConfiguration->debug($salesChannelId)) {
                $this->paazlLogger->addEntry(
                    'Paazl orderApi guzzleError :',
                    $context,
                    null,
                    [$e->getMessage()]
                );
            }

            $this->orderRepository->upsert([
                [
                    'id' => $order->getOrder()->getId(),
                    'customFields' => ['PaazlPost' => 'Unsuccessful'],
                ],
            ], $context);

            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        $this->cartPaazlTokenService->deleteReference(
            $order->getOrder()->getCustomFields()['paazlOrderReference']
        );

        if ($response->getStatusCode() !== 200) {
            return ['type' => 'error', 'message' => 'API error'];
        }

        $date = date("Y-m-d h:i:sa");
        $this->orderRepository->upsert([
            [
                'id' => $order->getOrder()->getId(),
                'customFields' => [
                    'PaazlPost' => 'Successfully Updated at ' . $date,
                ],
            ],
        ], $context);

        if (empty($response->getBody()->getContents())) {
            return [
                'type' => 'success',
                'message' => 'API Data Post SuccessFully',
            ];
        }

        return json_decode(
            $response->getBody()->getContents(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    private function getPayloadPaidPaazl($orderData, $context): array
    {
        $order = $orderData->getOrder();
        if (!$delivery = ($order->getDeliveries() ? $order->getDeliveries()
            ->first() : null)
        ) {
            return [];
        }

        if (!$shippingAddress = $delivery->getShippingOrderAddress()) {
            return [];
        }
        if (!$order->getOrderCustomer()) {
            return [];
        }

        $extInformation = null;
        $preferredDeliveryDate = null;
        $identifier = null;
        $pickupCode = null;
        $pickupAccountNumber = null;
        if (array_key_exists('paazlData', $order->getCustomFields())) {
            $extInformation = $order->getCustomFields()['paazlData'];

            if (array_key_exists('shippingOption', $extInformation)) {
                $shippingOption = $extInformation['shippingOption'];
                $preferredDeliveryDate
                    = $shippingOption['preferred_delivery_date'] ?? null;
                $identifier = $shippingOption['identifier'] ?? null;
            }

            $pickupCode = $extInformation['pickupLocation']['code'] ?? null;
            $pickupAccountNumber = $extInformation['pickup_account_number'] ??
                null;
        }

        $address = $this->addressParser->parseAddress(
            $shippingAddress->getStreet(),
            $order->getSalesChannelId()
        );

        $houseNr = $this->paazlConfiguration->getHouseNumberDefaultOption(
            $order->getSalesChannelId()
        );

        $getDefaultBillingAddressId = $order->getOrderCustomer()->getCustomer()->getDefaultBillingAddressId();
        $getDefaultShippingAddressId = $order->getOrderCustomer()->getCustomer()->getDefaultShippingAddressId();

        $allAddress = $order->getOrderCustomer()->getCustomer()->getAddresses();
        //$shippingAllAddress = $order->getOrderCustomer()->getCustomer()->getAddresses();
        $billingAddress = null;
        foreach ($allAddress as $allBillingAddress) {
            if ($getDefaultBillingAddressId == $allBillingAddress->getId()) {
                $billingAddress = $allBillingAddress;
            }
        }

        $shippingAddress = null;
        foreach ($allAddress as $allShippingAddress) {
            if ($getDefaultShippingAddressId == $allShippingAddress->getId()) {
                $shippingAddress = $allShippingAddress;
            }
        }

        $shippingOptions = null;
        if ($extInformation['deliveryType'] === 'PICKUP_LOCATION') {
            $shippingOptions = $extInformation['shippingOption'];
        }

        $shippingConsigeeAddress = $this->addressParser->parseAddress(
            $shippingAddress->getStreet(),
            $order->getSalesChannelId()
        );
        $consineeAddress = [
            'city' => $shippingAddress->getCity(),
            'country' => $shippingAddress->getCountry()->getIso(),
            'postalCode' => $shippingAddress->getZipcode(),
            'street' => $shippingConsigeeAddress['street'] ?? '',
            'houseNumber' => $shippingConsigeeAddress['houseNumber'] ?? '',
            'houseNumberExtension' => $shippingConsigeeAddress['houseNumberExtension'] ?? '',
        ];

        $currency = $order->getCurrency() ? $order->getCurrency()->getIsoCode()
            : 'EUR';

        $result = [
            'additionalInstruction' => '',
            "deliveryType" => $extInformation['deliveryType'],
            'consignee' => [
                'email' => $order->getOrderCustomer()->getEmail(),
                'name' => $shippingAddress->getFirstName() . " "
                    . $shippingAddress->getLastName(),
                'address' => $consineeAddress,
            ],
            'shippingOptions' => $shippingOptions,
            'customsValue' => [
                'currency' => $currency,
                'value' => $order->getAmountTotal(),
            ],
            'codValue' => [
                'currency' => $currency,
                'value' => $order->getAmountTotal(),
            ],
            'insuredValue' => [
                'currency' => $currency,
                'value' => $this->paazlConfiguration->getInsuranceValue(
                    $order->getSalesChannelId()
                ),
            ],
            'requestedDeliveryDate' => $preferredDeliveryDate,
            'products' => [],
            'reference' => $order->getOrderNumber(),
            'invoiceNumber' => $order->getOrderNumber(),
            'shipping' => [
                'option' => $identifier,
            ],
        ];

        if ($billingAddress->getCountryState()) {
            $result['consignee']['address']['province']
                = $billingAddress->getCountryState();
        }

        if ($billingAddress->getCompany()) {
            $result['consignee']['companyName'] = $billingAddress->getCompany(
            );
        }

        if ($billingAddress->getPhoneNumber()) {
            $result['consignee']['phone'] = $billingAddress->getPhoneNumber();
        }

        if (array_key_exists('deliveryType', $extInformation)
            && $extInformation['deliveryType'] === 'PICKUP_LOCATION'
        ) {
            $result['shipping']['pickupLocation'] = [
                'code' => $pickupCode,
            ];
            if ($pickupAccountNumber) {
                $result['shipping']['pickupLocation']['accountNumber']
                    = $pickupAccountNumber;
            }
        }

        if (array_key_exists('paazlProducts', $order->getcustomFields())) {
            $result['weight'] = array_sum(
                $order->getcustomFields()['paazlProducts']['totalWeight']
            );
            $result['products'] = $order->getcustomFields(
            )['paazlProducts']['lineItem'];
        }
        /**
         * Added fallback to streetLines if no address could be parsed.
         */
        if (empty($result['consignee']['address']['street'])) {
            $result['consignee']['address']['streetLines']
                = [$shippingAddress->getStreet()];
            unset(
                $result['consignee']['address']['street'],
                $result['consignee']['address']['houseNumber'],
                $result['consignee']['address']['houseNumberExtension']
            );
        }
        if ($this->paazlConfiguration->debug($order->getSalesChannelId())) {
            $this->paazlLogger->addEntry(
                'Paazl OrderApi Payload :',
                $context,
                null,
                [$result]
            );
        }
        return $result;
    }
    private function getPayloadPaazl(CheckoutOrderPlacedEvent $event): array
    {
        $order = $event->getOrder();
        if (!$delivery = ($order->getDeliveries() ? $order->getDeliveries()
            ->first() : null)
        ) {
            return [];
        }

        if (!$shippingAddress = $delivery->getShippingOrderAddress()) {
            return [];
        }
        if (!$order->getOrderCustomer()) {
            return [];
        }

        $extInformation = null;
        $preferredDeliveryDate = null;
        $identifier = null;
        $pickupCode = null;
        $pickupAccountNumber = null;
        if (array_key_exists('paazlData', $order->getCustomFields())) {
            $extInformation = $order->getCustomFields()['paazlData'];

            if (array_key_exists('shippingOption', $extInformation)) {
                $shippingOption = $extInformation['shippingOption'];
                $preferredDeliveryDate
                    = $shippingOption['preferred_delivery_date'] ?? null;
                $identifier = $shippingOption['identifier'] ?? null;
            }

            $pickupCode = $extInformation['pickupLocation']['code'] ?? null;
            $pickupAccountNumber = $extInformation['pickup_account_number'] ?? null;
        }

        $address = $this->addressParser->parseAddress(
            $shippingAddress->getStreet(),
            $event->getSalesChannelId()
        );

        $houseNr = $this->paazlConfiguration->getHouseNumberDefaultOption(
            $event->getSalesChannelId()
        );

        $getDefaultBillingAddressId = $event->getOrder()->getOrderCustomer()->getCustomer()->getDefaultBillingAddressId();
        $getDefaultShippingAddressId = $event->getOrder()->getOrderCustomer()->getCustomer()->getDefaultShippingAddressId();
        $shippingOptions = null;
        $allAddress = $order->getOrderCustomer()->getCustomer()->getAddresses();
        //$shippingAllAddress = $order->getOrderCustomer()->getCustomer()->getAddresses();
        $billingAddress = null;
        foreach ($allAddress as $allBillingAddress) {
            if ($getDefaultBillingAddressId == $allBillingAddress->getId()) {
                $billingAddress = $allBillingAddress;
            }
        }

        $shippingAddress = null;
        foreach ($allAddress as $allShippingAddress) {
            if ($getDefaultShippingAddressId == $allShippingAddress->getId()) {
                $shippingAddress = $allShippingAddress;
            }
        }

        if ($extInformation['deliveryType'] === 'PICKUP_LOCATION') {
            $shippingOptions = $extInformation['shippingOption'];
        }
        $shippingConsigeeAddress = $this->addressParser->parseAddress(
            $shippingAddress->getStreet(),
            $event->getSalesChannelId()
        );
        $consineeAddress = [
            'city' => $shippingAddress->getCity(),
            'country' => $shippingAddress->getCountry()->getIso(),
            'postalCode' => $shippingAddress->getZipcode(),
            'street' => $shippingConsigeeAddress['street'] ?? '',
            'houseNumber' => $shippingConsigeeAddress['houseNumber'] ?? '',
            'houseNumberExtension' => $shippingConsigeeAddress['houseNumberExtension'] ?? '',
        ];
        $currency = $order->getCurrency() ? $order->getCurrency()->getIsoCode()
            : 'EUR';

        $result = [
            'additionalInstruction' => '',
            "deliveryType" => $extInformation['deliveryType'],
            'consignee' => [
                'email' => $order->getOrderCustomer()->getEmail(),
                'name' => $shippingAddress->getFirstName() . " "
                    . $shippingAddress->getLastName(),
                'address' => $consineeAddress,
            ],
            'shippingOptions' => $shippingOptions,
            'customsValue' => [
                'currency' => $currency,
                'value' => $order->getAmountTotal(),
            ],
            'codValue' => [
                'currency' => $currency,
                'value' => $order->getAmountTotal(),
            ],
            'insuredValue' => [
                'currency' => $currency,
                'value' => $this->paazlConfiguration->getInsuranceValue(
                    $event->getSalesChannelId()
                ),
            ],
            'requestedDeliveryDate' => $preferredDeliveryDate,
            'products' => [],
            'reference' => $order->getOrderNumber(),
            'invoiceNumber' => $order->getOrderNumber(),
            'shipping' => [
                'option' => $identifier,
            ],
        ];

        if ($billingAddress->getCountryState()) {
            $result['consignee']['address']['province']
                = $billingAddress->getCountryState();
        }

        if ($billingAddress->getCompany()) {
            $result['consignee']['companyName'] = $billingAddress->getCompany();
        }

        if ($billingAddress->getPhoneNumber()) {
            $result['consignee']['phone'] = $billingAddress->getPhoneNumber();
        }

        if (array_key_exists('deliveryType', $extInformation)
            && $extInformation['deliveryType'] === 'PICKUP_LOCATION'
        ) {
            $result['shipping']['pickupLocation'] = [
                'code' => $pickupCode,
            ];
            if ($pickupAccountNumber) {
                $result['shipping']['pickupLocation']['accountNumber']
                    = $pickupAccountNumber;
            }
        }

        if (array_key_exists('paazlProducts', $order->getcustomFields())) {
            $result['weight'] = array_sum(
                $order->getcustomFields()['paazlProducts']['totalWeight']
            );
            $result['products'] = $order->getcustomFields(
            )['paazlProducts']['lineItem'];
        }
        /**
         * Added fallback to streetLines if no address could be parsed.
         */
        if (empty($result['consignee']['address']['street'])) {
            $result['consignee']['address']['streetLines']
                = [$shippingAddress->getStreet()];
            unset(
                $result['consignee']['address']['street'],
                $result['consignee']['address']['houseNumber'],
                $result['consignee']['address']['houseNumberExtension']
            );
        }
        if ($this->paazlConfiguration->debug($event->getSalesChannelId())) {
            $this->paazlLogger->addEntry(
                'Paazl OrderApi Payload :',
                $event->getcontext(),
                null,
                [$result]
            );
        }
        return $result;
    }

    private function getCountryIso($countryId, $context): string
    {
        $country = $this->countryRepository->search(
            new Criteria([$countryId]),
            $context
        )->first();

        return $country->getIso();
    }

    private function getLocalName($languageId, $context): string
    {
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        $languageData = $this->languageRepository->search($criteria, $context)
            ->first();

        if (!$languageData) {
            return 'eng';
        }

        $locale = explode('-', $languageData->getLocale()->getCode())[0];

        if ($locale === 'en') {
            return 'eng';
        }

        return $locale;
    }

    private function getOrderNumber(): int
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::DESCENDING));
        $orderData = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();

        if (!$orderData) {
            return 1;
        }

        return $orderData->getOrderNumber() + 1;
    }

    /*private function getOrderNumber(Context $context): int
    {
        $criteria = (new Criteria())
            ->addSorting(
                new FieldSorting('autoIncrement', FieldSorting::DESCENDING)
            )
            ->setLimit(1);

        $orderData = $this->orderRepository->search(
            $criteria,
            $context
        )->first();

        if (!$orderData) {
            return 1;
        }

        return $orderData->getOrderNumber() + 1;
    }*/
}
