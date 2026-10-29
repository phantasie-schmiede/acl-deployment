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
use PSBits\Qa\Testing\Support\SiteBasedTestTrait;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

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
    use SiteBasedTestTrait;

    public const int ROOT_PAGE_ID = 1;

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/psbits/foundation',
        'typo3conf/ext/psbits/acl-deployment',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeSiteConfiguration('acl-deployment-test', $this->buildSiteConfiguration(1, '/'));
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
}
