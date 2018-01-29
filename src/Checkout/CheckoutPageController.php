<?php

namespace SilverShop\Core\Checkout;

use PageController;

/**
 * @package shop
 * @mixin CheckoutPage
 * @mixin SteppedCheckout
 * @mixin CheckoutStep_Address
 * @mixin CheckoutStep_AddressBook
 * @mixin CheckoutStep_ContactDetails
 * @mixin CheckoutStep_Membership
 * @mixin CheckoutStep_PaymentMethod
 * @mixin CheckoutStep_Summary
 */
class CheckoutPageController extends PageController
{
    private static $url_segment     = 'checkout';

    private static $allowed_actions = array(
        'OrderForm',
        'payment',
        'PaymentForm',
    );

    public function Title()
    {
        if ($this->failover && $this->failover->Title) {
            return $this->failover->Title;
        }

        return _t('CheckoutPage.DefaultTitle', "Checkout");
    }

    public function OrderForm()
    {
        if (!(bool)$this->Cart()) {
            return false;
        }

        /** @var CheckoutComponentConfig $config */
        $config = Injector::inst()->create("CheckoutComponentConfig", ShoppingCart::curr());
        $form = PaymentForm::create($this, 'OrderForm', $config);

        // Normally, the payment is on a second page, either offsite or through /checkout/payment
        // If the site has customised the checkout component config to include an onsite payment
        // component, we should honor that and change the button label. PaymentForm::checkoutSubmit
        // will also check this and process payment if needed.
        if ($config->getComponentByType('OnsitePaymentCheckoutComponent')) {
            $form->setActions(
                FieldList::create(
                    FormAction::create('checkoutSubmit', _t('CheckoutPage.SubmitPayment', 'Submit Payment'))
                )
            );
        }

        $form->Cart = $this->Cart();
        $this->extend('updateOrderForm', $form);

        return $form;
    }

    /**
     * Action for making on-site payments
     */
    public function payment()
    {
        if (!$this->Cart()) {
            return $this->redirect($this->Link());
        }

        return array(
            'Title'     => 'Make Payment',
            'OrderForm' => $this->PaymentForm(),
        );
    }

    public function PaymentForm()
    {
        if (!(bool)$this->Cart()) {
            return false;
        }

        $config = new CheckoutComponentConfig(ShoppingCart::curr(), false);
        $config->addComponent(OnsitePaymentCheckoutComponent::create());

        $form = PaymentForm::create($this, "PaymentForm", $config);

        $form->setActions(
            FieldList::create(
                FormAction::create("submitpayment", _t('CheckoutPage.SubmitPayment', "Submit Payment"))
            )
        );

        $form->setFailureLink($this->Link());
        $this->extend('updatePaymentForm', $form);

        return $form;
    }

    /**
     * Retrieves error messages for the latest payment (if existing).
     * This can originate e.g. from an earlier offsite gateway API response.
     *
     * @return string
     */
    public function PaymentErrorMessage()
    {
        $order = $this->Cart();
        if (!$order) {
            return false;
        }

        $lastPayment = $order->Payments()->sort('Created', 'DESC')->first();
        if (!$lastPayment) {
            return false;
        }

        $errorMessages = $lastPayment->Messages()->exclude('Message', '')->sort('Created', 'DESC');
        $lastErrorMessage = null;
        foreach ($errorMessages as $errorMessage) {
            if ($errorMessage instanceof GatewayErrorMessage) {
                $lastErrorMessage = $errorMessage;
                break;
            }
        }
        if (!$lastErrorMessage) {
            return false;
        }

        return $lastErrorMessage->Message;
    }
}