# Secomm ZaloPay Wallet for Magento 2

Read more about Zalopay: https://fintechnews.sg/10595/vietnam/vietnams-zalo-pay-brings-payments-social-media/

## Installation

##### Using Composer (we recommended)

```
composer require secomm/module-zalopay
```

## Configuration

### Setup Currency

First of all, we need to make sure our website supporting Vietnamese Dong.

Log in to Admin, **STORES > Configurations > GENERAL > Currency Setup > Currency Options > Allowed Currencies**. Make sure the Vietnamese Dong is selected.

![Zalopay Wallet currency](https://github.com/secomm/wiki/blob/master/magento/magento2/images/zalopay/zalopay-wallet-currency-01.png)

Go to Currency Rates, **STORES > Currency > Currency Rates**

![Zalopay Wallet currency](https://github.com/secomm/wiki/blob/master/magento/magento2/images/zalopay/zalopay-currency-rates-01.png)

### Config API
Log in to Admin, **STORES > Configurations > SALES > Payment Methods > Zalopay**

![Zalopay Wallet Configuration](https://github.com/secomm/wiki/blob/master/magento/magento2/images/zalopay/configuration_zalopay.png)

Read more here:

- https://docs.zalopay.vn/en/faq/#f-a-q-frequently-asked-questions_3-is-zalopay-support-sandbox-for-developer
- https://developers.zalopay.vn/docs/gateway/index.html#dang-ky-ng-d-ng

After registering Zalo Pay system will see the application the following information:
<ul>
  <li>appid : positive integer, identifier for the application during the payment process with Zalo Pay system.</li>
  <li>key1 : secret key used to create authentication data for orders </li>
  <li>key2 : the secret key used to authenticate data sent by ZaloPayServer via MerchantServer at callback.</li>
</ul>

Configuration info to integrate with MoMo API.
<ul>
   <li>Enabled: enable or disable this method.</li>
   <li>App Id: Use the info above.</li>
   <li>Key 1: Use the info above.</li>
   <li>Key 2: Use the info above.</li>
   <li>App User: Identification information of the user of the payment order application: id / username / name / phone number / email of the user. If it is not identifiable, the default information can be used, such as the application name.</li>
  <li>Sandbox Mode: when testing, we should enable this mode</li>
 </ul>
 
  ## How does it work?
  ### Checkout
 After enabling this method, go to the checkout, we can see this method.
 
 ![Zalopay Wallet Checkout]( https://github.com/secomm/wiki/blob/master/magento/magento2/images/zalopay/m2_checkout_zalopay.png)

 Zalopay Payment page:
 
 ![Zalopay Payment page](https://github.com/secomm/wiki/blob/master/magento/magento2/images/zalopay/zalo_pay_scan_qr.png)
 
 ### Purchased Successfully

  ![Zalopay Payment page](https://github.com/secomm/wiki/blob/master/magento/magento2/images/zalopay/zalopay_sucess_payment.png)

 ### Payment-first flow (since 1.1.0)

 ZaloPay is **payment-first**: a Magento Sales Order exists ONLY after the
 payment is verified server-side by ZaloPay. The flow:

 1. The customer clicks "Place Order" — the renderer saves the payment
    method on the active quote (no order) and the browser is redirected to
    the ZaloPay gateway.
 2. A `secomm_zalopay_payment_attempt` row snapshots the quote contract
    (amount, currency, fingerprint, reserved order id) and goes
    `INITIATED -> ACTIVE`.
 3. ZaloPay notifies the store **server-to-server (IPN)**. The IPN verifies
    the MAC (key2) and the amount against the snapshot, marks the attempt
    `PAID`, and the `OrderFinalizer` creates exactly ONE Sales Order —
    the customer browser is never required (closing the browser after
    paying still produces the order).
 4. The browser return verifies authoritatively via ZaloPay `v2/query`
    and recovers/rebuilds the success page (idempotent — IPN and return
    races converge to one attempt and one order).
 5. A server-side guard blocks every generic placeOrder path (REST,
    GraphQL, SOAP, stale browser sessions, one-step-checkout plugins) for
    ZaloPay quotes: no verified payment, no order. Admin order creation is
    unaffected. Abandoned/cancelled payments create no order.

Contribution
---
Want to contribute to this extension? The quickest way is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests)

Support
---
If you encounter any problems or bugs, please open an issue on [GitHub](https://github.com/secomm/zalopay/issues).

Need help settings up or want to customize this extension to meet your business needs? Please email contact@secomm.vn and if we like your idea we will add this feature for free or at a discounted rate.
