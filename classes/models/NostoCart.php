<?php
/**
 * 2013-2016 Nosto Solutions Ltd
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@nosto.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2013-2016 Nosto Solutions Ltd
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use \Nosto\Object\Cart\Cart as NostoSDKCart;
use \Nosto\Object\Cart\LineItem as NostoSDKCartItem;

class NostoCart extends NostoSDKCart
{
    /**
     * @param $idCurrency
     * @return Currency
     * @suppress PhanTypeMismatchArgument
     */
    private static function loadCurrency($idCurrency)
    {
        return new Currency($idCurrency);
    }

    /**
     * Loads the cart data from supplied cart object.
     *
     * @param Cart $cart the cart object.
     * @return NostoCart
     */
    public static function loadData(Cart $cart)
    {
        if (!Validate::isLoadedObject($cart) || ($products = $cart->getProducts()) === array()) {
            return null;
        }

        $currency = self::loadCurrency($cart->id_currency);
        if (!Validate::isLoadedObject($currency)) {
            return null;
        }

        $nostoCart = new NostoCart();
        $cartRules = (array)$cart->getCartRules(CartRule::FILTER_ACTION_GIFT);

        $gift_products = array();
        foreach ($cartRules as $cartRule) {
            if ((int)$cartRule['gift_product']) {
                foreach ($products as $key => &$product) {
                    if (empty($product['gift'])
                        && (int)$product['id_product'] === (int)$cartRule['gift_product']
                        && (int)$product['id_product_attribute'] === (int)$cartRule['gift_product_attribute']
                    ) {
                        $product['cart_quantity'] = (int)$product['cart_quantity'];
                        $product['cart_quantity']--;

                        if (!($product['cart_quantity'] > 0)) {
                            unset($products[$key]);
                        }

                        $giftProduct = $product;
                        $giftProduct['cart_quantity'] = 1;
                        $giftProduct['price_wt'] = 0;
                        $giftProduct['gift'] = true;

                        $gift_products[] = $giftProduct;

                        break; // One gift product per cart rule
                    }
                }
                unset($product);
            }
        }

        $items = array_merge($products, $gift_products);

        foreach ($items as $item) {
            $name = $item['name'];
            if (isset($item['attributes_small'])) {
                $name .= ' (' . $item['attributes_small'] . ')';
            }

            $nostoLineItem = new NostoSDKCartItem();
            $nostoLineItem->setProductId($item['id_product']);
            $nostoLineItem->setQuantity((int)$item['cart_quantity']);
            $nostoLineItem->setName((string)$name);
            $nostoLineItem->setPrice($item['price_wt']);
            $nostoLineItem->setPriceCurrencyCode((string)$currency->iso_code);
            $nostoCart->addItem($nostoLineItem);
        }

        return $nostoCart;
    }
}