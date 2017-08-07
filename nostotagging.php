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

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * Only try to load class files if we can resolve the __FILE__ global to the current file.
 * We need to do this as this module file is parsed with eval() on the modules page, and eval() messes up the __FILE__.
 */
if ((basename(__FILE__) === 'nostotagging.php')) {
    define('NOSTO_DIR', dirname(__FILE__));
    require_once("bootstrap.php");
}

/**
 * NostoTagging module that integrates Nosto marketing automation service.
 *
 * @property Context $context
 */
class NostoTagging extends Module
{
    /**
     * The version of the Nosto plug-in
     *
     * @var string
     */
    const PLUGIN_VERSION = '2.8.3';

    /**
     * Internal name of the Nosto plug-in
     *
     * @var string
     */
    const MODULE_NAME = 'nostotagging';

    /**
     * @var string the algorithm to use for hashing visitor id.
     */
    const VISITOR_HASH_ALGO = 'sha256';

    /**
     * Custom hooks to add for this module.
     *
     * @var array
     */
    protected static $custom_hooks = array(
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
        array(
            'name' => 'actionNostoCartLoadAfter',
            'title' => 'After load nosto cart',
            'description' => 'Action hook fired after a Nosto cart object has been loaded.',
        ),
        array(
            'name' => 'actionNostoOrderLoadAfter',
            'title' => 'After load nosto order',
            'description' => 'Action hook fired after a Nosto order object has been loaded.',
        ),
        array(
            'name' => 'actionNostoProductLoadAfter',
            'title' => 'After load nosto product',
            'description' => 'Action hook fired after a Nosto product object has been loaded.',
        ),
        array(
            'name' => 'actionNostoPriceVariantLoadAfter',
            'title' => 'After load nosto price variation',
            'description' => 'Action hook fired after a Nosto price variation object has been initialized.',
        ),
        array(
            'name' => 'actionNostoRatesLoadAfter',
            'title' => 'After load nosto exchange rates',
            'description' => 'Action hook fired after a Nosto exchange rate collection has been initialized.',
        ),
    );

    /**
     * Constructor.
     *
     * Defines module attributes.
     *
     * @suppress PhanTypeMismatchProperty
     */
    public function __construct()
    {
        $this->name = self::MODULE_NAME;
        $this->tab = 'advertising_marketing';
        $this->version = self::PLUGIN_VERSION;
        $this->author = 'Nosto';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);
        $this->module_key = '8d80397cab6ca02dfe8ef681b48c37a3';

