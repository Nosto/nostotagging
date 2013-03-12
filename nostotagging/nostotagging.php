<?php
if (!defined('_PS_VERSION_'))
  exit;

/**
 * NostoTagging module that integrates Nosto marketing automation service.
 */
class NostoTagging extends Module
{
    const NOSTOTAGGING_CONFIG_KEY_SERVER_ADDRESS = 'NOSTOTAGGING_SERVER_ADDRESS';
    const NOSTOTAGGING_CONFIG_KEY_ACCOUNT_NAME = 'NOSTOTAGGING_ACCOUNT_NAME';
    const NOSTOTAGGING_CONFIG_KEY_USE_DEFAULT_NOSTO_ELEMENTS = 'NOSTOTAGGING_DEFAULT_ELEMENTS';
    const NOSTOTAGGING_DEFAULT_SERVER_ADDRESS = 'connect.nosto.com';
    const NOSTOTAGGING_PRODUCT_IN_STOCK = 'InStock';
    const NOSTOTAGGING_PRODUCT_OUT_OF_STOCK = 'OutOfStock';

    /**
     * Custom hooks to add for this module.
     *
     * @var array
     */
    protected $custom_hooks = array(
        array(
            'name' => 'displayCategoryTop',
            'title' => 'Category top',
            'description' => 'Add new blocks above the category product list',
        ),
        array(
            'name' => 'displayCategoryFooter',
            'title' => 'Category footer',
            'description' => 'Add new blocks below the category product list',
        ),
        array(
            'name' => 'displaySearchTop',
            'title' => 'Search top',
            'description' => 'Add new blocks above the search result list.',
        ),
        array(
            'name' => 'displaySearchFooter',
            'title' => 'Search footer',
            'description' => 'Add new blocks below the search result list.',
        ),
    );

