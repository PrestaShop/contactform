<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
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

    /** @var array */
    protected $customer_thread;

    public function __construct()
    {
        $this->name = 'contactform';
        $this->author = 'PrestaShop';
        $this->tab = 'front_office_features';
        $this->version = '4.4.2';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Contact form', [], 'Modules.Contactform.Admin');
        $this->description = $this->trans(
            'Help your customers get in touch when they need, add a contact form on your store.',
            [],
            'Modules.Contactform.Admin'
        );
        $this->ps_versions_compliancy = [
            'min' => '1.7.2.0',
            'max' => _PS_VERSION_,
        ];
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install() && $this->registerHook(['registerGDPRConsent', 'displayContactContent']);
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $html = $this->renderForm();

        if (Tools::getValue(self::SUBMIT_NAME)) {
            Configuration::updateValue(
                self::SEND_CONFIRMATION_EMAIL,
                Tools::getValue(self::SEND_CONFIRMATION_EMAIL)
            );
            Configuration::updateValue(
                self::SEND_NOTIFICATION_EMAIL,
                Tools::getValue(self::SEND_NOTIFICATION_EMAIL)
            );

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&conf=6');
        }

        return $html;
    }

    /**
     * @return string
     */
    protected function renderForm()
    {
        $fieldsValue = [
            self::SEND_CONFIRMATION_EMAIL => Tools::getValue(
                self::SEND_CONFIRMATION_EMAIL,
                Configuration::get(self::SEND_CONFIRMATION_EMAIL)
            ),
            self::SEND_NOTIFICATION_EMAIL => Tools::getValue(
                self::SEND_NOTIFICATION_EMAIL,
                Configuration::get(self::SEND_NOTIFICATION_EMAIL)
            ),
        ];
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Parameters', [], 'Modules.Contactform.Admin'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans(
                            'Send confirmation email to your customers',
                            [],
                            'Modules.Contactform.Admin'
                        ),
                        'desc' => $this->trans(
                            "Choose Yes and your customers will receive a generic confirmation email including a tracking number after their message is sent. Note: to discourage spam, the content of their message won't be included in the email.",
                            [],
                            'Modules.Contactform.Admin'
                        ),
                        'name' => self::SEND_CONFIRMATION_EMAIL,
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => self::SEND_CONFIRMATION_EMAIL . '_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => self::SEND_CONFIRMATION_EMAIL . '_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans(
                            "Receive customers' messages by email",
                            [],
                            'Modules.Contactform.Admin'
                        ),
                        'desc' => $this->trans(
                            'By default, you will only receive contact messages through your Customer service tab.',
                            [],
                            'Modules.Contactform.Admin'
                        ),
                        'name' => self::SEND_NOTIFICATION_EMAIL,
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => self::SEND_NOTIFICATION_EMAIL . '_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => self::SEND_NOTIFICATION_EMAIL . '_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'name' => self::SUBMIT_NAME,
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];
        $helper = new HelperForm();
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->submit_action = 'update-configuration';
        $helper->currentIndex = $this->getModuleConfigurationPageLink();
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $fieldsValue,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * @return string
     */
    protected function getModuleConfigurationPageLink()
    {
        $parsedUrl = parse_url($this->context->link->getAdminLink('AdminModules', false));

        $urlParams = http_build_query(
            [
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ]
        );

        if (!empty($parsedUrl['query'])) {
            $parsedUrl['query'] .= "&$urlParams";
        } else {
            $parsedUrl['query'] = $urlParams;
        }

        /*
         * http_build_query function is available through composer package jakeasmith/http_build_url
         */
        return http_build_url($parsedUrl);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $notifications = [];

        if (Tools::isSubmit('submitMessage')) {
            $this->sendMessage();

            $notifications = [];
            if (!empty($this->context->controller->errors)) {
                $notifications['messages'] = $this->context->controller->errors;
                $notifications['nw_error'] = true;
            } elseif (!empty($this->context->controller->success)) {
                $notifications['messages'] = $this->context->controller->success;
                $notifications['nw_error'] = false;
            }
        } elseif (empty($this->context->cookie->contactFormToken)
            || empty($this->context->cookie->contactFormTokenTTL)
            || $this->context->cookie->contactFormTokenTTL < time()
        ) {
            $this->createNewToken();
        }

        if (($id_customer_thread = (int) Tools::getValue('id_customer_thread'))
            && $token = Tools::getValue('token')
        ) {
            $cm = new CustomerThread($id_customer_thread);

            if ($cm->token == $token) {
                $this->customer_thread = $this->context->controller->objectPresenter->present($cm);
            }
        }
        $this->contact['contacts'] = $this->getTemplateVarContact();
        $this->contact['message'] = Tools::getValue('message');
        $this->contact['allow_file_upload'] = (bool) Configuration::get('PS_CUSTOMER_SERVICE_FILE_UPLOAD');

        if (!(bool) Configuration::isCatalogMode()) {
            $this->contact['orders'] = $this->getTemplateVarOrders();
        } else {
            $this->contact['orders'] = [];
        }

        if (isset($this->customer_thread['email'])) {
            $this->contact['email'] = $this->customer_thread['email'];
        } else {
            $this->contact['email'] = Tools::safeOutput(
                Tools::getValue(
                    'from',
                    !empty($this->context->cookie->email) && Validate::isEmail($this->context->cookie->email) ?
                    $this->context->cookie->email :
                    ''
                )
            );
        }

        return [
            'contact' => $this->contact,
            'notifications' => $notifications,
            'token' => $this->context->cookie->contactFormToken,
            'id_module' => $this->id,
        ];
    }

    /**
     * @return $this
     */
    protected function createNewToken()
    {
        $this->context->cookie->contactFormToken = md5(uniqid());
        $this->context->cookie->contactFormTokenTTL = time() + 600;

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplateVarContact()
    {
        $contacts = [];
        $all_contacts = Contact::getContacts($this->context->language->id);

        foreach ($all_contacts as $one_contact) {
            $contacts[$one_contact['id_contact']] = $one_contact;
        }

        if (!empty($this->customer_thread['id_contact'])) {
            return [
                $contacts[$this->customer_thread['id_contact']],
            ];
        }

        return $contacts;
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    public function getTemplateVarOrders()
    {
        $orders = [];

        if (empty($this->customer_thread['id_order'])
            && isset($this->context->customer)
            && $this->context->customer->isLogged()
        ) {
            $customer_orders = Order::getCustomerOrders($this->context->customer->id);

            foreach ($customer_orders as $customer_order) {
                $myOrder = new Order((int) $customer_order['id_order']);

                if (Validate::isLoadedObject($myOrder)) {
                    $orders[$customer_order['id_order']] = $customer_order;
                    $orders[$customer_order['id_order']]['products'] = $myOrder->getProducts();
                }
            }
        } elseif (isset($this->customer_thread['id_order']) && (int) $this->customer_thread['id_order'] > 0) {
            $myOrder = new Order($this->customer_thread['id_order']);

            if (Validate::isLoadedObject($myOrder)) {
                $orders[$myOrder->id] = $this->context->controller->objectPresenter->present($myOrder);
                $orders[$myOrder->id]['id_order'] = $myOrder->id;
                $orders[$myOrder->id]['products'] = $myOrder->getProducts();
            }
        }

        if (!empty($this->customer_thread['id_product'])) {
            $id_order = isset($this->customer_thread['id_order']) ?
                      (int) $this->customer_thread['id_order'] :
                      0;

            $orders[$id_order]['products'][(int) $this->customer_thread['id_product']] = $this->context->controller->objectPresenter->present(
                new Product((int) $this->customer_thread['id_product'])
            );
        }

        return $orders;
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function sendMessage()
    {
        $extension = ['.txt', '.rtf', '.doc', '.docx', '.pdf', '.zip', '.png', '.jpeg', '.gif', '.jpg', '.webp'];
        $file_attachment = Tools::fileAttachment('fileUpload');
        $message = trim(Tools::getValue('message'));
        $url = Tools::getValue('url');
        $clientToken = Tools::getValue('token');
        $serverToken = $this->context->cookie->contactFormToken;
        $clientTokenTTL = $this->context->cookie->contactFormTokenTTL;

        if (!($from = trim(Tools::getValue('from'))) || !Validate::isEmail($from)) {
            $this->context->controller->errors[] = $this->trans(
                'Invalid email address.',
                [],
                'Shop.Notifications.Error'
            );

            return;
        }
        if (empty($message)) {
            $this->context->controller->errors[] = $this->trans(
                'The message cannot be blank.',
                [],
                'Shop.Notifications.Error'
            );

            return;
        }
        if (!Validate::isCleanHtml($message)) {
            $this->context->controller->errors[] = $this->trans(
                'Invalid message',
                [],
                'Shop.Notifications.Error'
            );

            return;
        }

        $id_contact = (int) Tools::getValue('id_contact');
        $contact = new Contact($id_contact, $this->context->language->id);

        if (!$id_contact || !(Validate::isLoadedObject($contact))) {
            $this->context->controller->errors[] = $this->trans(
                'Please select a subject from the list provided. ',
                [],
                'Modules.Contactform.Shop'
            );

            return;
        }

        if (!empty($file_attachment['name']) && $file_attachment['error'] != 0) {
            $this->context->controller->errors[] = $this->trans(
                'An error occurred during the file-upload process.',
                [],
                'Modules.Contactform.Shop'
            );

            return;
        }
        if (!empty($file_attachment['name']) &&
                  !in_array(Tools::strtolower(Tools::substr($file_attachment['name'], -4)), $extension) &&
                  !in_array(Tools::strtolower(Tools::substr($file_attachment['name'], -5)), $extension)
        ) {
            $this->context->controller->errors[] = $this->trans(
                'Bad file extension',
                [],
                'Modules.Contactform.Shop'
            );

            return;
        }
        if ($url !== ''
            || empty($serverToken)
            || $clientToken !== $serverToken
            || $clientTokenTTL < time()
        ) {
            $this->context->controller->errors[] = $this->trans(
                'An error occurred while sending the message, please try again.',
                [],
                'Modules.Contactform.Shop'
            );
            $this->createNewToken();

            return;
        }

        $customer = $this->context->customer;

        if (!$customer->id) {
            $customer->getByEmail($from);
        }

        /**
         * Check that the order belongs to the customer.
         */
        $id_order = (int) Tools::getValue('id_order');
        if (!empty($id_order)) {
            $order = new Order($id_order);
            $id_order = (int) $order->id_customer === (int) $customer->id ? $id_order : 0;
        }

        $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($from, $id_order);

        if ($contact->customer_service) {
            if ((int) $id_customer_thread) {
                $ct = new CustomerThread($id_customer_thread);
                $ct->status = 'open';
                $ct->id_lang = (int) $this->context->language->id;
                $ct->id_contact = (int) $id_contact;
                $ct->id_order = $id_order;

                if ($id_product = (int) Tools::getValue('id_product')) {
                    $ct->id_product = $id_product;
                }
                $ct->update();
            } else {
                $ct = new CustomerThread();
                if (isset($customer->id)) {
                    $ct->id_customer = (int) $customer->id;
                }
                $ct->id_shop = (int) $this->context->shop->id;
                $ct->id_order = $id_order;

                if ($id_product = (int) Tools::getValue('id_product')) {
                    $ct->id_product = $id_product;
                }
                $ct->id_contact = (int) $id_contact;
                $ct->id_lang = (int) $this->context->language->id;
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
                    $cm->ip_address = (string) ip2long(Tools::getRemoteAddr());
                    $cm->user_agent = $_SERVER['HTTP_USER_AGENT'];

                    if (!$cm->add()) {
                        $this->context->controller->errors[] = $this->trans(
                            'An error occurred while sending the message.',
                            [],
                            'Modules.Contactform.Shop'
                        );
                    }
                } else {
                    $mailAlreadySend = true;
                }
            } else {
                $this->context->controller->errors[] = $this->trans(
                    'An error occurred while sending the message.',
                    [],
                    'Modules.Contactform.Shop'
                );
            }
        }
        $sendConfirmationEmail = Configuration::get(self::SEND_CONFIRMATION_EMAIL);
        $sendNotificationEmail = Configuration::get(self::SEND_NOTIFICATION_EMAIL);

        if (!count($this->context->controller->errors)
            && empty($mailAlreadySend)
            && ($sendConfirmationEmail || $sendNotificationEmail)
        ) {
            $message = version_compare(_PS_VERSION_, '8.0.0', '>=') ? stripslashes($message) : Tools::stripslashes($message);
            $var_list = [
                '{firstname}' => '',
                '{lastname}' => '',
                '{order_name}' => '-',
                '{attached_file}' => '-',
                '{message}' => Tools::nl2br(Tools::htmlentitiesUTF8($message)),
                '{email}' => $from,
                '{product_name}' => '',
            ];

            if (isset($customer->id)) {
                $var_list['{firstname}'] = $customer->firstname;
                $var_list['{lastname}'] = $customer->lastname;
            }

            if (isset($file_attachment['name'])) {
                $var_list['{attached_file}'] = $file_attachment['name'];
            }
            $id_product = (int) Tools::getValue('id_product');

            if ($id_order) {
                $order = new Order((int) $id_order);
                $var_list['{order_name}'] = $order->getUniqReference();
                $var_list['{id_order}'] = (int) $order->id;
            }

            if ($id_product) {
                $product = new Product((int) $id_product);

                if (Validate::isLoadedObject($product) &&
                    isset($product->name[Context::getContext()->language->id])
                ) {
                    $var_list['{product_name}'] = $product->name[Context::getContext()->language->id];
                }
            }

            if ($sendNotificationEmail) {
                if (empty($contact->email) || !Mail::Send(
                    $this->context->language->id,
                    'contact',
                    $this->trans('Message from contact form', [], 'Emails.Subject') . ' [no_sync]',
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
                        [],
                        'Modules.Contactform.Shop'
                    );
                }
            }

            if ($sendConfirmationEmail) {
                $var_list['{message}'] = self::MESSAGE_PLACEHOLDER_FOR_OLDER_VERSION;

                if (!Mail::Send(
                    $this->context->language->id,
                    'contact_form',
                    ((isset($ct) && Validate::isLoadedObject($ct)) ? $this->trans(
                        'Your message has been correctly sent #ct%thread_id% #tc%thread_token%',
                        [
                            '%thread_id%' => $ct->id,
                            '%thread_token%' => $ct->token,
                        ],
                        'Emails.Subject'
                    ) : $this->trans('Your message has been correctly sent', [], 'Emails.Subject')),
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
                        [],
                        'Modules.Contactform.Shop'
                    );
                }
            }
        }

        if (!count($this->context->controller->errors)) {
            $this->context->controller->success[] = $this->trans(
                'Your message has been successfully sent to our team.',
                [],
                'Modules.Contactform.Shop'
            );
        }
    }

    /**
     * empty listener for registerGDPRConsent hook
     */
    public function hookRegisterGDPRConsent()
    {
        /* registerGDPRConsent is a special kind of hook that doesn't need a listener, see :
           https://build.prestashop.com/howtos/module/how-to-make-your-module-compliant-with-prestashop-official-gdpr-compliance-module/
          However since Prestashop 1.7.8, modules must implement a listener for all the hooks they register: a check is made
          at module installation.
        */
    }
}
