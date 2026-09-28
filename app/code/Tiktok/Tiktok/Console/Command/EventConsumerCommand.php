<?php

declare(strict_types=1);

namespace Tiktok\Tiktok\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EventConsumerCommand extends Command
{
    /**
     * Website ID Option
     */
    private const WEBSITE_ID_OPTION = 'website-id';

    /**
     * @var \Magento\Framework\App\State
     */
    private State $state;

    /**
     * @var \Magento\Framework\MessageQueue\ConsumerFactory
     */
    private ConsumerFactory $consumerFactory;

    /**
     * Init dependencies
     *
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\MessageQueue\ConsumerFactory $consumerFactory
     * @param string|null $name
     */
    public function __construct(
        State $state,
        ConsumerFactory $consumerFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->state = $state;
        $this->consumerFactory = $consumerFactory;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('tiktok:events:consume')
            ->setDescription('Consume TikTok events for specific website')
            ->addOption(
                self::WEBSITE_ID_OPTION,
                'w',
                InputOption::VALUE_REQUIRED,
                'Website ID to process events for'
            );
    }

    /**
     * Executes current command
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_GLOBAL);

            $websiteId = (int)$input->getOption(self::WEBSITE_ID_OPTION);

            $consumerName = str_replace(
                '%website_id%',
                $websiteId,
                'tiktok.event.track.consumer.website'
            );

            $consumer = $this->consumerFactory->get($consumerName);
            $consumer->process();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
