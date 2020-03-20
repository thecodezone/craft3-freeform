<?php

namespace Solspace\Freeform\Integrations\PaymentGateways;

use craft\helpers\UrlHelper;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Composer\Components\Properties\PaymentProperties;
use Solspace\Freeform\Library\DataObjects\PaymentDetails;
use Solspace\Freeform\Library\DataObjects\PlanDetails;
use Solspace\Freeform\Library\DataObjects\SubscriptionDetails;
use Solspace\Freeform\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;
use Solspace\Freeform\Library\Integrations\IntegrationStorageInterface;
use Solspace\Freeform\Library\Integrations\PaymentGateways\AbstractPaymentGatewayIntegration;
use Solspace\Freeform\Library\Integrations\SettingBlueprint;
use Solspace\Freeform\Library\Logging\FreeformLogger;
use Solspace\Freeform\Library\Payments\PaymentInterface;
use Solspace\Freeform\Models\Pro\Payments\PaymentModel;
use Solspace\Freeform\Models\Pro\Payments\SubscriptionModel;
use Solspace\Freeform\Models\Pro\Payments\SubscriptionPlanModel;
use Solspace\Freeform\Records\Pro\Payments\PaymentRecord;
use Solspace\Freeform\Services\Pro\Payments\PaymentsService;
use Solspace\Freeform\Services\Pro\Payments\SubscriptionPlansService;
use Solspace\Freeform\Services\Pro\Payments\SubscriptionsService;
use Stripe as StripeAPI;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\CardException as Card;
use Stripe\Exception\InvalidRequestException as InvalidRequest;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use function strtolower;

class Stripe extends AbstractPaymentGatewayIntegration
{
    const SETTING_PUBLIC_KEY_LIVE = 'public_key_live';
    const SETTING_SECRET_KEY_LIVE = 'secret_key_live';
    const SETTING_PUBLIC_KEY_TEST = 'public_key_test';
    const SETTING_SECRET_KEY_TEST = 'secret_key_test';
    const SETTING_LIVE_MODE       = 'live_mode';
    const SETTING_WEBHOOK_KEY     = 'webhook_key';

    const TITLE        = 'Stripe';
    const LOG_CATEGORY = 'Stripe';

    const PRODUCT_TYPE_SERVICE = 'service';
    const PRODUCT_TYPE_GOOD    = 'good';

    const ZERO_DECIMAL_CURRENCIES = [
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF',
    ];

    const PLAN_INTERVAL_CONVERSION = [
        PaymentProperties::PLAN_INTERVAL_DAILY    => ['interval' => 'day', 'count' => 1],
        PaymentProperties::PLAN_INTERVAL_WEEKLY   => ['interval' => 'week', 'count' => 1],
        PaymentProperties::PLAN_INTERVAL_BIWEEKLY => ['interval' => 'week', 'count' => 2],
        PaymentProperties::PLAN_INTERVAL_MONTHLY  => ['interval' => 'month', 'count' => 1],
        PaymentProperties::PLAN_INTERVAL_ANNUALLY => ['interval' => 'year', 'count' => 1],
    ];

    /** @var \Exception */
    protected $lastError = null;

