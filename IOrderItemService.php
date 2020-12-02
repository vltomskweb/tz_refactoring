<?php

namespace App\Order;

use App\Admin\Frontend\Importer\OrderItemToSave;
use App\Admin\Frontend\Importer\OrderToSave;

/**
 * Сервис управления позициями заказа.
 */
interface IOrderItemService
{
    /**
     * Добавит позицию в заказ.
     *
     * @param Order $order
     * @param int $goodPriceId
     * @param int $quantity
     * @param float $price
     * @param string $lang
     *
     * @throws \LogicException
     */
    public function addItemToOrder(Order $order, $goodPriceId, $quantity, $price, $lang);

    /**
     * Обновляет данные позиции в заказе.
     *
     * @param OrderItemToSave $itemToSave
     * @param OrderToSave $orderToSave
     */
    public function updateItem(OrderItemToSave $itemToSave, OrderToSave $orderToSave);

    /**
     * Билдит объект OrderItem по goodPriceId.
     *
     * @param Order $order
     * @param int $goodPriceId
     * @param int $quantity
     * @param string $lang
     * @return OrderItem
     */
    public function buildOrderItemByGoodPriceId(Order $order, $goodPriceId, $quantity, $lang);

    /**
     * Считает итоговую сумму заказа.
     *
     * @param OrderItemToSave[] $itemsToSave
     * @param int $currencyId
     * @return float
     */
    public function countTotalByItemsToSave($itemsToSave, $currencyId);
}