    /**
     * Constructor.
     *
     * Defines module attributes.
     */
    public function __construct()
    {
        $this->name = 'nostotagging';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Nosto Solutions Ltd';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Nosto Tagging');
        $this->description = $this->l('Integrates Nosto marketing automation service.');
    }

    /**
     * Installs the module.
     *
     * Initializes config, adds custom hooks and registers used hooks.
     *
     * @return bool
     */
    public function install()
    {
        require_once(dirname(__FILE__).'/nostotagging-top-sellers-page.php');

        return parent::install()
            && $this->initConfig()
            && NostoTaggingTopSellersPage::addPage()
            && $this->initHooks()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayTop')
            && $this->registerHook('displayFooter')
            && $this->registerHook('displayLeftColumn')
            && $this->registerHook('displayRightColumn')
            && $this->registerHook('displayFooterProduct')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayCategoryTop')
            && $this->registerHook('displayCategoryFooter')
            && $this->registerHook('displaySearchTop')
            && $this->registerHook('displaySearchFooter');
    }

    /**
     * Uninstalls the module.
     *
     * Removes used config values. No need to un-register any hooks,
     * as that is handled by the parent class.
     *
     * @return bool
     */
    public function uninstall()
    {
        require_once(dirname(__FILE__).'/nostotagging-top-sellers-page.php');

        return parent::uninstall()
            && NostoTaggingTopSellersPage::deletePage()
            && $this->deleteConfig();
    }

    /**
     * Enables the module.
     *
     * @param bool $force_all Enable module for all shops
     * @return bool
     */
    public function enable($force_all = false)
    {
        require_once(dirname(__FILE__).'/nostotagging-top-sellers-page.php');

        if (!parent::enable($force_all))
            return false;

        NostoTaggingTopSellersPage::enablePage();

        return true;
    }

    /**
     * Disables the module.
     *
     * @param bool $force_all Disable module for all shops
     */
    public function disable($force_all = false)
    {
        require_once(dirname(__FILE__).'/nostotagging-top-sellers-page.php');

        parent::disable($force_all);

        NostoTaggingTopSellersPage::disablePage();
    }

    /**
     * Renders the module administration form.
     * Also handles the form submit action.
     *
     * @return string The HTML to output.
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit'.$this->name))
        {
            $server_address = (string)Tools::getValue($this->name.'_server_address');
            $account_name = (string)Tools::getValue($this->name.'_account_name');
            $default_nosto_elements = (int)Tools::getValue($this->name.'_use_defaults');

            if (!Validate::isUrl($server_address))
                $output .= $this->displayError($this->l('Server address is not a valid URL.'));

            if (preg_match('@^https?://@i', $server_address))
                $output .= $this->displayError($this->l('Server address cannot contain the protocol (http or https).'));

            if (empty($account_name))
                $output .= $this->displayError($this->l('Account name cannot be empty.'));

            if ($default_nosto_elements !== 0 && $default_nosto_elements !== 1)
                $output .= $this->displayError($this->l('Use default nosto elements setting is invalid.'));

            if (empty($output))
            {
                $this->setServerAddress($server_address);
                $this->setAccountName($account_name);
                $this->setUseDefaultNostoElements($default_nosto_elements);
                $output .= $this->displayConfirmation($this->l('Configuration saved'));
            }
        }

        return $output.$this->displayForm();
    }

    /**
     * Renders the module administration form.
     *
     * @return string The HTML to output.
     */
    public function displayForm()
    {
        $field_server_address = $this->name.'_server_address';
        $field_account_name = $this->name.'_account_name';
        $field_use_defaults = $this->name.'_use_defaults';

        $fields_form = array(
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->l('General Settings'),
                    ),
                    'input' => array(
                        array(
                            'type' => 'text',
                            'label' => $this->l('Server address'),
                            'name' => $field_server_address,
                            'desc' => $this->l('The server address for the Nosto marketing automation service.'),
                            'size' => 40,
                            'required' => true,
                        ),
                        array(
                            'type' => 'text',
                            'label' => $this->l('Account name'),
                            'name' => $field_account_name,
                            'desc' => $this->l('Your Nosto marketing automation service account name.'),
                            'size' => 40,
                            'required' => true,
                        ),
                        array(
                            'type' => 'radio',
                            'label' => $this->l('Use default nosto elements'),
                            'name' => $field_use_defaults,
                            'desc' => $this->l('Use default nosto elements for showing product recommendations.'),
                            'values' => array(
                                array(
                                    'id' => $this->name.'_defaults_on',
                                    'value' => 1,
                                    'label' => $this->l('Enabled'),
                                ),
                                array(
                                    'id' => $this->name.'_defaults_off',
                                    'value' => 0,
                                    'label' => $this->l('Disabled'),
                                ),
                            ),
                            'is_bool' => true,
                            'class' => 't',
                            'required' => true,
                        ),
                    ),
                    'submit' => array(
                        'title' => $this->l('Save'),
                        'name' => 'submit'.$this->name,
                        'class' => 'button',
                    ),
                ),
            ),
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->title = $this->displayName;
        $helper->submit_action = '';

        $helper->fields_value = array(
            $field_server_address => (string)Tools::getValue($field_server_address, $this->getServerAddress()),
            $field_account_name => (string)Tools::getValue($field_account_name, $this->getAccountName()),
            $field_use_defaults => (int)Tools::getValue($field_use_defaults, $this->getUseDefaultNostoElements()),
        );

        return $helper->generateForm($fields_form);
    }

    /**
     * Getter for the Nosto server address.
     *
     * @return string
     */
    public function getServerAddress()
    {
        return (string)Configuration::get(self::NOSTOTAGGING_CONFIG_KEY_SERVER_ADDRESS);
    }

    /**
     * Setter for the Nosto server address.
     *
     * @param string $server_address
     * @param bool $global
     * @return bool
     */
    public function setServerAddress($server_address, $global = false)
    {
        $callback = array(
            'Configuration',
            $global ? 'updateGlobalValue' : 'updateValue'
        );

        return call_user_func($callback, self::NOSTOTAGGING_CONFIG_KEY_SERVER_ADDRESS, (string)$server_address);
    }

    /**
     * Getter for the Nosto account name.
     *
     * @return string
     */
    public function getAccountName()
    {
        return (string)Configuration::get(self::NOSTOTAGGING_CONFIG_KEY_ACCOUNT_NAME);
    }

    /**
     * Setter for the Nosto account name.
     *
     * @param string $account_name
     * @param bool $global
     * @return bool
     */
    public function setAccountName($account_name, $global = false)
    {
        $callback = array(
            'Configuration',
            $global ? 'updateGlobalValue' : 'updateValue'
        );

        return call_user_func($callback, self::NOSTOTAGGING_CONFIG_KEY_ACCOUNT_NAME, (string)$account_name);
    }

    /**
     * Getter for the "use default nosto elements" settings.
     *
     * @return int
     */
    public function getUseDefaultNostoElements()
    {
        return (int)Configuration::get(self::NOSTOTAGGING_CONFIG_KEY_USE_DEFAULT_NOSTO_ELEMENTS);
    }

    /**
     * Setter for the "use default nosto elements" settings.
     *
     * @param int $value Either 1 or 0.
     * @param bool $global
     * @return bool
     */
    public function setUseDefaultNostoElements($value, $global = false)
    {
        $callback = array(
            'Configuration',
            $global ? 'updateGlobalValue' : 'updateValue'
        );

        return call_user_func($callback, self::NOSTOTAGGING_CONFIG_KEY_USE_DEFAULT_NOSTO_ELEMENTS, (int)$value);
    }

    /**
     * Hook for adding content to the <head> section of the HTML pages.
     *
     * Adds the Nosto embed script.
     *
     * @return string The HTML to output
     */
    public function hookDisplayHeader()
    {
        $server_address = $this->getServerAddress();
        $account_name = $this->getAccountName();

        if (empty($server_address) || empty($account_name))
            return '';

        $this->smarty->assign(array(
            'server_address' => $server_address,
            'account_name' => $account_name,
        ));

        return $this->display(__FILE__, 'header_embed-script.tpl');
    }

    /**
     * Hook for adding content to the top of every page.
     *
     * Adds customer and cart tagging.
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayTop()
    {
        $html = '';

        $html .= $this->getCustomerTagging();
        $html .= $this->getCartTagging();

        if ($this->getUseDefaultNostoElements())
            $html .= $this->display(__FILE__, 'top_nosto-elements.tpl');

        return $html;
    }

    /**
     * Hook for adding content to the footer of every page.
     *
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayFooter()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'footer_nosto-elements.tpl');
    }

    /**
     * Hook for adding content to the left column of every page.
     *
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayLeftColumn()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'left-column_nosto-elements.tpl');
    }

    /**
     * Hook for adding content to the right column of every page.
     *
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayRightColumn()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'right-column_nosto-elements.tpl');
    }

    /**
     * Hook for adding content below the product description on the product page.
     *
     * Adds product tagging.
     * Adds nosto elements.
     *
     * @param array $params
     * @return string The HTML to output
     */
    public function hookDisplayFooterProduct(Array $params)
    {
        $html = '';

        /** @var $product Product */
        $product = isset($params['product']) ? $params['product'] : null;
        $html .= $this->getProductTagging($product);

        if ($this->getUseDefaultNostoElements())
            $html .= $this->display(__FILE__, 'footer-product_nosto-elements.tpl');

        return $html;
    }

    /**
     * Hook for adding content below the product list on the shopping cart page.
     *
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayShoppingCartFooter()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'shopping-cart-footer_nosto-elements.tpl');
    }

    /**
     * Hook for adding content on the order confirmation page.
     *
     * Adds completed order tagging.
     * Adds nosto elements.
     *
     * @param array $params
     * @return string The HTML to output
     */
    public function hookDisplayOrderConfirmation(Array $params)
    {
        $html = '';

        /** @var $order Order */
        $order = isset($params['objOrder']) ? $params['objOrder'] : null;
        /** @var $currency Currency */
        $currency = isset($params['currencyObj']) ? $params['currencyObj'] : null;
        $html .= $this->getOrderTagging($order, $currency);

        return $html;
    }

    /**
     * Hook for adding content to category page above the product list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added both the category controller
     * and the theme catalog.tpl file.
     *
     * - CategoryController::initContent()
     *   $this->context->smarty->assign(array(
     *       'HOOK_CATEGORY_TOP' => Hook::exec('displayCategoryTop', array('category' => $this->category))
     *   ));
     *
     * - Theme catalog.tpl
     *   {if isset($HOOK_CATEGORY_TOP) && $HOOK_CATEGORY_TOP}{$HOOK_CATEGORY_TOP}{/if}
     *
     * @return string The HTML to output
     */
    public function hookDisplayCategoryTop()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'category-top_nosto-elements.tpl');
    }

    /**
     * Hook for adding content to category page below the product list.
     *
     * Adds category tagging.
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added both the category controller
     * and the theme catalog.tpl file.
     *
     * - CategoryController::initContent()
     *   $this->context->smarty->assign(array(
     *       'HOOK_CATEGORY_FOOTER' => Hook::exec('displayCategoryFooter', array('category' => $this->category))
     *   ));
     *
     * - Theme catalog.tpl
     *   {if isset($HOOK_CATEGORY_FOOTER) && $HOOK_CATEGORY_FOOTER}{$HOOK_CATEGORY_FOOTER}{/if}
     *
     * @param array $params
     * @return string The HTML to output
     */
    public function hookDisplayCategoryFooter(Array $params)
    {
        $html = '';

        /** @var $category Category */
        $category = isset($params['category']) ? $params['category'] : null;
        $html .= $this->getCategoryTagging($category);

        if ($this->getUseDefaultNostoElements())
            $html .= $this->display(__FILE__, 'category-footer_nosto-elements.tpl');

        return $html;
    }

    /**
     * Hook for adding content to search page above the search result list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added both the search controller
     * and the theme search.tpl file.
     *
     * - SearchController::initContent()
     *   $this->context->smarty->assign(array(
     *       'HOOK_SEARCH_TOP' => Hook::exec('displaySearchTop')
     *   ));
     *
     * - Theme search.tpl
     *   {if isset($HOOK_SEARCH_TOP) && $HOOK_SEARCH_TOP}{$HOOK_SEARCH_TOP}{/if}
     *
     * @return string The HTML to output
     */
    public function hookDisplaySearchTop()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'search-top_nosto-elements.tpl');
    }

    /**
     * Hook for adding content to search page below the search result list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added both the search controller
     * and the theme search.tpl file.
     *
     * - SearchController::initContent()
     *   $this->context->smarty->assign(array(
     *       'HOOK_SEARCH_FOOTER' => Hook::exec('displaySearchFooter')
     *   ));
     *
     * - Theme search.tpl
     *   {if isset($HOOK_SEARCH_FOOTER) && $HOOK_SEARCH_FOOTER}{$HOOK_SEARCH_FOOTER}{/if}
     *
     * @return string The HTML to output
     */
    public function hookDisplaySearchFooter()
    {
        if (!$this->getUseDefaultNostoElements())
            return '';

        return $this->display(__FILE__, 'search-footer_nosto-elements.tpl');
    }

    /**
     * Adds custom hooks used by this module.
     * Run on module install.
     *
     * @return bool
     */
    protected function initHooks()
    {
        if (!empty($this->custom_hooks))
        {
            $db = Db::getInstance();
            foreach ($this->custom_hooks as $hook)
            {
                $query = 'SELECT `name`
                          FROM `'._DB_PREFIX_.'hook`
                          WHERE `name` = "'.$db->escape($hook['name']).'"';

                if (!$db->getRow($query))
                    if (!$db->insert('hook', $hook))
                        return false;
            }
        }

        return true;
    }

    /**
     * Adds initial config values for Nosto server address and account name.
     *
     * @return bool
     */
    protected function initConfig()
    {
        return ($this->setServerAddress(self::NOSTOTAGGING_DEFAULT_SERVER_ADDRESS, true)
            && $this->setAccountName('', true)
            && $this->setUseDefaultNostoElements(1, true));
    }

    /**
     * Deletes all config entries created by the module.
     *
     * @return bool
     */
    protected function deleteConfig()
    {
        return (Configuration::deleteByName(self::NOSTOTAGGING_CONFIG_KEY_SERVER_ADDRESS)
            && Configuration::deleteByName(self::NOSTOTAGGING_CONFIG_KEY_ACCOUNT_NAME)
            && Configuration::deleteByName(self::NOSTOTAGGING_CONFIG_KEY_USE_DEFAULT_NOSTO_ELEMENTS));
    }

    /**
     * Formats price into Nosto format (e.g. 1000.99).
     *
     * @param string|int|float $price
     * @return string
     */
    protected function formatPrice($price)
    {
        return number_format((float)$price, 2, '.', '');
    }

    /**
     * Formats date into Nosto format, i.e. Y-m-d.
     *
     * @param string $date
     * @return string
     */
    protected function formatDate($date)
    {
        return date('Y-m-d', strtotime((string)$date));
    }

    /**
     * Builds a tagging string of the given category including all its parent categories.
     *
     * @param string|int $category_id
     * @return string
     */
    protected function buildCategoryString($category_id)
    {
        $category_list = array();

        $lang_id = (int)$this->context->language->id;
        $category = new Category((int)$category_id, $lang_id);

        if (Validate::isLoadedObject($category))
            foreach ($category->getParentsCategories($lang_id) as $parent_category)
                if (isset($parent_category['name']))
                    $category_list[] = (string)$parent_category['name'];

        if (empty($category_list))
            return '';

        return DS.implode(DS, array_reverse($category_list));
    }

    /**
     * Render meta-data (tagging) for the logged in customer.
     *
     * @return string The rendered HTML
     */
    protected function getCustomerTagging()
    {
        if (!isset($this->context->customer) || !$this->context->customer->isLogged())
            return '';

        $this->smarty->assign(array(
            'customer' => $this->context->customer,
        ));

        return $this->display(__FILE__, 'top_customer-tagging.tpl');
    }

    /**
     * Render meta-data (tagging) for the shopping cart.
     *
     * @return string The rendered HTML
     */
    protected function getCartTagging()
    {
        if (!isset($this->context->cart))
            return '';

        $products = (array)$this->context->cart->getProducts();
        if (empty($products))
            return '';

        $currency = $this->context->currency;

        $nosto_line_items = array();
        foreach ($products as $product)
            $nosto_line_items[] = array(
                'product_id' => (int)$product['id_product'],
                'quantity' => (int)$product['quantity'],
                'name' => (string)$product['name'],
                'unit_price' => $this->formatPrice($product['price_wt']),
                'price_currency_code' => (string)$currency->iso_code,
            );

        $this->smarty->assign(array(
            'nosto_line_items' => $nosto_line_items,
        ));

        return $this->display(__FILE__, 'top_cart-tagging.tpl');
    }

    /**
     * Render meta-data (tagging) for a product.
     *
     * @param Product $product
     * @return string The rendered HTML
     */
    protected function getProductTagging(Product $product)
    {
        if (!($product instanceof Product) || !Validate::isLoadedObject($product))
            return '';

        $nosto_product = array();
        $nosto_product['url'] = (string)$product->getLink();
        $nosto_product['product_id'] = (int)$product->id;
        $nosto_product['name'] = (string)$product->name;

        $image_id = $product->getCoverWs();
        if (ctype_digit((string)$image_id))
            $image_url = $this->context->link->getImageLink($product->link_rewrite, $image_id, 'large_default');
        else
            $image_url = '';
        $nosto_product['image_url'] = (string)$image_url;

        $nosto_product['price'] = $this->formatPrice($product->getPrice(true, null));
        $nosto_product['price_currency_code'] = (string)$this->context->currency->iso_code;

        if ($product->checkQty(1))
            $nosto_product['availability'] = self::NOSTOTAGGING_PRODUCT_IN_STOCK;
        else
            $nosto_product['availability'] = self::NOSTOTAGGING_PRODUCT_OUT_OF_STOCK;

        $nosto_product['categories'] = array();
        foreach ($product->getCategories() as $category_id)
        {
            $category = $this->buildCategoryString($category_id);
            if (!empty($category))
                $nosto_product['categories'][] = (string)$category;
        }

        $nosto_product['description'] = (string)$product->description_short;
        $nosto_product['list_price'] = $this->formatPrice($product->getPriceWithoutReduct(false, null));

        if (!empty($product->manufacturer_name))
            $nosto_product['brand'] = (string)$product->manufacturer_name;

        $nosto_product['date_published'] = $this->formatDate($product->date_add);

        $this->smarty->assign(array(
            'nosto_product' => $nosto_product,
        ));

        return $this->display(__FILE__, 'footer-product_product-tagging.tpl');
    }

    /**
     * Render meta-data (tagging) for a completed order.
     *
     * @param Order $order
     * @param Currency $currency
     * @return string The rendered HTML
     */
    protected function getOrderTagging(Order $order, Currency $currency)
    {
        if (!($order instanceof Order) || !Validate::isLoadedObject($order)
            || !($currency instanceof Currency) || !Validate::isLoadedObject($currency))
            return '';

        $nosto_order = array();
        $nosto_order['order_number'] = (int)$order->id;
        $nosto_order['customer'] = $order->getCustomer();
        $nosto_order['purchased_items'] = array();

        foreach ($order->getProducts() as $product)
        {
            $p = new Product($product['product_id'], false, $this->context->language->id);
            if (Validate::isLoadedObject($p))
                $nosto_order['purchased_items'][] = array(
                    'product_id' => (int)$p->id,
                    'quantity' => (int)$product['product_quantity'],
                    'name' => (string)$p->name,
                    'unit_price' => $this->formatPrice($product['product_price_wt']),
                    'price_currency_code' => (string)$currency->iso_code,
                );
        }

        if (empty($nosto_order['purchased_items']))
            return '';

        // Add special items for shipping, discounts and wrapping.
        if (is_numeric($order->total_discounts_tax_incl) && $order->total_discounts_tax_incl > 0)
            $nosto_order['purchased_items'][] = array(
                'product_id' => -1,
                'quantity' => 1,
                'name' => 'Discount',
                'unit_price' => $this->formatPrice(-$order->total_discounts_tax_incl),
                'price_currency_code' => (string)$currency->iso_code,
            );

        if (is_numeric($order->total_shipping_tax_incl) && $order->total_shipping_tax_incl > 0)
            $nosto_order['purchased_items'][] = array(
                'product_id' => -1,
                'quantity' => 1,
                'name' => 'Shipping',
                'unit_price' => $this->formatPrice($order->total_shipping_tax_incl),
                'price_currency_code' => (string)$currency->iso_code,
            );

        if (is_numeric($order->total_wrapping_tax_incl) && $order->total_wrapping_tax_incl > 0)
            $nosto_order['purchased_items'][] = array(
                'product_id' => -1,
                'quantity' => 1,
                'name' => 'Gift Wrapping',
                'unit_price' => $this->formatPrice($order->total_wrapping_tax_incl),
                'price_currency_code' => (string)$currency->iso_code,
            );

        $this->smarty->assign(array(
            'nosto_order' => $nosto_order,
        ));

        return $this->display(__FILE__, 'order-confirmation_order-tagging.tpl');
    }

    /**
     * Render meta-data (tagging) for a category.
     *
     * @param Category $category
     * @return string The rendered HTML
     */
    protected function getCategoryTagging(Category $category)
    {
        if (!($category instanceof Category) || !Validate::isLoadedObject($category))
            return '';

        $category_string = $this->buildCategoryString($category->id);
        if (empty($category_string))
            return '';

        $this->smarty->assign(array(
            'category' => (string)$category_string,
        ));

        return $this->display(__FILE__, 'category-footer_category-tagging.tpl');
    }
}
