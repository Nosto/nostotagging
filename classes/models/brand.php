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

/**
 * Model for tagging manufacturers (brands).
 */
class NostoTaggingBrand extends NostoTaggingModel
{
	/**
	 * @var string the built brand string.
	 */
	protected $brand;

	/**
	 * Sets up this DTO.
	 *
	 * @param Manufacturer|ManufacturerCore $manufacturer the PS manufacturer model.
	 */
	public function loadData(Manufacturer $manufacturer)
	{
		if (!Validate::isLoadedObject($manufacturer))
			return;

		$this->brand = DS.$manufacturer->name;

		$this->dispatchHookActionObjectLoadAfter(array(
			'nosto_brand' => $this,
			'manufacturer' => $manufacturer,
			'context' => Context::getContext()
		));
	}

	/**
	 * Returns the brand value.
	 *
	 * @return string the brand.
	 */
	public function getBrand()
	{
		return $this->brand;
	}

	/**
	 * Sets the brand name of a manufacturer.
	 *
	 * The name must be a non-empty string, that starts with a "/" character.
	 *
	 * Usage:
	 * $object->setBrand('Example');
	 *
	 * @param string $brand the brand name.
	 *
	 * @throws InvalidArgumentException
	 */
	public function setBrand($brand)
	{
		if (!is_string($brand) || empty($brand))
			throw new InvalidArgumentException('Brand must be a non-empty string value.');
		if ($brand[0] !== DS)
			throw new InvalidArgumentException(sprintf('Brand string must start with a %s character.', DS));

		$this->brand = $brand;
	}
}
