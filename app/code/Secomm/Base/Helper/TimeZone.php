<?php

namespace Secomm\Base\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class TimeZone extends AbstractHelper
{

    /**
     * @var TimezoneInterface
     */
    private TimezoneInterface $timezoneInterface;

    /**
     * @param Context $context
     * @param TimezoneInterface $timezoneInterface
     */
    public function __construct(
        Context           $context,
        TimezoneInterface $timezoneInterface,
    )
    {
        $this->timezoneInterface = $timezoneInterface;
        parent::__construct($context);
    }

    /**
     * @return string
     */
    public function getTime(): string
    {
        return $this->timezoneInterface->formatDate();
    }

}
