<?php

declare(strict_types=1);

/*
 * This file is part of PSBits ACL Deployment.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSBits\AclDeployment\Tests\Functional;

use PSBits\AclDeployment\Data\ExtensionInformation;
use PSBits\AclDeployment\Service\PermissionService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function file_put_contents;

/**
 * Class AbstractFunctionalTestCase
 *
 * Boots the instance with the extension, a single site (root page 1) and the
 * page tree from the pages fixture, so that the permission logic can be tested
 * against real pages.
 *
 * @package PSBits\AclDeployment\Tests\Functional
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    public const int ROOT_PAGE_ID = 1;

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/psbits/foundation',
        'typo3conf/ext/psbits/acl-deployment',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeSiteConfiguration();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
    }

    protected function permissionService(): PermissionService
    {
        return GeneralUtility::makeInstance(
            PermissionService::class,
            GeneralUtility::makeInstance(ExtensionInformation::class),
            GeneralUtility::makeInstance(SiteFinder::class),
            GeneralUtility::makeInstance(TypoScriptService::class),
        );
    }

    private function writeSiteConfiguration(): void
    {
        $path = Environment::getConfigPath() . '/sites/acl-deployment-test';
        GeneralUtility::rmdir($path, true);
        GeneralUtility::mkdir_deep($path);
        file_put_contents($path . '/config.yaml', "rootPageId: 1\nbase: '/'\n");
    }
}
