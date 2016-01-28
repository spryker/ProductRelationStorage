<?php
/**
 * (c) Spryker Systems GmbH copyright protected
 */

namespace Spryker\Zed\ProductOptionDiscountConnector\Business\Model\OrderAmountAggregator;

use Generated\Shared\Transfer\CalculatedDiscountTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Orm\Zed\Sales\Persistence\Map\SpySalesDiscountTableMap;
use Orm\Zed\Sales\Persistence\SpySalesDiscount;
use Propel\Runtime\Collection\ObjectCollection;
use Spryker\Zed\Discount\Persistence\DiscountQueryContainerInterface;

class ProductOptionDiscounts implements OrderAmountAggregatorInterface
{
    /**
     * @var DiscountQueryContainerInterface
     */
    protected $discountQueryContainer;

    /**
     * ItemDiscountAmounts constructor.
     */
    public function __construct(DiscountQueryContainerInterface $discountQueryContainer)
    {
        $this->discountQueryContainer = $discountQueryContainer;
    }

    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return void
     */
    public function aggregate(OrderTransfer $orderTransfer)
    {
        $salesOrderDiscounts = $this->getSalesOrderDiscounts($orderTransfer);

        if (count($salesOrderDiscounts) === 0) {
            $this->addProductOptionWithDiscountsGrossPriceAmountDefaults($orderTransfer);
            return;
        }

        $this->populateProductOptionDiscountsFromSalesOrderDiscounts($orderTransfer, $salesOrderDiscounts);
        $this->addProductOptionWithDiscountsGrossPriceAmounts($orderTransfer);
    }

    /**
     * @param OrderTransfer $orderTransfer
     * @param ObjectCollection|SpySalesDiscount[] $salesOrderDiscounts
     *
     * @return void
     */
    protected function populateProductOptionDiscountsFromSalesOrderDiscounts(
        OrderTransfer $orderTransfer,
        ObjectCollection $salesOrderDiscounts
    ) {
        foreach ($salesOrderDiscounts as $salesOrderDiscountEntity) {
            foreach ($orderTransfer->getItems() as $itemTransfer) {
                $this->addProductOptionCalculatedDiscounts($itemTransfer, $salesOrderDiscountEntity);
            }
        }
    }

    /**
     * @param ItemTransfer $itemTransfer
     * @param SpySalesDiscount $salesOrderDiscountEntity
     *
     * @return void
     */
    protected function addProductOptionCalculatedDiscounts(
        ItemTransfer $itemTransfer,
        SpySalesDiscount $salesOrderDiscountEntity
    ) {

        foreach ($itemTransfer->getProductOptions() as $productOptionTransfer) {
            if ($salesOrderDiscountEntity->getFkSalesOrderItemOption() !== $productOptionTransfer->getIdSalesOrderItemOption()) {
                continue;
            }

            $calculatedDiscountTransfer = $this->hydrateCalculatedDiscountTransferFromEntity(
                $salesOrderDiscountEntity,
                $productOptionTransfer->getQuantity()
            );

            $productOptionTransfer->addCalculatedDiscount($calculatedDiscountTransfer);
        }
    }

    /**
     * @param SpySalesDiscount $salesOrderDiscountEntity
     * @param int $quantity
     *
     * @return CalculatedDiscountTransfer
     */
    protected function hydrateCalculatedDiscountTransferFromEntity(SpySalesDiscount $salesOrderDiscountEntity, $quantity)
    {
        $quantity = !empty($quantity) ? $quantity : 1;

        $calculatedDiscountTransfer = new CalculatedDiscountTransfer();
        $calculatedDiscountTransfer->fromArray($salesOrderDiscountEntity->toArray(), true);
        $calculatedDiscountTransfer->setQuantity($quantity);
        $calculatedDiscountTransfer->setUnitGrossAmount($salesOrderDiscountEntity->getAmount());
        $calculatedDiscountTransfer->setSumGrossAmount($salesOrderDiscountEntity->getAmount() * $quantity);

        foreach ($salesOrderDiscountEntity->getDiscountCodes() as $discountCodeEntity) {
            $calculatedDiscountTransfer->setVoucherCode($discountCodeEntity->getCode());
        }

        return $calculatedDiscountTransfer;
    }

