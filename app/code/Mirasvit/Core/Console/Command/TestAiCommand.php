<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Console\Command;

use Magento\Framework\Console\Cli;
use Mirasvit\Core\Ai\Model\ConfigProvider;
use Mirasvit\Core\Ai\Model\RequestFactory;
use Mirasvit\Core\Ai\Service\Manager as AiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestAiCommand extends Command
{
    private $configProvider;

    private $serviceManager;

    private $requestFactory;

    public function __construct(
        ConfigProvider $configProvider,
        AiService      $serviceManager,
        RequestFactory $requestFactory
    ) {
        $this->configProvider = $configProvider;
        $this->serviceManager = $serviceManager;
        $this->requestFactory = $requestFactory;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mirasvit:core:test-ai')
            ->setDescription('Test AI provider configuration and connectivity')
            ->addArgument(
                'prompt',
                InputArgument::OPTIONAL,
                'Test prompt to send to AI provider',
                'Hello, this is a test message. Please respond with "AI integration is working!"'
            )
            ->addOption(
                'provider',
                'p',
                InputOption::VALUE_OPTIONAL,
                'AI provider to test (openai, claude, gemini). Uses default provider if not specified'
            )
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'AI model to use for testing. Uses provider default if not specified'
            )
            ->addOption(
                'show-config',
                'c',
                InputOption::VALUE_NONE,
                'Show current AI configuration'
            )
            ->addOption(
                'list-providers',
                null,
                InputOption::VALUE_NONE,
                'List all available AI providers'
            )
            ->addOption(
                'list-models',
                'l',
                InputOption::VALUE_NONE,
                'List all supported models for the specified provider'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>AI Provider Test Command</info>');
        $output->writeln('==============================');

        if ($input->getOption('show-config')) {
            return $this->showConfiguration($output);
        }

        if ($input->getOption('list-providers')) {
            return $this->listProviders($output);
        }

        if ($input->getOption('list-models')) {
            $provider = $input->getOption('provider');

            return $this->listModels($output, $provider);
        }

        return $this->testAiProvider($input, $output);
    }

    private function showConfiguration(OutputInterface $output): int
    {
        $output->writeln('<comment>AI Configuration:</comment>');
        $output->writeln('Enabled: ' . ($this->configProvider->isEnabled() ? '<info>Yes</info>' : '<error>No</error>'));

        if (!$this->configProvider->isEnabled()) {
            $output->writeln('<error>AI services are disabled. Enable them in admin configuration.</error>');

            return Cli::RETURN_FAILURE;
        }

        $output->writeln('Default Provider: ' . ($this->configProvider->getDefaultProvider() ? : '<comment>Not set</comment>'));
        $output->writeln('Timeout: ' . $this->configProvider->getTimeout() . ' seconds');
        $output->writeln('Max Tokens: ' . $this->configProvider->getMaxTokens());
        $output->writeln('Temperature: ' . $this->configProvider->getTemperature());
        $output->writeln('Debug Mode: ' . ($this->configProvider->isDebugModeEnabled() ? '<info>Yes</info>' : '<error>No</error>'));

        $output->writeln('');
        $output->writeln('<comment>Provider Status:</comment>');
        foreach (ConfigProvider::AVAILABLE_PROVIDERS as $provider) {
            $enabled   = $this->configProvider->isProviderEnabled($provider);
            $hasApiKey = !empty($this->configProvider->getApiKey($provider));

            $status = $enabled && $hasApiKey ? '<info>Ready</info>' : '<error>Not configured</error>';
            $output->writeln(sprintf('  %s: %s', $this->getProviderLabel($provider), $status));
        }

        return Cli::RETURN_SUCCESS;
    }

    private function listProviders(OutputInterface $output): int
    {
        $output->writeln('<comment>Available AI Providers:</comment>');

        foreach (ConfigProvider::AVAILABLE_PROVIDERS as $provider) {
            $enabled   = $this->configProvider->isProviderEnabled($provider);
            $hasApiKey = !empty($this->configProvider->getApiKey($provider));

            $status = $enabled && $hasApiKey ? '<info>✓</info>' : '<error>✗</error>';
            $output->writeln(sprintf('  %s %s (%s)', $status, $this->getProviderLabel($provider), $provider));
        }

        return Cli::RETURN_SUCCESS;
    }

    private function listModels(OutputInterface $output, ?string $provider): int
    {
        if ($provider && !in_array($provider, ConfigProvider::AVAILABLE_PROVIDERS)) {
            $output->writeln('<error>Invalid provider: ' . $provider . '</error>');

            return Cli::RETURN_FAILURE;
        }

        foreach (ConfigProvider::AVAILABLE_PROVIDERS as $exist) {
            if ($provider && $exist !== $provider) {
                continue;
            }

            $output->writeln(sprintf('<comment>Available Models for %s (%s):</comment>', $this->getProviderLabel($exist), $exist));

            $models = $this->configProvider->getAvailableModels($exist);
            if (empty($models)) {
                $output->writeln('  <error>No models available for this provider</error>');

                continue;
            }

            foreach ($models as $modelKey => $modelName) {
                $output->writeln(sprintf('  %s (%s)', $modelName, $modelKey));
            }
        }

        return Cli::RETURN_SUCCESS;
    }

    private function testAiProvider(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->configProvider->isEnabled()) {
            $output->writeln('<error>AI services are disabled in configuration</error>');

            return Cli::RETURN_FAILURE;
        }

        $provider = $input->getOption('provider');
        if (!$provider) {
            $provider = $this->configProvider->getDefaultProvider();
            if (!$provider) {
                $output->writeln('<error>No provider specified and no default provider configured</error>');

                return Cli::RETURN_FAILURE;
            }
            $output->writeln(sprintf('<comment>Using default provider: %s</comment>', $provider));
        }

        if (!in_array($provider, ConfigProvider::AVAILABLE_PROVIDERS)) {
            $output->writeln('<error>Invalid provider: ' . $provider . '</error>');

            return Cli::RETURN_FAILURE;
        }

        if (!$this->configProvider->isProviderEnabled($provider)) {
            $output->writeln('<error>' . $this->getProviderLabel($provider) . ' provider is disabled in configuration</error>');

            return Cli::RETURN_FAILURE;
        }

        $apiKey = $this->configProvider->getApiKey($provider);
        if (empty($apiKey)) {
            $output->writeln('<error>' . $this->getProviderLabel($provider) . ' API key is not configured</error>');

            return Cli::RETURN_FAILURE;
        }

        $model = $input->getOption('model');
        if (!$model) {
            $model = $this->configProvider->getDefaultModel($provider);
            $output->writeln(sprintf('<comment>Using default model: %s</comment>', $model));
        }

        $output->writeln('');
        $output->writeln(sprintf('<comment>Testing %s Provider...</comment>', $this->getProviderLabel($provider)));
        $output->writeln('Provider: ' . $provider);
        $output->writeln('Model: ' . $model);

        try {
            $prompt  = $input->getArgument('prompt');
            $request = $this->requestFactory->create();
            $request->setPrompt($prompt)
                ->setProvider($provider)
                ->setModel($model);

            $output->writeln('');
            $output->writeln('<comment>Sending Request...</comment>');
            $output->writeln('Prompt: "' . $prompt . '"');

            $response = $this->serviceManager->sendRequest($request);

            if ($response->isSuccess()) {
                $output->writeln('');
                $output->writeln('<info>SUCCESS!</info>');
                $output->writeln('<comment>Response:</comment>');
                $output->writeln($response->getContent());
                $output->writeln('');
                $output->writeln('<comment>Details:</comment>');
                $output->writeln('Provider: ' . $response->getProvider());
                $output->writeln('Model: ' . $response->getModel());
                $output->writeln('Tokens Used: ' . $response->getTokensUsed());
                $output->writeln('Status: ' . $response->getStatus());

                return Cli::RETURN_SUCCESS;
            } else {
                $output->writeln('');
                $output->writeln('<error>FAILED!</error>');
                $output->writeln('<error>Error: ' . $response->getErrorMessage() . '</error>');
                if ($response->getErrorCode()) {
                    $output->writeln('<error>Error Code: ' . $response->getErrorCode() . '</error>');
                }

                return Cli::RETURN_FAILURE;
            }

        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>EXCEPTION!</error>');
            $output->writeln('<error>Exception: ' . $e->getMessage() . '</error>');

            if ($this->configProvider->isDebugModeEnabled()) {
                $output->writeln('<comment>Debug trace:</comment>');
                $output->writeln($e->getTraceAsString());
            }

            return Cli::RETURN_FAILURE;
        }
    }

    private function getProviderLabel(string $provider): string
    {
        return $this->configProvider->getProviderLabel($provider);
    }
}
