<?php

namespace App\Order;

use App\Admin\Frontend\Importer\OrderItemToSave;
use App\Admin\Frontend\Importer\OrderToSave;
use App\Currency\ICurrencyService;
use App\Good\Attribute\IGoodAttributeService;
use App\Good\GoodNameUtils;
use App\Good\IGoodProvider;
use App\Localization\Language;
use App\Pricing\IPricingService;
use App\Search\ISearchProvider;

/**
 * Сервис управления позициями заказа.
 */
class OrderItemService implements IOrderItemService
{
    /**
     * @var IOrderItemProvider
     */
    private $orderItemProvider;
    /**
     * @var ISearchProvider
     */
    private $searchProvider;
    /**
     * @var IGoodAttributeService
     */
    private $goodsAttributeService;
    /**
     * @var IPricingService
     */
    private $pricingService;
    /**
     * @var IGoodProvider
     */
    private $goodProvider;
    /**
     * @var ICurrencyService
     */
    private $currencyService;

    /**
     * @param IOrderItemProvider $orderItemProvider
     * @param ISearchProvider $searchProvider
     * @param IGoodAttributeService $goodsAttributeService
     * @param IPricingService $pricingService
     * @param IGoodProvider $goodProvider
     * @param ICurrencyService $currencyService
     */
    public function __construct(
        IOrderItemProvider $orderItemProvider,
        ISearchProvider $searchProvider,
        IGoodAttributeService $goodsAttributeService,
        IPricingService $pricingService,
        IGoodProvider $goodProvider,
        ICurrencyService $currencyService
    ) {
        $this->orderItemProvider = $orderItemProvider;
        $this->searchProvider = $searchProvider;
        $this->goodsAttributeService = $goodsAttributeService;
        $this->pricingService = $pricingService;
        $this->goodProvider = $goodProvider;
        $this->currencyService = $currencyService;
    }

    /**
     * Добавит позицию в заказ.
     *
     * @inheritdoc
     */
    public function addItemToOrder(Order $order, $goodPriceId, $quantity, $price, $lang)
    {
        $orderItem = OrderItem::build()
            ->assignFrom($this->buildOrderItemByGoodPriceId($order, $goodPriceId, $quantity, $lang))
            ->setPrice($price)
            ->create();

        $this->orderItemProvider->createNewOrderItem($orderItem);
    }

    /**
     * Обновляет данные позиции в заказе.
     *
     * @param OrderItemToSave $itemToSave
     * @param OrderToSave $orderToSave
     */
    public function updateItem(OrderItemToSave $itemToSave, OrderToSave $orderToSave)
    {
        if (! $itemToSave->getId()) {
            throw new \LogicException('No ID is given');
        }

        $currentOrderItem = $this->orderItemProvider->findOrderItemById($itemToSave->getId());
        if (!$currentOrderItem) {
            throw new \LogicException('Order item was not found');
        }

        $orderItem = OrderItem::build()
            ->assignFrom($currentOrderItem)
            ->setStatusId($itemToSave->getStatusId())
            ->setPrice($itemToSave->getPrice())
            ->setQuantityFinal($itemToSave->getQuantity())
            ->setReplacementGoodId($itemToSave->getReplacementGoodId())
            ->setCalcWeight($itemToSave->getWeightCalc())
            ->create();

        $this->orderItemProvider->updateItem($orderItem);
    }

    /**
     * Билдит объект OrderItem по goodPriceId.
     *
     * @inheritdoc
     */
    public function buildOrderItemByGoodPriceId(Order $order, $goodPriceId, $quantity, $lang)
    {
        $goodsData = $this->searchProvider->getDetailGoodsPricesData([$goodPriceId], $lang);
        $data = $goodsData ? reset($goodsData) : null;

        $goodId = $data->good_id;
        $price = 0;
        $priceWithoutDiscount = 0;
        try {
            $prices = $this->pricingService->getGoodsPricesWithDiscounts(
                [$goodPriceId],
                $order->getCurrencyId(),
                $order->getCustomerId(),
                $order->getShippingAddress() ? $order->getShippingAddress()->getRegionId() : 0
            );

            list($price, $priceWithoutDiscount) = reset($prices);
        } catch (\Exception $e) {}

        $goodAttributes = $this->goodsAttributeService->getAttributes($goodId, $lang);
        $titleEn = GoodNameUtils::getGoodName($data->name_en, $goodAttributes);
        $titleRu = GoodNameUtils::getGoodName($data->name_ru, $goodAttributes);
        $title = ($lang == Language::RUSSIAN && $titleRu) ? $titleRu : $titleEn;

        $partNumber = $data->catalog_num;

        return OrderItem::build()
            ->setGoodId($goodId)
            ->setPartNumber($partNumber)
            ->setName(
                $this->goodProvider->getGoodManufacturerNameById($goodId) . ' ' .
                $partNumber .
                ($title ? ' - ' . $title : '')
            )
            ->setPrice($price)
            ->setPriceNoDiscount($priceWithoutDiscount)
            ->setSiteId($data->site_id)
            ->setCustomerId($order->getCustomerId())
            ->setQuantityInit($quantity)
            ->setQuantityFinal($quantity)
            ->setDeliveryId($data->delivery_id)
            ->setOrderId($order->getId())
            ->setOrderSeqId($order->getSeqId())
            ->setStatusId($order->getStatusId())
            ->setGoodPriceId($goodPriceId)
            ->create();
    }

    /**
     * Считает итоговую сумму заказа.
     *
     * @param OrderItemToSave[] $itemsToSave
     * @param int $currencyId
     * @return float
     */
    public function countTotalByItemsToSave($itemsToSave, $currencyId)
    {
        if (empty($itemsToSave)) {
            return 0.0;
        }

        $total = 0;
        foreach ($itemsToSave as $itemToSave) {
            if ($itemToSave->isCanceled()) {
                continue;
            }

            $total += $itemToSave->getPrice() * $itemToSave->getQuantity();
        }

        return $this->currencyService->roundCurrency($total, $currencyId);
    }
}
