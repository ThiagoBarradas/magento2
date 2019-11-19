<?php
/**
 * Class InstallmentsByBrandManagements
 *
 * @author      MundiPagg Embeddables Team <embeddables@mundipagg.com>
 * @copyright   2017 MundiPagg (http://www.mundipagg.com)
 * @license     http://www.mundipagg.com Copyright
 *
 * @link        http://www.mundipagg.com
 */

namespace MundiPagg\MundiPagg\Model;

use Magento\Framework\Api\SimpleBuilderInterface;
use Mundipagg\Core\Kernel\Services\MoneyService;
use Mundipagg\Core\Kernel\ValueObjects\CardBrand;
use Mundipagg\Core\Recurrence\Aggregates\Plan;
use Mundipagg\Core\Recurrence\Repositories\RepetitionRepository;
use Mundipagg\Core\Recurrence\Services\RecurrenceService;
use MundiPagg\MundiPagg\Api\InstallmentsByBrandAndAmountManagementInterface;
use Magento\Checkout\Model\Session;
use MundiPagg\MundiPagg\Helper\RecurrenceProductHelper;
use MundiPagg\MundiPagg\Model\Installments\Config\ConfigByBrand as Config;
use Magento\Framework\App\ObjectManager;

class InstallmentsByBrandAndAmountManagement
    extends AbstractInstallmentManagement
    implements InstallmentsByBrandAndAmountManagementInterface
{
    protected $builder;
    protected $session;
    protected $cardBrand;
    /**
     * @var RecurrenceProductHelper
     */
    protected $recurrenceProductHelper;

    /**
     * @param SimpleBuilderInterface $builder
     */
    public function __construct(
        SimpleBuilderInterface $builder,
        Session $session,
        Config $config,
        RecurrenceProductHelper $recurrenceProductHelper
    )
    {
        $this->setBuilder($builder);
        $this->setSession($session);
        $this->setConfig($config);
        $this->recurrenceProductHelper = $recurrenceProductHelper;

        parent::__construct();
    }

    /**
     * @param mixed $brand
     * @param mixed $amount
     * @return mixed
     */
    public function getInstallmentsByBrandAndAmount($brand = null, $amount = null)
    {
        $baseBrand = 'nobrand';
        if (
            strlen($brand) > 0 &&
            $brand !== "null" &&
            method_exists(CardBrand::class, $brand)
        ) {
            $baseBrand = strtolower($brand);
        }

        $quote = $this->builder->getSession()->getQuote();

        $baseAmount = $quote->getGrandTotal();
        if ($amount !== null) {
            $baseAmount = $amount;
        }

        $moneyService = new MoneyService();

        $baseAmount = str_replace(
            [",", "."],
            "",
            $baseAmount
        );

        $baseAmount = $moneyService->centsToFloat($baseAmount);

        $installments = $this->getCoreInstallments(
            null,
            CardBrand::$baseBrand(),
            $baseAmount
        );

        $maxInstallmentsByRecurrence = $this->getInstallmentsByRecurrence($quote, $installments);

        if (count($installments) > $maxInstallmentsByRecurrence) {
            $installments = array_slice($installments, 0, $maxInstallmentsByRecurrence);
        }

        return $installments;
    }

    public function getInstallmentsByRecurrence($quote, $installments)
    {
        $items = $quote->getItems();
        $interval = null;
        $recurrenceService = new RecurrenceService();

        foreach($items as $item) {
            $productId = $item->getProductId();
            $recurrenceProduct = $recurrenceService->getRecurrenceProductByProductId($productId);
            $interval = $this->getInterval($recurrenceProduct, $item);
        }

        if ($interval !== null) {
            return $recurrenceService->getMaxInstallmentByRecurrenceInterval($interval);
        }

        return count($installments);
    }

    public function getInterval($recurrenceEntity, $item)
    {
        if ($recurrenceEntity->getRecurrenceType() == Plan::RECURRENCE_TYPE) {
            return $recurrenceEntity->getInterval();
        }

        $repetition = $this->recurrenceProductHelper->getRepetitionSelected($item);

        if (!empty($repetition)) {
            return $repetition->getInterval();
        }

        return null;
    }


    /**
     * @param $brand
     * @return string
     */
    protected function formatCardBrand($brand){

        $cardBrand = '_' . strtolower($brand);

        return $cardBrand;

    }

    /**
     * @param SimpleBuilderInterface $builder
     * @return $this
     */
    protected function setBuilder(SimpleBuilderInterface $builder)
    {
        $this->builder = $builder;
        return $this;
    }

    /**
     * @return SimpleBuilderInterface
     */
    protected function getBuilder()
    {
        return $this->builder;
    }

    /**
     * @return Session
     */
    protected function getSession()
    {
        return $this->session;
    }

    /**
     * @param Session $session
     * @return $this
     */
    protected function setSession(Session $session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * @return Config
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Config $config
     * @return $this
     */
    protected function setConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

}