        parent::__construct();
        $this->displayName = $this->l('Nosto Personalization for PrestaShop');
        $this->description = $this->l(
            'Increase your conversion rate and average order value by delivering your customers personalized product
            recommendations throughout their shopping journey.'
        );
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
        $success = false;
        if (parent::install()) {
            $success = true;
            if (
                !$this->registerHook('displayCategoryTop')
                || !$this->registerHook('displayCategoryFooter')
                || !$this->registerHook('displaySearchTop')
                || !$this->registerHook('displaySearchFooter')
                || !$this->registerHook('header')
                || !$this->registerHook('top')
                || !$this->registerHook('footer')
                || !$this->registerHook('productfooter')
                || !$this->registerHook('shoppingCart')
                || !$this->registerHook('orderConfirmation')
                || !$this->registerHook('postUpdateOrderStatus')
                || !$this->registerHook('paymentTop')
                || !$this->registerHook('home')
            ) {
                $success = false;
                $this->_errors[] = $this->l(
                    'Failed to register hooks'
                );
            }
            /* @var NostoTaggingHelperCustomer $helper_customer */
            $helper_customer = Nosto::helper('nosto_tagging/customer');
            if (!$helper_customer->createTables()) {
                $success = false;
                $this->_errors[] = $this->l(
                    'Failed to create Nosto customer table'
                );
            }
            if (!NostoTaggingHelperAdminTab::install()) {
                $success = false;
                $this->_errors[] = $this->l(
                    'Failed to create Nosto admin tab'
                );
            }
            if (!$this->initHooks()) {
                $success = false;
            }
            // For versions < 1.5.3.1 we need to keep track of the currently installed version.
            // This is to enable auto-update of the module by running its upgrade scripts.
            // This config value is updated in the NostoTaggingUpdater helper every time the module is updated.
            if ($success) {
                if (version_compare(_PS_VERSION_, '1.5.4.0', '<')) {
                    /** @var NostoTaggingHelperConfig $config_helper */
                    $config_helper = Nosto::helper('nosto_tagging/config');
                    $config_helper->saveInstalledVersion($this->version);
                }

                $success = $this->registerHook('actionObjectUpdateAfter')
                    && $this->registerHook('actionObjectDeleteAfter')
                    && $this->registerHook('actionObjectAddAfter')
                    && $this->registerHook('actionObjectCurrencyUpdateAfter')
                    && $this->registerHook('displayBackOfficeTop')
                    && $this->registerHook('displayBackOfficeHeader');
                // New hooks in 1.7
                if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                    $this->registerHook('displayNav1');
                }
            }
        }

        return $success;
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
        return parent::uninstall()
            && NostoTaggingHelperAccount::deleteAll()
            && NostoTaggingHelperConfig::purge()
            && NostoTaggingHelperCustomer::dropTables()
            && NostoTaggingHelperAdminTab::uninstall();
    }

    /**
     * Renders the module administration form.
     * Also handles the form submit action.
     *
     * @return string The HTML to output.
     * @suppress PhanDeprecatedFunction
     */
    public function getContent()
    {
        // Always update the url to the module admin page when we access it.
        // This can then later be used by the oauth2 controller to redirect the user back.
        $admin_url = $this->getAdminUrl();

        /** @var NostoTaggingHelperConfig $config_helper */
        $config_helper = Nosto::helper('nosto_tagging/config');
        $config_helper->saveAdminUrl($admin_url);
        $output = '';
        $languages = Language::getLanguages(true, $this->context->shop->id);
        /** @var EmployeeCore $employee */
        $employee = $this->context->employee;
        $account_email = $employee->email;
        /** @var NostoTaggingHelperUrl $helper_url */
        $helper_url = Nosto::helper('nosto_tagging/url');
        /** @var NostoTaggingHelperConfig $helper_config */
        $helper_config = Nosto::helper('nosto_tagging/config');
        $id_shop = null;
        $id_shop_group = null;
        if ($this->context->shop instanceof Shop) {
            $id_shop = $this->context->shop->id;
            $id_shop_group = $this->context->shop->id_shop_group;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $language_id = (int)Tools::getValue($this->name . '_current_language');
            $current_language = $this->ensureAdminLanguage($languages, $language_id);
            if (Shop::getContext() !== Shop::CONTEXT_SHOP) {
                // Do nothing.
                // After the redirect this will be checked again and an error message is outputted.
            } elseif ($current_language['id_lang'] != $language_id) {
                NostoTaggingHelperFlashMessage::add('error', $this->l('Language cannot be empty.'));
            } elseif (
                Tools::isSubmit('submit_nostotagging_new_account')
                || Tools::getValue('nostotagging_account_action') === 'newAccount'
            ) {
                $account_email = (string)Tools::getValue($this->name . '_account_email');
                if (empty($account_email)) {
                    NostoTaggingHelperFlashMessage::add(
                        'error',
                        $this->l('Email cannot be empty.')
                    );
                } elseif (!Validate::isEmail($account_email)) {
                    NostoTaggingHelperFlashMessage::add(
                        'error',
                        $this->l('Email is not a valid email address.')
                    );
                } else {
                    try {
                        if (Tools::isSubmit('nostotagging_account_details')) {
                            $account_details = (object)Tools::jsonDecode(Tools::getValue('nostotagging_account_details'));
                        } else {
                            $account_details = false;
                        }
                        $service = new NostoSignupService();
                        $service->createAccount($language_id, $account_email, $account_details);

                        $helper_config->clearCache();
                        NostoTaggingHelperFlashMessage::add(
                            'success',
                            $this->l(
                                'Account created. Please check your email and follow the instructions to set a'
                                . ' password for your new account within three days.'
                            )
                        );
                    } catch (\Nosto\Request\Api\Exception\ApiResponseException $e) {
                        NostoTaggingHelperFlashMessage::add(
                            'error',
                            $this->l(
                                'Account could not be automatically created due to missing or invalid parameters.'
                                . ' Please see your Prestashop logs for details'
                            )
                        );
                        NostoTaggingHelperLogger::error(
                            'Creating Nosto account failed: ' . $e->getMessage() . ':' . $e->getCode(),
                            $e->getCode(),
                            'Employee',
                            (int)$employee->id
                        );
                    } catch (Exception $e) {
                        NostoTaggingHelperFlashMessage::add(
                            'error',
                            $this->l('Account could not be automatically created. Please see logs for details.')
                        );
                        NostoTaggingHelperLogger::error(
                            'Creating Nosto account failed: ' . $e->getMessage() . ':' . $e->getCode(),
                            $e->getCode(),
                            'Employee',
                            (int)$employee->id
                        );
                    }
                }
            } elseif (
                Tools::isSubmit('submit_nostotagging_authorize_account')
                || Tools::getValue('nostotagging_account_action') === 'connectAccount'
                || Tools::getValue('nostotagging_account_action') === 'syncAccount'
            ) {
                $meta = NostoTaggingMetaOauth::loadData($this->context, $language_id, $this->name,
                    $this->_path);
                Tools::redirect(Nosto\Helper\OAuthHelper::getAuthorizationUrl($meta), '');
                die();
            } elseif (
                Tools::isSubmit('submit_nostotagging_reset_account')
                || Tools::getValue('nostotagging_account_action') === 'removeAccount'
            ) {
                $account = NostoTaggingHelperAccount::findByContext($this->context);
                $helper_config->clearCache();
                NostoTaggingHelperAccount::delete($this->context, $account, $language_id, null);
            } elseif (Tools::isSubmit('submit_nostotagging_update_exchange_rates')) {
                $nosto_account = NostoTaggingHelperAccount::find($language_id, $id_shop_group,
                    $id_shop);
                $operation = new NostoRatesService();
                if ($nosto_account && $operation->updateCurrencyExchangeRates($nosto_account, $this->context)) {
                    NostoTaggingHelperFlashMessage::add(
                        'success',
                        $this->l(
                            'Exchange rates successfully updated to Nosto'
                        )
                    );
                } else {
                    if (!$nosto_account->getApiToken(Nosto\Request\Api\Token::API_EXCHANGE_RATES)) {
                        $message = 'Failed to update exchange rates to Nosto due to a missing API token. 
                            Please, reconnect your account with Nosto';
                    } else {
                        $message = 'There was an error updating the exchange rates. 
                            See Prestashop logs for more information.';
                    }
                    NostoTaggingHelperFlashMessage::add(
                        'error',
                        $this->l($message)
                    );
                }
            } elseif (
                Tools::isSubmit('submit_nostotagging_advanced_settings')
                && Tools::isSubmit('multi_currency_method')
            ) {
                /** @var NostoTaggingHelperConfig $helper_config */
                $helper_config = Nosto::helper('nosto_tagging/config');
                $helper_config->saveMultiCurrencyMethod(
                    Tools::getValue('multi_currency_method'),
                    $language_id,
                    $id_shop_group,
                    $id_shop
                );
                $helper_config->saveNostoTaggingRenderPosition(
                    Tools::getValue('nostotagging_position'),
                    $language_id,
                    $id_shop_group,
                    $id_shop
                );
                $helper_config->saveImageType(
                    Tools::getValue('image_type'),
                    $language_id,
                    $id_shop_group,
                    $id_shop
                );
                $account = NostoTaggingHelperAccount::find($language_id, $id_shop_group, $id_shop);
                $account_meta = NostoTaggingMetaAccount::loadData($this->context, $language_id);
                // Make sure we Nosto is installed for the current store
                if (empty($account) || !$account->isConnectedToNosto()) {
                    Tools::redirect(
                        Nosto\Request\Http\HttpRequest::replaceQueryParamInUrl(
                            'language_id',
                            $language_id,
                            $admin_url
                        ),
                        ''
                    );
                    die;
                }
                try {
                    $operation = new NostoSettingsService($account);
                    $operation->update($account_meta);
                    NostoTaggingHelperFlashMessage::add(
                        'success',
                        $this->l('The settings have been saved.')
                    );
                } catch (\Nosto\NostoException $e) {
                    NostoTaggingHelperLogger::error(
                        __CLASS__ . '::' . __FUNCTION__ . ' - ' . $e->getMessage(),
                        $e->getCode(),
                        'Employee',
                        (int)$employee->id
                    );

                    NostoTaggingHelperFlashMessage::add(
                        'error',
                        $this->l('There was an error saving the settings. Please, see log for details.')
                    );
                }
                // Also update the exchange rates if multi currency is used
                if ($account_meta->getUseExchangeRates()) {
                    $operation = new NostoRatesService();
                    $operation->updateCurrencyExchangeRates($account, $this->context);
                }
            }

            // Refresh the page after every POST to get rid of form re-submission errors.
            Tools::redirect(
                Nosto\Request\Http\HttpRequest::replaceQueryParamInUrl(
                    'language_id',
                    $language_id,
                    $admin_url
                ),
                ''
            );
            die;
        } else {
            $language_id = (int)Tools::getValue('language_id', 0);

            if (($error_message = Tools::getValue('oauth_error')) !== false) {
                $output .= $this->displayError($this->l($error_message));
            }
            if (($success_message = Tools::getValue('oauth_success')) !== false) {
                $output .= $this->displayConfirmation($this->l($success_message));
            }

            foreach (NostoTaggingHelperFlashMessage::getList('success') as $flash_message) {
                $output .= $this->displayConfirmation($flash_message);
            }
            foreach (NostoTaggingHelperFlashMessage::getList('error') as $flash_message) {
                $output .= $this->displayError($flash_message);
            }

            if (Shop::getContext() !== Shop::CONTEXT_SHOP) {
                $output .= $this->displayError($this->l('Please choose a shop to configure Nosto for.'));
            }
        }
        // Choose current language if it has not been set.
        if (!isset($current_language)) {
            $current_language = $this->ensureAdminLanguage($languages, $language_id);
            $language_id = (int)$current_language['id_lang'];
        }
        /** @var NostoAccount $account */
        $account = NostoTaggingHelperAccount::find($language_id, $id_shop_group, $id_shop);
        $missing_tokens = true;
        if (
            $account instanceof Nosto\Types\Signup\AccountInterface
            && $account->getApiToken(Nosto\Request\Api\Token::API_EXCHANGE_RATES)
            && $account->getApiToken(Nosto\Request\Api\Token::API_SETTINGS)
        ) {
            $missing_tokens = false;
        }
        // When no account is found we will show the installation URL
        if (
            $account instanceof Nosto\Object\Signup\Account === false
            && Shop::getContext() === Shop::CONTEXT_SHOP
        ) {
            $currentUser = NostoTaggingCurrentUser::loadData($this->context);
            $account_iframe = NostoTaggingMetaAccountIframe::loadData(
                $this->context,
                $language_id,
                ''
            );
            $iframe_installation_url = \Nosto\Helper\IframeHelper::getUrl(
                $account_iframe,
                $account,
                $currentUser,
                array('v' => 1)
            );
        } else {
            $iframe_installation_url = null;
        }
        /** @var NostoTaggingHelperImage $helper_images */
        $helper_images = Nosto::helper('nosto_tagging/image');
        $this->getSmarty()->assign(array(
            $this->name . '_form_action' => $this->getAdminUrl(),
            $this->name . '_create_account' => $this->getAdminUrl(),
            $this->name . '_delete_account' => $this->getAdminUrl(),
            $this->name . '_connect_account' => $this->getAdminUrl(),
            $this->name . '_has_account' => ($account !== null),
            $this->name . '_account_name' => ($account !== null) ? $account->getName() : null,
            $this->name . '_account_email' => $account_email,
            $this->name . '_account_authorized' => ($account !== null) ? $account->isConnectedToNosto() : false,
            $this->name . '_languages' => $languages,
            $this->name . '_current_language' => $current_language,
            $this->name . '_translations' => array(
                'installed_heading' => sprintf(
                    $this->l('You have installed Nosto to your %s shop'),
                    $current_language['name']
                ),
                'installed_subheading' => sprintf(
                    $this->l('Your account ID is %s'),
                    ($account !== null) ? $account->getName() : ''
                ),
                'not_installed_subheading' => sprintf(
                    $this->l('Install Nosto to your %s shop'),
                    $current_language['name']
                ),
                'exchange_rate_crontab_example' => sprintf(
                    '0 0 * * * curl --silent %s > /dev/null 2>&1',
                    $helper_url->getModuleUrl(
                        $this->name,
                        $this->_path,
                        'cronRates',
                        $current_language['id_lang'],
                        $id_shop,
                        array('token' => NostoTaggingHelperCron::getCronAccessToken())
                    )
                ),
            ),
            'multi_currency_method' => $helper_config->getMultiCurrencyMethod(
                $current_language['id_lang'],
                $id_shop_group,
                $id_shop
            ),
            'nostotagging_position' => $helper_config->getNostotaggingRenderPosition(
                $current_language['id_lang'],
                $id_shop_group,
                $id_shop
            ),
            $this->name . '_ps_version_class' => 'ps-' . str_replace('.', '',
                    Tools::substr(_PS_VERSION_, 0, 3)),
            'missing_tokens' => $missing_tokens,
            'iframe_installation_url' => $iframe_installation_url,
            'iframe_origin' => $helper_url->getIframeOrigin(),
            'module_path' => $this->_path,
            'image_types' => $helper_images->getProductImageTypes(),
            'current_image_type' => $helper_config->getImageType(
                $current_language['id_lang'],
                $id_shop_group,
                $id_shop
            )
        ));
        // Try to login employee to Nosto in order to get a url to the internal setting pages,
        // which are then shown in an iframe on the module config page.
        if (
            $account
            && $account->isConnectedToNosto()
            && Shop::getContext() === Shop::CONTEXT_SHOP
        ) {
            try {
                $currentUser = NostoTaggingCurrentUser::loadData($this->context);
                $meta = NostoTaggingMetaAccountIframe::loadData(
                    $this->context,
                    $language_id,
                    ''
                );
                $url = \Nosto\Helper\IframeHelper::getUrl($meta, $account, $currentUser);
                if (!empty($url)) {
                    $this->getSmarty()->assign(array('iframe_url' => $url));
                }
            } catch (\Nosto\NostoException $e) {
                NostoTaggingHelperLogger::error(
                    __CLASS__ . '::' . __FUNCTION__ . ' - ' . $e->getMessage(),
                    $e->getCode(),
                    'Employee',
                    (int)$employee->id
                );
            }
        }
        $output .= $this->display(__FILE__, $this->getSettingsTemplate());

        return $output;
    }

    /**
     * @return string
     */
    private function getSettingsTemplate()
    {
        $template_file = 'views/templates/admin/config-bootstrap.tpl';
        if (_PS_VERSION_ < '1.6') {
            $template_file = 'views/templates/admin/legacy-config-bootstrap.tpl';
        }

        return $template_file;
    }

    /**
     * Hook for adding content to the <head> section of the HTML pages.
     * Adds the Nosto embed script.
     *
     * @return string The HTML to output
     */
    public function hookDisplayHeader()
    {
        return NostoHeaderContent::get();
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayHeader()
     * @return string The HTML to output
     */
    public function hookHeader()
    {
        return $this->hookDisplayHeader();
    }

    /**
     * Hook for adding content to the <head> section of the back office HTML pages.
     * Also updates exchange rates if needed.
     *
     * Note: PS 1.5+ only.
     *
     * Adds Nosto admin tab CSS.
     */
    public function hookDisplayBackOfficeHeader()
    {
        // In some cases, the controller in the context is actually not an instance of `AdminController`,
        // but of `AdminTab`. This class does not have an `addCss` method.
        // In these cases, we skip adding the CSS which will only cause the logo to be missing for the
        // Nosto menu item in PS >= 1.6.
        $ctrl = $this->context->controller;
        if ($ctrl instanceof AdminController && method_exists($ctrl, 'addCss')) {
            $ctrl->addCss($this->_path . 'views/css/nostotagging-back-office.css');
        }
        $this->updateExchangeRatesIfNeeded(false);
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
        return NostoDefaultTagging::get();
    }

    /**
     * Hook for adding content to the top of every page in displayNav1.
     *
     * Adds customer and cart tagging.
     * Adds nosto elements.
     *
     * @since Prestashop 1.7.0.0
     * @return string The HTML to output
     */
    public function hookDisplayNav1()
    {
        return NostoDefaultTagging::get();
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayTop()
     * @return string The HTML to output
     */
    public function hookTop()
    {
        return $this->hookDisplayTop();
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
        $html = NostoDefaultTagging::get();
        $html .= NostoRecommendationElement::get("nosto-page-footer");
        return $html;
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayFooter()
     * @return string The HTML to output
     */
    public function hookFooter()
    {
        return $this->hookDisplayFooter();
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
        return NostoRecommendationElement::get("nosto-column-left");
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayLeftColumn()
     * @return string The HTML to output
     */
    public function hookLeftColumn()
    {
        return $this->hookDisplayLeftColumn();
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
        return NostoRecommendationElement::get("nosto-column-right");
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayRightColumn()
     * @return string The HTML to output
     */
    public function hookRightColumn()
    {
        return $this->hookDisplayRightColumn();
    }

    /**
     * Hook for adding content below the product description on the product page.
     *
     * Adds product tagging.
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayFooterProduct()
    {
        $html = '';
        $html .= NostoRecommendationElement::get("nosto-page-product1");
        $html .= NostoRecommendationElement::get("nosto-page-product2");
        $html .= NostoRecommendationElement::get("nosto-page-product3");
        return $html;
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayFooterProduct()
     * @param array $params
     * @return string The HTML to output
     */
    public function hookProductFooter(array $params)
    {
        return $this->hookDisplayFooterProduct($params);
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
        /* @var NostoTaggingHelperCustomer $customer_helper */
        $customer_helper = Nosto::helper('nosto_tagging/customer');
        $customer_helper->updateNostoId();

        $html = '';
        $html .= NostoRecommendationElement::get("nosto-page-cart1");
        $html .= NostoRecommendationElement::get("nosto-page-cart2");
        $html .= NostoRecommendationElement::get("nosto-page-cart3");
        return $html;
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayShoppingCartFooter()
     * @return string The HTML to output
     */
    public function hookShoppingCart()
    {
        return $this->hookDisplayShoppingCartFooter();
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
    public function hookDisplayOrderConfirmation()
    {
        if (!NostoTaggingHelperAccount::isContextConnected($this->context)) {
            return '';
        }

        return ''; //TODO: Nothing rendered here?!?!
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayOrderConfirmation()
     * @param array $params
     * @return string The HTML to output
     */
    public function hookOrderConfirmation(array $params)
    {
        return $this->hookDisplayOrderConfirmation($params);
    }

    /**
     * Hook for adding content to category page above the product list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added to the
     * theme category.tpl file.
     *
     * - Theme category.tpl: add the below line to the top of the file
     *   {hook h='displayCategoryTop'}
     *
     * @return string The HTML to output
     */
    public function hookDisplayCategoryTop()
    {
        return NostoRecommendationElement::get("nosto-page-category1");
    }

    /**
     * Hook for adding content to category page below the product list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added to the
     * theme category.tpl file.
     *
     * - Theme category.tpl: add the below line to the end of the file
     *   {hook h='displayCategoryFooter'}
     *
     * @return string The HTML to output
     */
    public function hookDisplayCategoryFooter()
    {
        return NostoRecommendationElement::get("nosto-page-category2");
    }

    /**
     * Hook for adding content to search page above the search result list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added to the
     * theme search.tpl file.
     *
     * - Theme search.tpl: add the below line to the top of the file
     *   {hook h='displaySearchTop'}
     *
     * @return string The HTML to output
     */
    public function hookDisplaySearchTop()
    {
        return NostoRecommendationElement::get("nosto-page-search1");
    }

    /**
     * Hook for adding content to search page below the search result list.
     *
     * Adds nosto elements.
     *
     * Please note that in order for this hook to be executed, it will have to be added to the
     * theme search.tpl file.
     *
     * - Theme search.tpl: add the below line to the end of the file
     *   {hook h='displaySearchFooter'}
     *
     * @return string The HTML to output
     */
    public function hookDisplaySearchFooter()
    {
        return NostoRecommendationElement::get("nosto-page-search2");
    }

    /**
     * Hook for updating the customer link table with the Prestashop customer id and the Nosto
     * customer id.
     */
    public function hookDisplayPaymentTop()
    {
        /* @var NostoTaggingHelperCustomer $customer_helper */
        $customer_helper = Nosto::helper('nosto_tagging/customer');
        $customer_helper->updateNostoId();
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayPaymentTop()
     */
    public function hookPaymentTop()
    {
        $this->hookDisplayPaymentTop();
    }

    /**
     * Hook for sending order confirmations to Nosto via the API.
     *
     * This is a fallback for the regular order tagging on the "order confirmation page", as there
     * are cases when the customer does not get redirected back to the shop after the payment is
     * completed.
     *
     * @param array $params
     */
    public function hookActionOrderStatusPostUpdate(array $params)
    {
        $operation = new AbstractNostoService(Context::getContext());
        $operation->send($params);
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookActionOrderStatusPostUpdate()
     * @param array $params
     */
    public function hookPostUpdateOrderStatus(array $params)
    {
        $this->hookActionOrderStatusPostUpdate($params);
    }

    /**
     * Hook for adding content to the home page.
     * Adds nosto elements.
     *
     * @return string The HTML to output
     */
    public function hookDisplayHome()
    {
        $html = '';
        $html .= NostoRecommendationElement::get("frontpage-nosto-1");
        $html .= NostoRecommendationElement::get("frontpage-nosto-2");
        $html .= NostoRecommendationElement::get("frontpage-nosto-3");
        $html .= NostoRecommendationElement::get("frontpage-nosto-4");
        return $html;
    }

    /**
     * Backwards compatibility hook.
     *
     * @see NostoTagging::hookDisplayHome()
     * @return string The HTML to output
     */
    public function hookHome()
    {
        return $this->hookDisplayHome();
    }

    /**
     * Hook that is fired after a object is updated in the db.
     *
     * @param array $params
     */
    public function hookActionObjectUpdateAfter(array $params)
    {
        $operation = new AbstractNostoService();
        $operation->upsert($params);
    }

    /**
     * Hook that is fired after a object is deleted from the db.
     *
     * @param array $params
     */
    public function hookActionObjectDeleteAfter(array $params)
    {
        $operation = new AbstractNostoService();
        $operation->delete($params);
    }

    /**
     * Hook that is fired after a object has been created in the db.
     *
     * @param array $params
     */
    public function hookActionObjectAddAfter(array $params)
    {
        $operation = new AbstractNostoService();
        $operation->upsert($params);
    }

    /**
     * Hook called when a product is update with a new picture, right after said update
     *
     * @see NostoTagging::hookActionObjectUpdateAfter
     * @param array $params
     */
    public function hookUpdateProduct(array $params)
    {
        $this->hookActionObjectUpdateAfter(array('object' => $params['product']));
    }

    /**
     * Hook called when a product is deleted, right before said deletion
     *
     * @see NostoTagging::hookActionObjectDeleteAfter
     * @param array $params
     */
    public function hookDeleteProduct(array $params)
    {
        $this->hookActionObjectDeleteAfter(array('object' => $params['product']));
    }

    /**
     * Hook called when a product is added, right after said addition
     *
     * @see NostoTagging::hookActionObjectAddAfter
     * @param array $params
     */
    public function hookAddProduct(array $params)
    {
        $this->hookActionObjectAddAfter(array('object' => $params['product']));
    }

    /**
     * Hook called during an the validation of an order, the status of which being something other
     * than
     * "canceled" or "Payment error", for each of the order's item
     *
     * @see NostoTagging::hookActionObjectUpdateAfter
     * @param array $params
     */
    public function hookUpdateQuantity(array $params)
    {
        $this->hookActionObjectUpdateAfter(array('object' => $params['product']));
    }

    /**
     * Gets the current admin config language data.
     *
     * @param array $languages list of valid languages.
     * @param int $id_lang if a specific language is required.
     * @return array the language data array.
     */
    protected function ensureAdminLanguage(array $languages, $id_lang)
    {
        foreach ($languages as $language) {
            if ($language['id_lang'] == $id_lang) {
                return $language;
            }
        }

        if (isset($languages[0])) {
            return $languages[0];
        } else {
            return array('id_lang' => 0, 'name' => '', 'iso_code' => '');
        }
    }

    /**
     * Returns hidden nosto recommendation elements for the current controller.
     * These are used as a fallback for showing recommendations if the appropriate hooks are not
     * present in the theme. The hidden elements are put into place and shown in the shop with
     * JavaScript.
     *
     * @return string the html.
     */
    protected function getHiddenRecommendationElements()
    {
        if (NostoTaggingHelperController::isController('index')) {
            // The home page.
            return $this->display(__FILE__, 'views/templates/hook/home_hidden-nosto-elements.tpl');
        } elseif (NostoTaggingHelperController::isController('product')) {
            // The product page.
            return $this->display(__FILE__,
                'views/templates/hook/footer-product_hidden-nosto-elements.tpl');
        } elseif (NostoTaggingHelperController::isController('order') && (int)Tools::getValue('step',
                0) === 0
        ) {
            // The cart summary page.
            return $this->display(__FILE__,
                'views/templates/hook/shopping-cart-footer_hidden-nosto-elements.tpl');
        } elseif (NostoTaggingHelperController::isController('category')
            || NostoTaggingHelperController::isController('manufacturer')
        ) {
            // The category/manufacturer page.
            return $this->display(__FILE__,
                'views/templates/hook/category-footer_hidden-nosto-elements.tpl');
        } elseif (NostoTaggingHelperController::isController('search')) {
            // The search page.
            return $this->display(__FILE__,
                'views/templates/hook/search_hidden-nosto-elements.tpl');
        } elseif (NostoTaggingHelperController::isController('pagenotfound')
            || NostoTaggingHelperController::isController('404')
        ) {
            // The search page.
            return $this->display(__FILE__, 'views/templates/hook/404_hidden_nosto-elements.tpl');
        } elseif (NostoTaggingHelperController::isController('order-confirmation')) {
            // The search page.
            return $this->display(__FILE__,
                'views/templates/hook/order-confirmation_hidden_nosto-elements.tpl');
        } else {
            // If the current page is not one of the ones we want to show recommendations on, just return empty.
            return '';
        }
    }

    /**
     * Returns the admin url.
     * Note the url is parsed from the current url, so this can only work if called when on the
     * admin page.
     *
     * @return string the url.
     */
    protected function getAdminUrl()
    {
        $current_url = Tools::getHttpHost(true) . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        $parsed_url = Nosto\Request\Http\HttpRequest::parseUrl($current_url);
        $parsed_query_string = Nosto\Request\Http\HttpRequest::parseQueryString($parsed_url['query']);
        $valid_params = array(
            'controller',
            'token',
            'configure',
            'tab_module',
            'module_name',
            'tab',
        );
        $query_params = array();
        foreach ($valid_params as $valid_param) {
            if (isset($parsed_query_string[$valid_param])) {
                $query_params[$valid_param] = $parsed_query_string[$valid_param];
            }
        }
        $parsed_url['query'] = http_build_query($query_params);
        return Nosto\Request\Http\HttpRequest::buildUrl($parsed_url);
    }

    /**
     * Adds custom hooks used by this module.
     *
     * Run on module install.
     *
     * @return bool
     */
    protected function initHooks()
    {
        $success = true;
        if (!empty(self::$custom_hooks)) {
            foreach (self::$custom_hooks as $hook) {
                $callback = array(
                    'Hook',
                    (method_exists('Hook', 'getIdByName')) ? 'getIdByName' : 'get'
                );
                $id_hook = call_user_func($callback, $hook['name']);
                if (empty($id_hook)) {
                    $new_hook = new Hook();
                    $new_hook->name = pSQL($hook['name']);
                    $new_hook->title = pSQL($hook['title']);
                    $new_hook->description = pSQL($hook['description']);
                    $new_hook->add();
                    $id_hook = $new_hook->id;
                    if (!$id_hook) {
                        $success = false;
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Method for resolving correct smarty object
     *
     * @return Smarty|Smarty_Data
     * @throws \Nosto\NostoException
     */
    protected function getSmarty()
    {
        if (!empty($this->smarty)
            && method_exists($this->smarty, 'assign')
        ) {
            return $this->smarty;
        } elseif (!empty($this->context->smarty)
            && method_exists($this->context->smarty, 'assign')
        ) {
            return $this->context->smarty;
        }

        throw new \Nosto\NostoException('Could not find smarty');
    }

    /**
     * Updates the exchange rates to Nosto when user logs in or logs out
     */
    public function hookDisplayBackOfficeTop()
    {
        $this->checkNotifications();
    }

    public function hookBackOfficeFooter()
    {
        return $this->updateExchangeRatesIfNeeded(false);
    }

    /**
     * Defines exchange rates updated for current session
     */
    public function defineExchangeRatesAsUpdated()
    {
        if (Context::getContext()->cookie && $this->adminLoggedIn()) {
            /** @noinspection PhpUndefinedFieldInspection */
            Context::getContext()->cookie->nostoExchangeRatesUpdated = (string)true;
        }
    }

    /**
     * Checks if the exchange rates have been updated during the current
     * admin session
     *
     * @return boolean
     */
    public function exchangeRatesShouldBeUpdated()
    {
        if (!$this->adminLoggedIn()) {
            return false;
        }

        $cookie = Context::getContext()->cookie;
        if (
            isset($cookie->nostoExchangeRatesUpdated)
            && $cookie->nostoExchangeRatesUpdated == true //@codingStandardsIgnoreLine
        ) {

            return false;
        }

        return true;
    }

    /**
     * Updates the exchange rates to Nosto when currency object is saved
     *
     * @param array $params
     */
    public function hookActionObjectCurrencyUpdateAfter(
        /** @noinspection PhpUnusedParameterInspection */
        array $params
    ) {
        return $this->updateExchangeRatesIfNeeded(true);
    }

    /**
     * Updates the exchange rates to Nosto if needed
     *
     * @param boolean $force if set to true cookie check is ignored
     * @internal param array $params
     */
    public function updateExchangeRatesIfNeeded($force = false)
    {
        if ($this->exchangeRatesShouldBeUpdated() || $force === true) {
            $this->defineExchangeRatesAsUpdated(); // This ensures we only try this at once
            try {
                $operation = new NostoRatesService();
                $operation->updateExchangeRatesForAllStores();
                $this->defineExchangeRatesAsUpdated();
            } catch (\Nosto\NostoException $e) {
                NostoTaggingHelperLogger::error(
                    'Exchange rate sync failed with error: %s',
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Checks if user is logged into store admin
     *
     * @return bool
     */
    public function adminLoggedIn()
    {
        /* @var Employee $employee */
        $employee = $this->context->employee;
        $logged_in = false;
        if ($employee instanceof Employee && $employee->id) {
            $logged_in = true;
        }

        return $logged_in;
    }

    /**
     * Checks all Nosto notifications and adds them as an admin notification
     */
    public function checkNotifications()
    {
        /* @var NostoTaggingHelperNotification $helper_notification */
        $helper_notification = Nosto::helper('nosto_tagging/notification');
        $notifications = $helper_notification->getAll();
        if (is_array($notifications) && count($notifications) > 0) {
            /* @var NostoTaggingAdminNotification $notification */
            foreach ($notifications as $notification) {
                if (
                    $notification->getNotificationType() === \Nosto\Object\Notification::TYPE_MISSING_INSTALLATION
                    && !NostoTaggingHelperController::isController('AdminModules')
                ) {
                    continue;
                }
                $this->addPrestashopNotification($notification);
            }
        }
    }

    /**
     * Adds a Prestashop admin notification
     *
     * @param NostoTaggingAdminNotification $notification
     */
    protected function addPrestashopNotification(NostoTaggingAdminNotification $notification)
    {
        switch ($notification->getNotificationSeverity()) {
            case \Nosto\Object\Notification::SEVERITY_INFO:
                $this->adminDisplayInformation($notification->getFormattedMessage());
                break;
            case \Nosto\Object\Notification::SEVERITY_WARNING:
                $this->adminDisplayWarning($notification->getFormattedMessage());
                break;
            default:
        }
    }
}
