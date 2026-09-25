<?php

declare(strict_types=1);

namespace Secomm\ThemeHelper\Controller\Styleguide;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\State;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly State $appState,
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory
    ) {
    }

    public function execute(): Page|Forward
    {
        if ($this->appState->getMode() !== State::MODE_DEVELOPER) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Global Style Showcase'));

        return $page;
    }
}