    public static function toStripeAmount($amount, $currency)
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES)) {
            return $amount;
        }

        return ceil($amount * 100);
    }

    public static function fromStripeAmount($amount, $currency)
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES)) {
            return $amount;
        }

        return $amount * 0.01;
    }

    public static function fromStripeInterval($interval, $intervalCount)
    {
        $stripeInterval = ['interval' => $interval, 'count' => $intervalCount];

        return array_search($stripeInterval, self::PLAN_INTERVAL_CONVERSION);
    }

    /**
     * Returns a list of additional settings for this integration
     * Could be used for anything, like - AccessTokens
     *
     * @return SettingBlueprint[]
     */
    public static function getSettingBlueprints(): array
    {
        return [
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_PUBLIC_KEY_LIVE,
                'Public Key (Live)',
                'Enter your Stripe LIVE public key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_SECRET_KEY_LIVE,
                'Secret Key (Live)',
                'Enter your Stripe LIVE secret key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_PUBLIC_KEY_TEST,
                'Public Key (Test)',
                'Enter your Stripe TEST public key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_SECRET_KEY_TEST,
                'Secret Key (Test)',
                'Enter your Stripe TEST secret key here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_BOOL,
                self::SETTING_LIVE_MODE,
                'LIVE mode',
                'Enable this to start using LIVE public and secret keys.',
                false
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_WEBHOOK_KEY,
                'Webhook Secret',
                'Enter your Stripe webhook secret here.',
                false
            ),
        ];
    }

    public function getWebhookUrl(): string
    {
        if (!$this->getId()) {
            return '';
        }

        $url = UrlHelper::actionUrl('freeform/payment-webhooks/stripe?id=' . $this->getId());
        $url = str_replace(\Craft::$app->config->general->cpTrigger . '/', '', $url);

        return $url;
    }

    /**
     * Check if it's possible to connect to the API
     *
     * @return bool
     */
    public function checkConnection(): bool
    {
        $this->prepareApi();

        try {
            $charges = StripeAPI\Charge::all(['limit' => 1]);
        } catch (\Exception $e) {
            throw new IntegrationException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $charges instanceof StripeAPI\Collection;
    }

    /**
     * Authorizes the application
     * Returns the access_token
     *
     * @return string
     * @throws IntegrationException
     */
    public function fetchAccessToken(): string
    {
        return $this->getSetting(
            $this->isLiveMode() ? self::SETTING_SECRET_KEY_LIVE : self::SETTING_SECRET_KEY_TEST
        );
    }

    /**
     * A method that initiates the authentication
     */
    public function initiateAuthentication()
    {
    }

    /**
     * @return array
     */
    public function fetchFields(): array
    {
        return [
            new FieldObject('name', 'Full Name', FieldObject::TYPE_STRING, false),
            new FieldObject('first_name', 'First  Name', FieldObject::TYPE_STRING, false),
            new FieldObject('last_name', 'Last Name', FieldObject::TYPE_STRING, false),
            new FieldObject('email', 'Email', FieldObject::TYPE_STRING, false),
            new FieldObject('phone', 'Phone', FieldObject::TYPE_STRING, false),
            new FieldObject('line1', 'Address #1', FieldObject::TYPE_STRING, false),
            new FieldObject('line2', 'Address #2', FieldObject::TYPE_STRING, false),
            new FieldObject('city', 'City', FieldObject::TYPE_STRING, false),
            new FieldObject('state', 'State', FieldObject::TYPE_STRING, false),
            new FieldObject('postal_code', 'Zip', FieldObject::TYPE_STRING, false),
            new FieldObject('country', 'Country', FieldObject::TYPE_STRING, false),
        ];
    }

    /**
     * Creates payment plan
     *
     * @param PlanDetails $plan
     *
     * @return string|false
     */
    public function createPlan(PlanDetails $plan)
    {
        $this->prepareApi();

        $interval  = self::PLAN_INTERVAL_CONVERSION[strtolower($plan->getInterval())];
        $hash      = $plan->getFormHash();
        $productId = 'freeform' . ($hash ? '_' . $hash : '');

        $product = $this->fetchProduct($productId);
        if ($product === false) {
            return false;
        }

        if ($product) {
            $product = $productId;
        } else {
            //TODO: allow for customization
            $product = [
                'name' => 'Freeform' . ($plan->getFormName() ? ': ' . $plan->getFormName() : ' Plans'),
                'id'   => $productId,
            ];
        }

        $params = [
            'id'             => $plan->getId(),
            'nickname'       => $plan->getName(),
            'amount'         => self::toStripeAmount($plan->getAmount(), $plan->getCurrency()),
            'currency'       => strtolower($plan->getCurrency()),
            'interval'       => $interval['interval'],
            'interval_count' => $interval['count'],
            'product'        => $product,
        ];

        try {
            $data = StripeAPI\Plan::create($params);

            $planHandler = $this->getPlanHandler();
            $model       = $planHandler->getByResourceId($data['id'], $this->getId());

            if ($model == null) {
                $model                = new SubscriptionPlanModel();
                $model->integrationId = $this->getId();
                $model->resourceId    = $data['id'];
            }
            $model->name = $data['nickname'];

            $planHandler->save($model);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $data['id'];
    }

    public function fetchProduct($id)
    {
        $product = null;
        $this->prepareApi();
        try {
            $product = StripeAPI\Product::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $product;
    }

    /**
     * @param PaymentDetails    $paymentDetails
     * @param PaymentProperties $paymentProperties
     *
     * @return bool|false|mixed|PaymentModel
     */
    public function processPayment(PaymentDetails $paymentDetails, PaymentProperties $paymentProperties)
    {
        $submissionId = $paymentDetails->getSubmission()->getId();

        $data = $this->processPaymentIntent($paymentDetails->getToken(), $paymentDetails);
        if (!$data) {
            $this->savePayment([], $submissionId);

            return false;
        }

        $data->amount = self::fromStripeAmount($data->amount, $data->currency);

        return $this->savePayment($data, $submissionId);
    }

    /**
     * @param SubscriptionDetails $subscriptionDetails
     * @param PaymentProperties   $paymentProperties
     *
     * @return bool|false|mixed|SubscriptionModel
     * @throws \Exception
     */
    public function processSubscription(SubscriptionDetails $subscriptionDetails, PaymentProperties $paymentProperties)
    {
        $this->prepareApi();

        $token        = $subscriptionDetails->getToken();
        $submissionId = $subscriptionDetails->getSubmission()->getId();

        $subscription = $customer = null;
        try {
            $subscription = Subscription::retrieve($token, [
                'expand' => [
                    'latest_invoice.payment_intent',
                    'plan',
                ],
            ]);

            $customer = $subscription->customer;
        } catch (\Exception $e) {
            $this->processError($e);
        }

        if ($customer !== false) {
            if ($subscription === false) {
                $this->saveSubscription([], $submissionId, null);

                return false;
            }

            $plan                         = $subscription->plan;
            $subscription->plan->amount   = self::fromStripeAmount($plan->amount, $plan->currency);
            $subscription->plan->interval = self::fromStripeInterval($plan->interval, $plan->interval_count);

            //TODO: log if this fails
            //we need to save it immediately or we risk hitting webhooks without available record in DB
            $model = $this->saveSubscription($subscription, $submissionId, $plan->id);

            try {
                $handler = $this->getSubscriptionHandler();

                /** @var Invoice $invoice */
                $invoice = $subscription->latest_invoice;
                if (!$invoice instanceof Invoice) {
                    $invoice = Invoice::retrieve($invoice);
                }

                /** @var PaymentIntent $paymentIntent */
                $paymentIntentId = $invoice->payment_intent;
                $paymentIntent   = PaymentIntent::update(
                    $paymentIntentId,
                    ['metadata' => ['subscription' => $subscription['id']]]
                );

                Subscription::update(
                    $subscription->id,
                    ['metadata' => ['submission' => $submissionId]]
                );

                /** @var Charge $charge */
                $charge = array_pop($paymentIntent->charges->data);
                $last4  = null;
                if ($charge) {
                    $last4 = $charge->payment_method_details->card->last4;
                }

                $model->last4 = $last4;
                $model        = $handler->save($model);
            } catch (\Exception $e) {
                //TODO: log error
            }

            return $model;
        }

        $this->saveSubscription([], $submissionId, $subscription->plan->id);

        return false;
    }

    /**
     * @param      $resourceId
     * @param bool $atPeriodEnd
     *
     * @return bool
     * @throws \Exception
     */
    public function cancelSubscription($resourceId, $atPeriodEnd = true): bool
    {
        $this->prepareApi();
        try {
            $subscription = StripeAPI\Subscription::retrieve($resourceId);
            $subscription->cancel();
        } catch (\Exception $e) {
            $this->processError($e);

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function fetchPlans(): array
    {
        $planHandler = $this->getPlanHandler();

        $plans = [];
        if (!$this->isForceUpdate()) {
            $plans = $planHandler->getByIntegrationId($this->getId());

            if ($plans) {
                return $plans;
            }
        }

        $this->prepareApi();

        try {
            $response = StripeAPI\Plan::all();

            foreach ($response->autoPagingIterator() as $data) {
                $plans[] = new SubscriptionPlanModel([
                    'integrationId' => $this->getId(),
                    'resourceId'    => $data['id'],
                    'name'          => $data['nickname'],
                ]);
            }
        } catch (\Exception $e) {
            $this->processError($e);

            return $plans;
        }

        $planHandler->updateIntegrationPlans($this->getId(), $plans);

        return $plans;
    }

    /**
     * @inheritdoc
     */
    public function fetchPlan(string $id)
    {
        $planHandler = $this->getPlanHandler();

        $this->prepareApi();
        try {
            $data = StripeAPI\Plan::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        $plan = $planHandler->getByResourceId($data['id'], $this->getId());
        if ($plan === null) {
            $plan = new SubscriptionPlanModel();
        }

        $plan->integrationId = $this->getId();
        $plan->resourceId    = $id;
        $plan->name          = $data['nickname'];

        $planHandler->save($plan);

        return $plan;
    }

    /**
     * Return Stripe details for specific payment
     * If token is provided and no payment was found in DB it tries to recover payment data from gateway
     *
     * @param int    $submissionId
     * @param string $token
     *
     * @return array|false|SubscriptionModel|PaymentInterface
     */
    public function getPaymentDetails(int $submissionId, string $token = '')
    {
        $subscriptionHandler = $this->getSubscriptionHandler();
        $subscription        = $subscriptionHandler->getBySubmissionId($submissionId);
        if ($subscription !== null) {
            return $subscription;
        }

        $paymentHandler = $this->getPaymentHandler();
        $payment        = $paymentHandler->getBySubmissionId($submissionId);
        if ($payment !== null) {
            return $payment;
        }

        if (!$token) {
            return false;
        }

        //TODO: in theory we never should get here
        //TODO: but if we get here we could tie up submission and these charges/subscriptions

        //TODO: from linking subscriptions with wrong submissions

        $this->prepareApi();
        try {
            $source = StripeAPI\Source::retrieve($token);
        } catch (\Exception $e) {
            return $this->processError($e);
        }
        $metadata = $source['metadata'];
        if (isset($metadata['charge'])) {
            $data = $this->getChargeDetails($metadata['charge']);

            return $this->savePayment($data, $submissionId);
        }
        if (isset($metadata['subscription'])) {
            $data = $this->getSubscriptionDetails($metadata['subscription']);
            if ($data === false) {
                return false;
            }
            $data['source'] = $source;

            return $this->saveSubscription($data, $submissionId, $data['plan']['id']);
        }

        return false;
    }

    /**
     * @param mixed $id
     *
     * @return array|bool|\Stripe\StripeObject
     * @throws \Exception
     */
    public function getChargeDetails($id)
    {
        try {
            $charge = StripeAPI\Charge::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }
        $charge = $charge->toArray();
        //TODO: constants?
        $charge['type']   = 'charge';
        $charge['amount'] = self::fromStripeAmount($charge['amount'], $charge['currency']);

        return $charge;
    }

    /**
     * @param mixed $id
     *
     * @return array|bool|\Stripe\StripeObject
     * @throws \Exception
     */
    public function getSubscriptionDetails($id)
    {
        $this->prepareApi();
        try {
            $subscription = StripeAPI\Subscription::retrieve($id);
        } catch (\Exception $e) {
            return $this->processError($e);
        }
        $subscription                     = $subscription->toArray();
        $subscription['type']             = 'subscription';
        $plan                             = $subscription['plan'];
        $subscription['plan']['amount']   = self::fromStripeAmount($plan['amount'], $plan['currency']);
        $subscription['plan']['interval'] = self::fromStripeInterval($plan['interval'], $plan['interval_count']);

        return $subscription;
    }

    /**
     * Perform anything necessary before this integration is saved
     *
     * @param IntegrationStorageInterface $model
     */
    public function onBeforeSave(IntegrationStorageInterface $model)
    {
        $model->updateAccessToken(
            $this->getSetting(
                $this->isLiveMode() ? self::SETTING_SECRET_KEY_LIVE : self::SETTING_SECRET_KEY_TEST
            )
        );
    }

    /**
     * Returns last error happened during Stripe API calls
     *
     * @return \Exception|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Returns link to stripe dashboard for selected resource
     *
     * @param string $resourceId stripe resource id
     * @param string $type       resource type
     *
     * @return string
     */
    public function getExternalDashboardLink(string $resourceId, string $type): string
    {
        switch ($type) {
            case PaymentInterface::TYPE_SINGLE:
                return "https://dashboard.stripe.com/payments/$resourceId";
            case PaymentInterface::TYPE_SUBSCRIPTION:
                return "https://dashboard.stripe.com/subscriptions/$resourceId";
            default:
                return '';
        }
    }

    /**
     * @return string
     * @throws IntegrationException
     */
    public function getPublicKey(): string
    {
        return $this->getSetting(
            $this->isLiveMode() ? self::SETTING_PUBLIC_KEY_LIVE : self::SETTING_PUBLIC_KEY_TEST
        );
    }

    public function prepareApi()
    {
        StripeAPI\Stripe::setApiKey($this->getAccessToken());
        StripeApi\Stripe::setApiVersion('2019-08-14');

        StripeApi\Stripe::setAppInfo(
            'solspace/craft3-freeform',
            Freeform::getInstance()->getVersion(),
            'https://docs.solspace.com/craft/freeform'
        );

        $this->lastError = null;
    }


    /**
     * @return bool
     * @throws IntegrationException
     */
    protected function isLiveMode(): bool
    {
        return $this->getSetting(self::SETTING_LIVE_MODE);
    }

    /**
     * @param string         $paymentIntentId
     * @param PaymentDetails $paymentDetails
     *
     * @return bool|PaymentIntent
     */
    protected function processPaymentIntent(string $paymentIntentId, PaymentDetails $paymentDetails)
    {
        $this->prepareApi();
        $submissionId = $paymentDetails->getSubmission()->getId();

        if ($paymentIntentId === 'declined') {
            $this->lastError = new \Exception('Your card was declined', 400);
            return false;
        }

        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        $customer = null;
        if (!$intent->customer) {
            try {
                $customer = Customer::create($paymentDetails->getCustomer()->toStripeConstructArray());
            } catch (\Exception $e) {
            }
        }

        $updateDataCallback = Freeform::getInstance()->getSettings()->settings['stripePaymentDataCallback'];

        try {
            if($updateDataCallback) {
                $updateData = $updateDataCallback([
                    'submission' => $paymentDetails->getSubmission()
                ]);
            } else {
                $updateData = [
                    'description' => 'Payment for FF Submission #' . $submissionId,
                    'metadata'    => ['submissionId' => $submissionId],
                ];
            }

            if ($customer) {
                $updateData['customer'] = $customer;
            }

            $intent = PaymentIntent::update($paymentIntentId, $updateData);
            if ($intent->status === PaymentIntent::STATUS_REQUIRES_CONFIRMATION) {
                $intent->confirm();
            }
        } catch (\Exception $e) {
            return $this->processError($e);
        }

        return $intent;
    }

    /**
     * @return string
     */
    protected function getApiRootUrl(): string
    {
        return 'https://api.stripe.com/';
    }

    /**
     * @return SubscriptionPlansService
     */
    protected function getPlanHandler(): SubscriptionPlansService
    {
        return Freeform::getInstance()->subscriptionPlans;
    }

    /**
     * @return SubscriptionsService
     */
    protected function getSubscriptionHandler(): SubscriptionsService
    {
        return Freeform::getInstance()->subscriptions;
    }

    /**
     * @return PaymentsService
     */
    protected function getPaymentHandler(): PaymentsService
    {
        return Freeform::getInstance()->payments;
    }

    /**
     * Saves payment data to db
     *
     * @param array|\Stripe\ApiResource $data
     * @param integer                   $submissionId
     *
     * @return PaymentModel|false
     */
    protected function savePayment($data, int $submissionId)
    {
        $handler = $this->getPaymentHandler();

        $model = new PaymentModel([
            'integrationId' => $this->getId(),
            'submissionId'  => $submissionId,
        ]);

        $error = $this->getLastError();
        if ($error) {
            //TODO: we can request charge and get details, but we can end up with failure loop
            //TODO: validate that we have these?

            if ($error->getPrevious() instanceof StripeAPI\Exception\ApiErrorException) {
                $error = $error->getPrevious();
            }

            if ($error instanceof StripeAPI\Exception\ApiErrorException) {
                $data              = $error->getJsonBody()['error'];
                $model->resourceId = $data['paymentIntentId'] ?? null;
            }

            $model->errorCode    = $error->getCode();
            $model->errorMessage = $error->getMessage();
            $model->status       = PaymentRecord::STATUS_FAILED;
        } else {
            /** @var Charge $charge */
            $charge = array_pop($data['charges']->data);
            $last4  = $card = null;
            if ($charge) {
                $last4 = $charge->payment_method_details->card->last4;
                $card  = $charge->payment_method_details->card;
            }

            $model->resourceId = $data['id'];
            $model->amount     = $data['amount'];
            $model->currency   = $data['currency'];
            $model->last4      = $last4;
            $model->status     = $data['status'] === 'succeeded' ? PaymentRecord::STATUS_PAID : PaymentRecord::STATUS_FAILED;
            $model->metadata   = [
                'paymentIntentId' => $data['id'],
                'card'            => $card,
            ];
        }

        $handler->save($model);

        return $model;
    }

    /**
     * Saves submission data to DB
     *
     * @param array|\Stripe\ApiResource $data
     * @param integer                   $submissionId
     * @param string                    $planResourceId
     *
     * @return SubscriptionModel|false
     */
    protected function saveSubscription($data, int $submissionId, string $planResourceId)
    {
        $handler     = $this->getSubscriptionHandler();
        $planHandler = $this->getPlanHandler();
        $plan        = $planHandler->getByResourceId($planResourceId, $this->getId());

        $model = new SubscriptionModel([
            'integrationId' => $this->getId(),
            'submissionId'  => $submissionId,
            'planId'        => $plan->getId(),
        ]);

        $error = $this->getLastError();
        if ($error) {
            //TODO: we can request charge and get details, but we can end up with failure loop
            //TODO: validate that we have these?

            if ($error->getPrevious() instanceof StripeAPI\Exception\ApiErrorException) {
                $error = $error->getPrevious();
            }

            if ($error instanceof StripeAPI\Exception\ApiErrorException	) {
                $data              = $error->getJsonBody()['error'];
                $model->resourceId = isset($data['subscription']) ? $data['subscription'] : null;
            }

            $model->errorCode    = $error->getCode();
            $model->errorMessage = $error->getMessage();
            $model->status       = PaymentRecord::STATUS_FAILED;
        } else {
            $model->resourceId = $data['id'];
            $model->amount     = $data['plan']['amount'];
            $model->currency   = $data['plan']['currency'];
            $model->interval   = $data['plan']['interval'];
            if (isset($data['source'])) {
                $model->last4    = $data['source']['card']['last4'];
                $model->metadata = [
                    'paymentIntentId' => $data['id'],
                    'card'            => $data['source']['card'],
                ];
            }
            $model->status = $data['status'];
        }

        $handler->save($model);

        return $model;
    }

    /**
     * Catches and logs all Stripe errors, you can get saved error with getLastError()
     *
     * @param \Exception $exception
     *
     * @return bool returns false
     */
    protected function processError($exception)
    {
        $this->lastError = $exception;

        switch (get_class($exception)) {
            case 'Stripe\Exception\CardException':
                return false;

            case 'Stripe\Exception\InvalidRequestException':
                //Resource not found
                if ($exception->getHttpStatus() == 404) {
                    return null;
                }

            // intentional fall through
            case 'Stripe\Exception\AuthenticationException':
            case 'Stripe\Exception\RateLimitException':
            case 'Stripe\Exception\ApiConnectionException':
            case 'Stripe\Exception\PermissionException':
            case 'Stripe\Exception\UnknownApiErrorException':
            case 'Stripe\Exception\IdempotencyException':
            case 'Stripe\Exception\ApiErrorException':
                $message         = 'Error while processing your payment, please try later.';
                $this->lastError = new \Exception($message, 0, $exception);
                break;

            default:
                throw $exception;
        }

        FreeformLogger::getInstance(FreeformLogger::STRIPE)->error($exception->getMessage());

        return false;
    }
}
