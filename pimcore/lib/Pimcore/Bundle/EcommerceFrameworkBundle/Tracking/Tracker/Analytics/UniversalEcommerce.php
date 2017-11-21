<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\Tracker\Analytics;

use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\ICheckoutComplete;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\ProductAction;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\Tracker;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\Transaction;
use Pimcore\Google\Analytics;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UniversalEcommerce extends Tracker implements ICheckoutComplete
{
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'template_prefix' => 'PimcoreEcommerceFrameworkBundle:Tracking/analytics/universal'
        ]);
    }

    /**
     * Track checkout complete
     *
     * @param AbstractOrder $order
     */
    public function trackCheckoutComplete(AbstractOrder $order)
    {
        $transaction = $this->trackingItemBuilder->buildCheckoutTransaction($order);
        $items       = $this->trackingItemBuilder->buildCheckoutItems($order);

        $parameters = [];
        $parameters['transaction'] = $transaction;
        $parameters['items'] = $items;
        $parameters['calls'] = $this->buildCheckoutCompleteCalls($transaction, $items);

        $result = $this->renderTemplate('checkout_complete', $parameters);

        Analytics::addAdditionalCode($result, 'beforeEnd');
    }

    /**
     * @param Transaction $transaction
     * @param ProductAction[] $items
     *
     * @return array
     */
    protected function buildCheckoutCompleteCalls(Transaction $transaction, array $items)
    {
        $calls = [
            'ecommerce:addTransaction' => [
                $this->transformTransaction($transaction)
            ],
            'ecommerce:addItem' => []
        ];

        foreach ($items as $item) {
            $calls['ecommerce:addItem'][] = $this->transformProductAction($item);
        }

        return $calls;
    }

    /**
     * Transform transaction into universal data object
     *
     * @param Transaction $transaction
     *
     * @return array
     */
    protected function transformTransaction(Transaction $transaction)
    {
        return $this->filterNullValues([
            'id'          => $transaction->getId(),                     // Transaction ID. Required.
            'affiliation' => $transaction->getAffiliation() ?: '',      // Affiliation or store name.
            'revenue'     => $transaction->getTotal(),                  // Grand Total.
            'shipping'    => $transaction->getShipping(),               // Shipping.
            'tax'         => $transaction->getTax()                     // Tax.
        ]);
    }

    /**
     * Transform product action into universal data object
     *
     * @param ProductAction $item
     *
     * @return array
     */
    protected function transformProductAction(ProductAction $item)
    {
        return $this->filterNullValues([
            'id'       => $item->getTransactionId(),                    // Transaction ID. Required.
            'sku'      => $item->getId(),                               // SKU/code.
            'name'     => $item->getName(),                             // Product name. Required.
            'category' => $item->getCategory(),                         // Category or variation.
            'price'    => $item->getPrice(),                            // Unit price.
            'quantity' => $item->getQuantity() ?: 1,                    // Quantity.
        ]);
    }
}