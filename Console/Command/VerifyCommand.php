<?php

declare(strict_types=1);

namespace ETechFlow\ImageOptimizer\Console\Command;

use ETechFlow\ImageOptimizer\Model\Config;
use ETechFlow\ImageOptimizer\Model\Engine\EngineChain;
use ETechFlow\ImageOptimizer\Model\ImageProcessor;
use ETechFlow\ImageOptimizer\Model\LicenseValidator;
use ETechFlow\ImageOptimizer\Model\WebpGenerator;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento etechflow:io:verify` — 12-check smoke test.
 *
 * 12 PASS lines = green-light go-live. Same shape as etechflow:isp:verify,
 * etechflow:bisn:verify.
 */
class VerifyCommand extends Command
{
    private int $checksRun = 0;
    private int $checksFailed = 0;

    public function __construct(
        private readonly AppState $appState,
        private readonly LicenseValidator $licenseValidator,
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly EngineChain $engineChain,
        private readonly WebpGenerator $webpGenerator,
        private readonly ImageProcessor $imageProcessor
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('etechflow:io:verify')
            ->setDescription('Smoke-test ETechFlow Image Optimizer (license, DB, conversion engines, DI wiring).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
        } catch (\Exception $e) {
            // already set
        }

        $output->writeln('=== ETechFlow Image Optimizer verify ===');
        $output->writeln('');

        $this->check($output, 'LicenseValidator evaluates without throwing', function () {
            $host = $this->licenseValidator->getCurrentHost();
            $isDev = $this->licenseValidator->isDevHost($host);
            $isValid = $this->licenseValidator->isValid();
            return sprintf('host=%s; dev_host=%s; valid=%s',
                $host ?: '(none)',
                $isDev ? 'yes' : 'no',
                $isValid ? 'yes' : 'no');
        });

        $this->check($output, 'Config.isEnabled() returns a boolean', function () {
            return 'enabled=' . ($this->config->isEnabled() ? 'yes' : 'no');
        });

        $this->check($output, 'Output settings reachable', function () {
            $picture = $this->config->isPictureBlockEnabled() ? 'on' : 'off';
            $lazy = $this->config->isLazyLoadEnabled() ? 'on' : 'off';
            $quality = $this->config->getQuality();
            return sprintf('picture=%s; lazy_load=%s; quality=%d', $picture, $lazy, $quality);
        });

        $this->check($output, 'Coverage settings reachable', function () {
            $product = $this->config->isProductCacheCovered() ? 'on' : 'off';
            $cat = $this->config->isCategoryCovered() ? 'on' : 'off';
            $cms = $this->config->isCmsCovered() ? 'on' : 'off';
            return sprintf('product=%s; category=%s; cms=%s', $product, $cat, $cms);
        });

        $this->check($output, 'etechflow_io_optimization_log table exists', function () {
            $conn = $this->resourceConnection->getConnection();
            $name = $this->resourceConnection->getTableName('etechflow_io_optimization_log');
            if (!$conn->isTableExists($name)) {
                throw new \RuntimeException("Missing table '$name' — run bin/magento setup:upgrade");
            }
            return 'OK';
        });

        $this->check($output, 'WebpGenerator resolves via DI', function () {
            return get_class($this->webpGenerator);
        });

        $this->check($output, 'ImageProcessor resolves via DI', function () {
            return get_class($this->imageProcessor);
        });

        $this->check($output, 'EngineChain resolves via DI', function () {
            return get_class($this->engineChain);
        });

        $this->check($output, 'Engine availability report (at least one MUST be available)', function () {
            $report = $this->engineChain->getAvailabilityReport();
            $available = array_keys(array_filter($report));
            if (empty($available)) {
                throw new \RuntimeException(
                    'NO conversion engine available! Install one of: cwebp binary, php-imagick (with WebP), or rebuild php-gd with --with-webp.'
                );
            }
            $detail = implode(', ', array_map(
                fn($k, $v) => $k . '=' . ($v ? 'yes' : 'no'),
                array_keys($report),
                array_values($report)
            ));
            return $detail;
        });

        $this->check($output, 'First-available engine resolves', function () {
            $engine = $this->engineChain->getFirstAvailable();
            if ($engine === null) {
                throw new \RuntimeException('getFirstAvailable() returned null');
            }
            return 'picked=' . $engine->getName();
        });

        $this->check($output, 'Configured engine priority order is valid', function () {
            $order = $this->config->getEngineOrder();
            if (empty($order)) {
                throw new \RuntimeException('engine_order is empty');
            }
            $known = ['cwebp', 'imagick', 'gd'];
            $unknown = array_diff($order, $known);
            if (!empty($unknown)) {
                throw new \RuntimeException('Unknown engine(s): ' . implode(', ', $unknown));
            }
            return 'order=' . implode(',', $order);
        });

        $this->check($output, 'Quality value is in the 1-100 range', function () {
            $q = $this->config->getQuality();
            if ($q < 1 || $q > 100) {
                throw new \RuntimeException('Quality out of range: ' . $q);
            }
            return 'quality=' . $q;
        });

        $output->writeln('');
        if ($this->checksFailed === 0) {
            $output->writeln(sprintf('<info>All %d checks passed.</info>', $this->checksRun));
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('<error>%d of %d checks FAILED.</error>', $this->checksFailed, $this->checksRun));
        return Command::FAILURE;
    }

    private function check(OutputInterface $output, string $name, callable $fn): void
    {
        $this->checksRun++;
        $output->write(sprintf('%2d. %s ... ', $this->checksRun, $name));
        try {
            $detail = $fn();
            $output->writeln(sprintf('<info>OK</info> (%s)', $detail));
        } catch (\Throwable $e) {
            $this->checksFailed++;
            $output->writeln(sprintf('<error>FAIL: %s</error>', $e->getMessage()));
        }
    }
}
