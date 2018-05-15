<?php
/*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Contactform extends Module implements WidgetInterface
{
    /** @var string */
    const SEND_CONFIRMATION_EMAIL = 'CONTACTFORM_SEND_CONFIRMATION_EMAIL';

    /** @var string */
    const SEND_NOTIFICATION_EMAIL = 'CONTACTFORM_SEND_NOTIFICATION_EMAIL';

    /** @var string */
    const MESSAGE_PLACEHOLDER_FOR_OLDER_VERSION = '(hidden)';

    /** @var string */
    const SUBMIT_NAME = 'update-configuration';

    /** @var Contact */
    protected $contact;

    /** @var CustomerThread */
    protected $customer_thread;

    public function __construct()
    {
        $this->name = 'contactform';
        $this->author = 'PrestaShop';
        $this->tab = 'front_office_features';
        $this->version = '4.0.0';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Contact form', array(), 'Modules.Contactform.Admin');
        $this->description = $this->trans('Adds a contact form to the "Contact us" page.', array(), 'Modules.Contactform.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.2.0', 'max' => _PS_VERSION_);
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install() && $this->registerHook('registerGDPRConsent');
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $message = $this->trans(
            'For even more security on your website forms, consult our Security & Access modules category on the %link%',
            array('%link%' => $this->getSecurityMarketPlaceLink()),
            'Modules.Contactform.Admin'
        );
        $html = "<div class='alert alert-info'>$message</div>";
        $html .= $this->renderForm();

        if (Tools::getValue(self::SUBMIT_NAME)) {
            Configuration::updateValue(
                self::SEND_CONFIRMATION_EMAIL,
                Tools::getValue(self::SEND_CONFIRMATION_EMAIL)
            );
            Configuration::updateValue(
                self::SEND_NOTIFICATION_EMAIL,
                Tools::getValue(self::SEND_NOTIFICATION_EMAIL)
            );
        }

        return $html;
    }

    /**
     * @return string
     */
    public function getSecurityMarketPlaceLink()
    {
        $urlsByLanguagesCodes = array(
            'FR' => 'https://addons.prestashop.com/fr/429-securite-access?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-FR&utm_content=security',
            'EN' => 'https://addons.prestashop.com/en/429-website-security-access?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-EN&utm_content=security',
            'ES' => 'https://addons.prestashop.com/es/429-seguridad-y-accesos?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-ES&utm_content=security',
            'DE' => 'https://addons.prestashop.com/de/429-sicherheit-brechtigungen?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-DE&utm_content=security',
            'IT' => 'https://addons.prestashop.com/it/429-security-access?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-IT&utm_content=security',
            'NL' => 'https://addons.prestashop.com/nl/429-veiligheid-toegang?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-NL&utm_content=security',
            'PL' => 'https://addons.prestashop.com/pl/429-bezpieczestwa-dostepu?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-PL&utm_content=security',
            'PT' => 'https://addons.prestashop.com/pt/429-seguranca-acesso?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-PT&utm_content=security',
            'RU' => 'https://addons.prestashop.com/ru/429-website-security-access?utm_source=back-office&utm_medium=native-contactform&utm_campaign=back-office-RU&utm_content=security',
        );
        $link = $urlsByLanguagesCodes['EN'];
        $languageCode = strtoupper($this->context->language->language_code);

        if (!empty($urlsByLanguagesCodes[$languageCode])) {
            $link = $urlsByLanguagesCodes[$languageCode];
        }
        $linkText = $this->trans('PrestaShop Addons Marketplace', array(), 'Admin.Modules.Feature');

        return sprintf('<a href="%1$s">%2$s</a>', $link, $linkText);
    }

    /**
     * @return string
     */
    protected function renderForm()
    {
        $fieldsValue = array(
            self::SEND_CONFIRMATION_EMAIL => Tools::getValue(
                self::SEND_CONFIRMATION_EMAIL,
                Configuration::get(self::SEND_CONFIRMATION_EMAIL)
            ),
            self::SEND_NOTIFICATION_EMAIL => Tools::getValue(
                self::SEND_NOTIFICATION_EMAIL,
                Configuration::get(self::SEND_NOTIFICATION_EMAIL)
            )
        );
        $form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Parameters', array(), 'Modules.Contactform.Admin'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Send confirmation email to your customers', array(), 'Modules.Contactform.Admin'),
                        'desc' => $this->trans("Choose Yes and your customers will receive a generic confirmation email including a tracking number after their message is sent. Note: to discourage spam, the content of their message won't be included in the email.", array(), 'Modules.Contactform.Admin'),
                        'name' => self::SEND_CONFIRMATION_EMAIL,
                        'is_bool' => true,
                        'required' => true,
                        'values' => array(
                            array(
                                'id' => self::SEND_CONFIRMATION_EMAIL . '_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ),
                            array(
                                'id' => self::SEND_CONFIRMATION_EMAIL . '_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans("Receive customers' messages by email", array(), 'Modules.Contactform.Admin'),
                        'desc' => $this->trans('By default, you will only receive contact messages through your Customer service tab.', array(), 'Modules.Contactform.Admin'),
                        'name' => self::SEND_NOTIFICATION_EMAIL,
                        'is_bool' => true,
                        'required' => true,
                        'values' => array(
                            array(
                                'id' => self::SEND_NOTIFICATION_EMAIL . '_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', array(), 'Admin.Global')
                            ),
                            array(
                                'id' => self::SEND_NOTIFICATION_EMAIL . '_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', array(), 'Admin.Global')
                            )
                        )
                    )
                ),
                'submit' => array(
                    'name' => self::SUBMIT_NAME,
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $helper = new HelperForm();
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->submit_action = 'update-configuration';
        $helper->currentIndex = $this->getModuleConfigurationPageLink();
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $fieldsValue,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($form));
    }

    /**
     * @return string
     */
    protected function getModuleConfigurationPageLink()
    {
        $parsedUrl = parse_url($this->context->link->getAdminLink('AdminModules', false));
        $urlParams = http_build_query(array(
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name
        ));

        if (!empty($parsedUrl['query'])) {
            $parsedUrl['query'] .= "&$urlParams";
        } else {
            $parsedUrl['query'] = $urlParams;
        }

        return http_build_url($parsedUrl);
    }

    /**
     * @inheritdoc
     * @param string $hookName
     * @param array $configuration
     * @return string
     */
    public function renderWidget($hookName = null, array $configuration = [])
    {
        if (!$this->active) {
            return;
        }
        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->display(__FILE__, 'views/templates/widget/contactform.tpl');
    }

    /**
     * @param string|null $hookName
     * @param array $configuration
     * @return array
     * @throws Exception
     */
    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $notifications = false;

        if (Tools::isSubmit('submitMessage')) {
            $this->sendMessage();

            if (!empty($this->context->controller->errors)) {
                $notifications['messages'] = $this->context->controller->errors;
                $notifications['nw_error'] = true;
            } elseif (!empty($this->context->controller->success)) {
                $notifications['messages'] = $this->context->controller->success;
                $notifications['nw_error'] = false;
            }
        } else if (empty($this->context->cookie->contactFormToken)
            || empty($this->context->cookie->contactFormTokenTTL)
            || $this->context->cookie->contactFormTokenTTL < time()
        ) {
            $this->createNewToken();
        }

        if (($id_customer_thread = (int)Tools::getValue('id_customer_thread'))
            && $token = Tools::getValue('token')
        ) {
            $cm = new CustomerThread($id_customer_thread);

            if ($cm->token == $token) {
                $this->customer_thread = $this->context->controller->objectPresenter->present($cm);
                $order = new Order((int)$this->customer_thread['id_order']);

                if (Validate::isLoadedObject($order)) {
                    $customer_thread['reference'] = $order->getUniqReference();
                }
            }
        }
        $this->contact['contacts'] = $this->getTemplateVarContact();
        $this->contact['message'] = html_entity_decode(Tools::getValue('message'));
        $this->contact['allow_file_upload'] = (bool) Configuration::get('PS_CUSTOMER_SERVICE_FILE_UPLOAD');

        if (!(bool)Configuration::isCatalogMode()) {
            $this->contact['orders'] = $this->getTemplateVarOrders();
        } else {
            $this->contact['orders'] = array();
        }

        if ($this->customer_thread['email']) {
            $this->contact['email'] = $this->customer_thread['email'];
        } else {
            $this->contact['email'] = Tools::safeOutput(Tools::getValue('from', ((isset($this->context->cookie) && isset($this->context->cookie->email) && Validate::isEmail($this->context->cookie->email)) ? $this->context->cookie->email : '')));
        }

        return [
            'contact' => $this->contact,
            'notifications' => $notifications,
            'token' => $this->context->cookie->contactFormToken,
            'id_module' => $this->id
        ];
    }

    /**
     * @return $this
     */
    protected function createNewToken()
    {
        $this->context->cookie->contactFormToken = md5(uniqid());
        $this->context->cookie->contactFormTokenTTL = time()+600;

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplateVarContact()
    {
        $contacts = array();
        $all_contacts = Contact::getContacts($this->context->language->id);

        foreach ($all_contacts as $one_contact_id => $one_contact) {
            $contacts[$one_contact['id_contact']] = $one_contact;
        }

        if ($this->customer_thread['id_contact']) {
            return [$contacts[$this->customer_thread['id_contact']]];
        }

        return $contacts;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getTemplateVarOrders()
    {
        $orders = array();

        if (!isset($this->customer_thread['id_order']) && $this->context->customer->isLogged()) {
            $customer_orders = Order::getCustomerOrders($this->context->customer->id);

            foreach ($customer_orders as $customer_order) {
                $myOrder = new Order((int)$customer_order['id_order']);

                if (Validate::isLoadedObject($myOrder)) {
                    $orders[$customer_order['id_order']] = $customer_order;
                    $orders[$customer_order['id_order']]['products'] = $myOrder->getProducts();
                }
            }
        } elseif ((int)$this->customer_thread['id_order'] > 0) {
            $myOrder = new Order($this->customer_thread['id_order']);

            if (Validate::isLoadedObject($myOrder)) {
                $orders[$myOrder->id] = $this->context->controller->objectPresenter->present($myOrder);
                $orders[$myOrder->id]['id_order'] = $myOrder->id;
                $orders[$myOrder->id]['products'] = $myOrder->getProducts();
            }
        }

        if ($this->customer_thread['id_product']) {
            $id_order = 0;

            if (isset($this->customer_thread['id_order'])) {
                $id_order = (int)$this->customer_thread['id_order'];
            }
            $orders[$id_order]['products'][(int)$this->customer_thread['id_product']] = $this->context->controller->objectPresenter->present(new Product((int)$this->customer_thread['id_product']));
        }

        return $orders;
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function sendMessage()
    {
        $extension = array('.txt', '.rtf', '.doc', '.docx', '.pdf', '.zip', '.png', '.jpeg', '.gif', '.jpg');
        $file_attachment = Tools::fileAttachment('fileUpload');
        $message = trim(Tools::getValue('message'));
        $url = Tools::getValue('url');
        $clientToken = Tools::getValue('token');
        $serverToken = $this->context->cookie->contactFormToken;
        $clientTokenTTL = $this->context->cookie->contactFormTokenTTL;

        if (!($from = trim(Tools::getValue('from'))) || !Validate::isEmail($from)) {
            $this->context->controller->errors[] = $this->trans('Invalid email address.', array(), 'Shop.Notifications.Error');
        } elseif (empty($message)) {
            $this->context->controller->errors[] = $this->trans('The message cannot be blank.', array(), 'Shop.Notifications.Error');
        } elseif (!Validate::isCleanHtml($message)) {
            $this->context->controller->errors[] = $this->trans('Invalid message', array(), 'Shop.Notifications.Error');
        } elseif (!($id_contact = (int)Tools::getValue('id_contact')) || !(Validate::isLoadedObject($contact = new Contact($id_contact, $this->context->language->id)))) {
            $this->context->controller->errors[] = $this->trans('Please select a subject from the list provided. ', array(), 'Modules.Contactform.Shop');
        } elseif (!empty($file_attachment['name']) && $file_attachment['error'] != 0) {
            $this->context->controller->errors[] = $this->trans('An error occurred during the file-upload process.', array(), 'Modules.Contactform.Shop');
        } elseif (!empty($file_attachment['name']) && !in_array(Tools::strtolower(substr($file_attachment['name'], -4)), $extension) && !in_array(Tools::strtolower(substr($file_attachment['name'], -5)), $extension)) {
            $this->context->controller->errors[] = $this->trans('Bad file extension', array(), 'Modules.Contactform.Shop');
        } elseif ($url !== ''
            || empty($serverToken)
            || $clientToken !== $serverToken
            || $clientTokenTTL < time()
        ) {
            $this->context->controller->errors[] = $this->trans('An error occurred while sending the message, please try again.', array(), 'Modules.Contactform.Shop');
            $this->createNewToken();
        } else {
            $customer = $this->context->customer;

            if (!$customer->id) {
                $customer->getByEmail($from);
            }
            $id_order = (int)Tools::getValue('id_order');
            $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($from, $id_order);

            if ($contact->customer_service) {
                if ((int)$id_customer_thread) {
                    $ct = new CustomerThread($id_customer_thread);
                    $ct->status = 'open';
                    $ct->id_lang = (int)$this->context->language->id;
                    $ct->id_contact = (int)$id_contact;
                    $ct->id_order = (int)$id_order;

                    if ($id_product = (int)Tools::getValue('id_product')) {
                        $ct->id_product = $id_product;
                    }
                    $ct->update();
                } else {
                    $ct = new CustomerThread();
                    if (isset($customer->id)) {
                        $ct->id_customer = (int)$customer->id;
                    }
                    $ct->id_shop = (int)$this->context->shop->id;
                    $ct->id_order = (int)$id_order;

                    if ($id_product = (int)Tools::getValue('id_product')) {
                        $ct->id_product = $id_product;
                    }
                    $ct->id_contact = (int)$id_contact;
                    $ct->id_lang = (int)$this->context->language->id;
                    $ct->email = $from;
                    $ct->status = 'open';
                    $ct->token = Tools::passwdGen(12);
                    $ct->add();
                }

                if ($ct->id) {
                    $lastMessage = CustomerMessage::getLastMessageForCustomerThread($ct->id);
                    $testFileUpload = (isset($file_attachment['rename']) && !empty($file_attachment['rename']));

                    // if last message is the same as new message (and no file upload), do not consider this contact
                    if ($lastMessage != $message || $testFileUpload) {
                        $cm = new CustomerMessage();
                        $cm->id_customer_thread = $ct->id;
                        $cm->message = $message;

                        if ($testFileUpload && rename($file_attachment['tmp_name'], _PS_UPLOAD_DIR_ . basename($file_attachment['rename']))) {
                            $cm->file_name = $file_attachment['rename'];
                            @chmod(_PS_UPLOAD_DIR_ . basename($file_attachment['rename']), 0664);
                        }
                        $cm->ip_address = (int)ip2long(Tools::getRemoteAddr());
                        $cm->user_agent = $_SERVER['HTTP_USER_AGENT'];

                        if (!$cm->add()) {
                            $this->context->controller->errors[] = $this->trans('An error occurred while sending the message.', array(), 'Modules.Contactform.Shop');
                        }
                    } else {
                        $mailAlreadySend = true;
                    }
                } else {
                    $this->context->controller->errors[] = $this->trans('An error occurred while sending the message.', array(), 'Modules.Contactform.Shop');
                }
            }
            $sendConfirmationEmail = Configuration::get(self::SEND_CONFIRMATION_EMAIL);
            $sendNotificationEmail = Configuration::get(self::SEND_NOTIFICATION_EMAIL);

            if (!count($this->context->controller->errors)
                && empty($mailAlreadySend)
                && ($sendConfirmationEmail || $sendNotificationEmail)
            ) {
                $var_list = [
                    '{order_name}' => '-',
                    '{attached_file}' => '-',
                    '{message}' => Tools::nl2br(stripslashes($message)),
                    '{email}' =>  $from,
                    '{product_name}' => '',
                ];

                if (isset($file_attachment['name'])) {
                    $var_list['{attached_file}'] = $file_attachment['name'];
                }
                $id_product = (int)Tools::getValue('id_product');

                if (isset($ct) && Validate::isLoadedObject($ct) && $ct->id_order) {
                    $order = new Order((int)$ct->id_order);
                    $var_list['{order_name}'] = $order->getUniqReference();
                    $var_list['{id_order}'] = (int)$order->id;
                }

                if ($id_product) {
                    $product = new Product((int)$id_product);

                    if (Validate::isLoadedObject($product) && isset($product->name[Context::getContext()->language->id])) {
                        $var_list['{product_name}'] = $product->name[Context::getContext()->language->id];
                    }
                }

                if ($sendNotificationEmail) {
                    if (empty($contact->email) || !Mail::Send(
                        $this->context->language->id,
                        'contact',
                        $this->trans('Message from contact form', array(), 'Emails.Subject').' [no_sync]',
                        $var_list,
                        $contact->email,
                        $contact->name,
                        null,
                        null,
                        $file_attachment,
                        null,
                        _PS_MAIL_DIR_,
                        false,
                        null,
                        null,
                        $from
                    )) {
                        $this->context->controller->errors[] = $this->trans(
                            'An error occurred while sending the message.',
                            array(),
                            'Modules.Contactform.Shop'
                        );
                    }
                }

                if ($sendConfirmationEmail) {
                    $var_list['{message}'] = self::MESSAGE_PLACEHOLDER_FOR_OLDER_VERSION;

                    if (!Mail::Send(
                        $this->context->language->id,
                        'contact_form',
                        ((isset($ct) && Validate::isLoadedObject($ct)) ? $this->trans('Your message has been correctly sent #ct%thread_id% #tc%thread_token%', array('%thread_id%' => $ct->id, '%thread_token%' => $ct->token), 'Emails.Subject') : $this->trans('Your message has been correctly sent', array(), 'Emails.Subject')),
                        $var_list,
                        $from,
                        null,
                        null,
                        null,
                        $file_attachment,
                        null,
                        _PS_MAIL_DIR_,
                        false,
                        null,
                        null,
                        $contact->email
                    )) {
                        $this->context->controller->errors[] = $this->trans(
                            'An error occurred while sending the message.',
                            array(),
                            'Modules.Contactform.Shop'
                        );
                    }
                }
            }

            if (!count($this->context->controller->errors)) {
                $this->context->controller->success[] = $this->trans(
                    'Your message has been successfully sent to our team.',
                    array(),
                    'Modules.Contactform.Shop'
                );
            }
        }
    }
}
