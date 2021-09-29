<?php

namespace Mothership\PluginListCliExtension\Framework\Plugin\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginListCommand extends Command
{
    protected static $defaultName = 'plugin:list';

    /** @var EntityRepositoryInterface */
    private $pluginRepo;

    private $supportedFields = [
        'active',
        'installed',
        'upgradeable'
    ];

    public function __construct(EntityRepositoryInterface $pluginRepo)
    {
        parent::__construct();
        $this->pluginRepo = $pluginRepo;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Show a list of available plugins.')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term (e.g. PluginName, active:yes/no, upgradeable:yes/no, installed:yes/no')
            ->addOption('raw', 'r', InputOption::VALUE_NONE, 'Display only the name of the packages without any further informations.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isRaw = $input->getOption('raw');

        $io = new ShopwareStyle($input, $output);

        if (false === $isRaw) {
            $io->title('Mothership Plugin Service');
        }

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $filter = $input->getOption('filter');

        if ($filter) {

            $query = $this->parse($filter);

            if (is_array($query)) {
                $this->buildCustomCriteria($io, $criteria, $query, $isRaw);
            } else {
                $this->buildBasicCriteria($io, $criteria, $filter, $isRaw);
            }

        }
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();

        $pluginTable = [];
        $active = $installed = $upgradeable = 0;

        foreach ($plugins as $plugin) {
            $pluginActive = $plugin->getActive();
            $pluginInstalled = $plugin->getInstalledAt();
            $pluginUpgradeable = $plugin->getUpgradeVersion();

            $pluginTable[] = [
                $plugin->getName(),
                $plugin->getLabel(),
                $plugin->getVersion(),
                $pluginUpgradeable,
                $plugin->getAuthor(),
                $pluginInstalled ? 'Yes' : 'No',
                $pluginActive ? 'Yes' : 'No',
                $pluginUpgradeable ? 'Yes' : 'No',
            ];

            if ($pluginActive) {
                ++$active;
            }

            if ($pluginInstalled) {
                ++$installed;
            }

            if ($pluginUpgradeable) {
                ++$upgradeable;
            }
        }

        if ($isRaw) {
            $io->text(array_column($pluginTable, 0));
            return self::SUCCESS;
        }

        $io->table(
            ['Plugin', 'Label', 'Version', 'Upgrade version', 'Author', 'Installed', 'Active', 'Upgradeable'],
            $pluginTable
        );
        $io->text(
            sprintf(
                '%d plugins, %d installed, %d active , %d upgradeable',
                \count($plugins),
                $installed,
                $active,
                $upgradeable
            )
        );

        return self::SUCCESS;

    }

    /**
     * @param string $query
     * @return array|string
     */
    private function parse(string $query)
    {
        if (strpos($query, ':') === false) {
            return $query;
        }

        return explode(':', $query);
    }

    /**
     * @param ShopwareStyle $io
     * @param Criteria $criteria
     * @param array $query
     * @param boolean $isRaw
     */
    private function buildCustomCriteria(ShopwareStyle $io, Criteria $criteria, array $query, bool $isRaw = false)
    {
        if (false === $isRaw) {
            $io->comment(vsprintf('Filtering for column "%s" with value "%s"', $query));
        }

        [$field, $value] = $query;

        switch ($field) {

            case 'upgradeable':

                $filter = $value === 'yes'
                    ? new NotFilter(MultiFilter::CONNECTION_XOR, [
                        new EqualsFilter('upgradeVersion', null)
                    ])
                    : new EqualsFilter('upgradeVersion',null);

                break;

            case 'installed':

                $filter = $value === 'yes'
                    ? new NotFilter(MultiFilter::CONNECTION_XOR, [
                        new EqualsFilter('installedAt', null)
                    ])
                    : new EqualsFilter('installedAt',null);

                break;

            case 'active':

                $filter = $value === 'yes'
                    ? new EqualsFilter('active',1)
                    : new EqualsFilter('active',0);

                break;

            default:
                $supportedFields = implode(PHP_EOL, $this->supportedFields);
                throw new \InvalidArgumentException(
                    vsprintf('Unsupported search field "%s" provided.', $query)
                    . PHP_EOL
                    . sprintf('Use on of the following: %s',  PHP_EOL .$supportedFields)
                );

        }

        $criteria->addFilter($filter);

    }

    /**
     * @param ShopwareStyle $io
     * @param Criteria $criteria
     * @param string $filter
     * @param boolean $isRaw
     */
    private function buildBasicCriteria(ShopwareStyle $io, Criteria $criteria, string $filter, bool $isRaw = false)
    {
        if (false === $isRaw) {
            $io->comment(sprintf('Filtering for: %s', $filter));
        }

        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new ContainsFilter('name', $filter),
                new ContainsFilter('label', $filter),
            ]
        ));
    }

}