<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\TrustSignals;

use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetNumberSkus;
use Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetOrdersNumber;
use Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetGrossMerchandiseValue;
use Tiktok\Tiktok\Model\TrustSignals\PartnerData\L30GVM;
use Tiktok\Tiktok\Model\TrustSignals\PartnerData\L30Orders;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PartnerData
{
    /**
     * Default plan type
     */
    public const PLAN_TYPE = 'Magento';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var \Magento\Framework\App\DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    private TimezoneInterface $timezone;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var \Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetNumberSkus
     */
    private GetNumberSkus $getNumberSkus;

    /**
     * @var \Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetOrdersNumber
     */
    private GetOrdersNumber $getOrdersNumber;

    /**
     * @var \Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetGrossMerchandiseValue
     */
    private GetGrossMerchandiseValue $getGrossMerchandiseValue;

    /**
     * @var \Tiktok\Tiktok\Model\TrustSignals\PartnerData\L30GVM
     */
    private L30GVM $l30GVM;

    /**
     * @var \Tiktok\Tiktok\Model\TrustSignals\PartnerData\L30Orders
     */
    private L30Orders $l30Orders;

    /**
     * @var \Magento\Store\Api\Data\StoreInterface|null
     */
    private ?StoreInterface $store = null;

    /**
     * Init dependencies
     *
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\DeploymentConfig $deploymentConfig
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetNumberSkus $getNumberSkus
     * @param \Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetOrdersNumber $getOrdersNumber
     * @param \Tiktok\Tiktok\Model\TrustSignals\PartnerData\GetGrossMerchandiseValue $getGrossMerchandiseValue
     * @param \Tiktok\Tiktok\Model\TrustSignals\PartnerData\L30GVM $l30GVM
     * @param \Tiktok\Tiktok\Model\TrustSignals\PartnerData\L30Orders $l30Orders
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        DeploymentConfig $deploymentConfig,
        TimezoneInterface $timezone,
        ScopeConfigInterface $scopeConfig,
        GetNumberSkus $getNumberSkus,
        GetOrdersNumber $getOrdersNumber,
        GetGrossMerchandiseValue $getGrossMerchandiseValue,
        L30GVM $l30GVM,
        L30Orders $l30Orders
    ) {
        $this->storeManager = $storeManager;
        $this->deploymentConfig = $deploymentConfig;
        $this->timezone = $timezone;
        $this->scopeConfig = $scopeConfig;
        $this->getNumberSkus = $getNumberSkus;
        $this->getOrdersNumber = $getOrdersNumber;
        $this->getGrossMerchandiseValue = $getGrossMerchandiseValue;
        $this->l30GVM = $l30GVM;
        $this->l30Orders = $l30Orders;
    }

    /**
     * Retrieve partner info
     *
     * @param string $storeId
     * @return array
     * @throws \Exception
     */
    public function getInfo(string $storeId): array
    {
        $data = [];
        $this->store = $this->storeManager->getStore($storeId);
        $contactInfo = $this->getContactInfo();
        if (!empty($contactInfo)) {
            $data['contact_info'] = $contactInfo;
        }

        $companyInfo = $this->getCompanyInfo();
        if (!empty($companyInfo)) {
            $data['company_info'] = $companyInfo;
        }

        $budgetIndicators = $this->getBudgetIndicators();
        if (!empty($budgetIndicators)) {
            $data['budget_indicators'] = $budgetIndicators;
        }

        $data['plan_type'] = self::PLAN_TYPE;
        return $data;
    }

    /**
     * Retrieve contact info
     *
     * @return array
     */
    private function getContactInfo(): array
    {
        $data = [];
        $storePhone = $this->scopeConfig->getValue(
            'general/store_information/phone',
            ScopeInterface::SCOPE_STORE,
            $this->store->getId()
        );
        if ($storePhone) {
            $data['phone'] = $storePhone;
        }

        $storeEmail = $this->scopeConfig->getValue(
            'trans_email/ident_general/email',
            ScopeInterface::SCOPE_STORE,
            $this->store->getId()
        );
        if ($storeEmail) {
            $data['email'] = $storeEmail;
        }

        return $data;
    }

    /**
     * Retrieve company info
     *
     * @return array
     * @throws \Exception
     */
    private function getCompanyInfo(): array
    {
        $data = [
            'website_url' => $this->store->getBaseUrl(),
            'number_skus' => $this->getNumberSkus->execute((string)$this->store->getId())
        ];

        try {
            if ($this->deploymentConfig->isAvailable()) {
                $installDate = $this->deploymentConfig->get(
                    ConfigOptionsListConstants::CONFIG_PATH_INSTALL_DATE
                );

                if ($installDate) {
                    $now = $this->timezone->date();
                    $targetDate = $this->timezone->date(new \DateTime($installDate));
                    $interval = $now->diff($targetDate);
                    $data['store_tenure'] = (int)$interval->format('%a');
                }
            }
        } catch (FileSystemException|RuntimeException $e) {
            return $data;
        }

        return $data;
    }

    /**
     * Retrieve budget indicators
     *
     * @return array
     */
    private function getBudgetIndicators(): array
    {
        $ordersNumber = $this->getOrdersNumber->execute((string)$this->store->getId());
        $gmv = $this->getGrossMerchandiseValue->execute((string)$this->store->getId());
        return [
            'l30orders' => $this->l30Orders->getTier($ordersNumber),
            'l30gmv' => $this->l30GVM->getTier($gmv)
        ];
    }
}
