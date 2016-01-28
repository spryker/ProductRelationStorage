<?php
/**
 * (c) Spryker Systems GmbH copyright protected
 */

namespace Spryker\Zed\ProductOptionDiscountConnector\Business\Model\OrderAmountAggregator;

use Generated\Shared\Transfer\CalculatedDiscountTransfer;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\OrderTransfer;

class DiscountTotalAmount implements OrderAmountAggregatorInterface
{
    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return void
     */
    public function aggregate(OrderTransfer $orderTransfer)
    {
        $this->assertDisountTotalRequirements($orderTransfer);

        $orderTransfer->getTotals()->setDiscountTotal(
            $this->getTotalDiscountAmountWithProductOptions($orderTransfer)
        );
    }

    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return int
     */
    protected function getTotalDiscountAmountWithProductOptions(OrderTransfer $orderTransfer)
    {
        $currentTotalDiscountAmount = $orderTransfer->getTotals()->getDiscountTotal();
        $discountTotalAmountForProductOptions = $this->getSumTotalGrossDiscountAmount($orderTransfer);

        return $currentTotalDiscountAmount + $discountTotalAmountForProductOptions;
    }

    /**
     * @param OrderTransfer $orderTransfer
     *
     * @return int
     */
    protected function getSumTotalGrossDiscountAmount(OrderTransfer $orderTransfer)
    {
        $totalSumGrossDiscountAmount = 0;
        foreach ($orderTransfer->getItems() as $itemTransfer) {
            $totalSumGrossDiscountAmount += $this->getProductOptionCalculatedDiscounts($itemTransfer);
        }

        return $totalSumGrossDiscountAmount;
    }

    /**
     * @param OrderTransfer $orderTransfer
     */
    protected function assertDisountTotalRequirements(OrderTransfer $orderTransfer)
    {
        $orderTransfer->requireTotals();
    }

    /**
     * @param ItemTransfer $itemTransfer
     *
     * @return int
     */
    protected function getProductOptionCalculatedDiscounts(ItemTransfer $itemTransfer)
    {
        $productOptionSumAmount = 0;
        foreach ($itemTransfer->getProductOptions() as $productOptionTransfer) {
            $productOptionSumAmount += $this->getCalculatedDiscountSumGrossAmount(
                $productOptionTransfer->getCalculatedDiscounts()
            );
        }

        return $productOptionSumAmount;
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

}
