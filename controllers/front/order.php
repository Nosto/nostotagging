<?php
/**
 * 2013-2015 Nosto Solutions Ltd
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
 * @copyright 2013-2015 Nosto Solutions Ltd
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once(dirname(__FILE__).'/api.php');

/**
 * Front controller for gathering all existing orders from the shop and sending the meta-data to Nosto.
 *
 * This controller should only be invoked once, when the Nosto module has been installed.
 */
class NostoTaggingOrderModuleFrontController extends NostoTaggingApiModuleFrontController
{
	/**
	 * @inheritdoc
	 */
	public function initContent()
	{
		$collection = new NostoExportCollectionOrder();
		foreach ($this->fetchOrderIds() as $id_order)
		{
			$order = new Order($id_order);
			if (!Validate::isLoadedObject($order))
				continue;

			try {
				$nosto_order = new NostoTaggingOrder();
				$nosto_order->excludeSpecialItems();
				$nosto_order->loadData($order);
				$collection[] = $nosto_order;
			} catch (NostoException $e) {
				continue;
			}
		}

		$this->encryptOutput($collection);
	}

	/**
	 * Returns a list of all order ids with limit and offset applied.
	 *
	 * @return array the order id list.
	 */
	protected function fetchOrderIds()
	{
		$context = $this->module->getContext();
		if (_PS_VERSION_ > '1.5')
			$where = strtr('`id_shop_group` = {g} AND `id_shop` = {s} AND `id_lang` = {l}',
				array(
					'{g}' => $this->sanitizeValue($context->shop->id_shop_group),
					'{s}' => $this->sanitizeValue($context->shop->id),
					'{l}' => $this->sanitizeValue($context->language->id),
				));
		else
			$where = strtr('`id_lang` = {l}',
				array(
					'{l}' => $this->sanitizeValue($context->language->id),
				));

	 	$sort = '`date_add` DESC';

		if (!empty($ids = $this->getIds()) && count($this->getIds() > 0)) {
			$ids = array_map(
				function ($val) {
					return '\'' . $this->sanitizeValue($val) . '\'';
				},
				$ids
			);

			$where .= sprintf(
				' AND `reference` IN(%s)',
				implode(',', $ids)
			);
		}

		if (!empty($this->getId())) {
			$where .= sprintf(
				' AND `reference` = \'%s\'',
				$this->sanitizeValue($this->getId())
			);
		}

		$sql = <<<EOT
			SELECT `id_order`
			FROM `ps_orders`
			WHERE $where
			ORDER BY $sort
			LIMIT $this->limit
			OFFSET $this->offset
EOT;
		$rows = Db::getInstance()->executeS($sql);
		$order_ids = array();
		foreach ($rows as $row)
			$order_ids[] = (int)$row['id_order'];
		return $order_ids;
	}
}