    /**
     * @param \ArrayObject|CalculatedDiscountTransfer[] $calculatedDiscounts
     *
     * @return int
     */
    protected function getCalculatedDiscountSumGrossAmount(\ArrayObject $calculatedDiscounts)
    {
        $totalSumGrossDiscountAmount = 0;
        foreach ($calculatedDiscounts as $calculatedDiscountTransfer) {
            $totalSumGrossDiscountAmount += $calculatedDiscountTransfer->getSumGrossAmount();
        }

        return $totalSumGrossDiscountAmount;
    }

    /**
     * @param \ArrayObject|CalculatedDiscountTransfer[] $calculatedDiscounts
     *
     * @return int
     */
    protected function getCalculatedDiscountUnitGrossAmount(\ArrayObject $calculatedDiscounts)
    {
        $totalUnitGrossDiscountAmount = 0;
        foreach ($calculatedDiscounts as $calculatedDiscountTransfer) {
            $totalUnitGrossDiscountAmount += $calculatedDiscountTransfer->getUnitGrossAmount();
        }

        return $totalUnitGrossDiscountAmount;
    }

    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return void
     */
    protected function addProductOptionWithDiscountsGrossPriceAmounts(OrderTransfer $orderTransfer)
    {
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            $totalItemUnitDiscountAmount = $this->getCalculatedDiscountUnitGrossAmount($itemTransfer->getCalculatedDiscounts());
            $totalItemSumDiscountAmount = $this->getCalculatedDiscountSumGrossAmount($itemTransfer->getCalculatedDiscounts());

            list($productOptionUnitAmount, $productOptionSumAmount) = $this->getProductOptionCalculatedDiscounts($itemTransfer);

            $itemTransfer->setUnitGrossPriceWithProductOptionAndDiscountAmounts(
                $itemTransfer->getUnitGrossPriceWithProductOptions() - $totalItemUnitDiscountAmount - $productOptionUnitAmount
            );
            $itemTransfer->setSumGrossPriceWithProductOptionAndDiscountAmounts(
                $itemTransfer->getSumGrossPriceWithProductOptions() - $totalItemSumDiscountAmount - $productOptionSumAmount
            );

            $itemTransfer->setRefundableAmount(
                $itemTransfer->getRefundableAmount() - $totalItemSumDiscountAmount - $productOptionSumAmount
            );
        }
    }

    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return void
     */
    protected function addProductOptionWithDiscountsGrossPriceAmountDefaults(OrderTransfer $orderTransfer)
    {
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            $itemTransfer->setUnitGrossPriceWithProductOptionAndDiscountAmounts(
                $itemTransfer->getUnitGrossPriceWithProductOptions()
            );

            $itemTransfer->setSumGrossPriceWithProductOptionAndDiscountAmounts(
                $itemTransfer->getSumGrossPriceWithProductOptions()
            );
        }
    }

    /**
     * @param ItemTransfer $itemTransfer
     *
     * @return array
     */
    protected function getProductOptionCalculatedDiscounts(ItemTransfer $itemTransfer)
    {
        $productOptionUnitAmount = 0;
        $productOptionSumAmount = 0;

        foreach ($itemTransfer->getProductOptions() as $productOptionTransfer) {
            $productOptionUnitAmount += $this->getCalculatedDiscountUnitGrossAmount(
                $productOptionTransfer->getCalculatedDiscounts()
            );

            $productOptionSumAmount += $this->getCalculatedDiscountSumGrossAmount(
                $productOptionTransfer->getCalculatedDiscounts()
            );
        }

        return array($productOptionUnitAmount, $productOptionSumAmount);
    }

    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return SpySalesDiscount[]|ObjectCollection
     */
    protected function getSalesOrderDiscounts(OrderTransfer $orderTransfer)
    {
        return $this->discountQueryContainer
            ->querySalesDisount()
            ->filterByFkSalesOrder($orderTransfer->getIdSalesOrder())
            ->where(SpySalesDiscountTableMap::COL_FK_SALES_ORDER_ITEM_OPTION . ' IS NOT NULL')
            ->find();
    }


}
