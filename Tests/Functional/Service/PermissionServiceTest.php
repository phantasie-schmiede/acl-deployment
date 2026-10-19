<?php

declare(strict_types=1);

/*
 * This file is part of PSBits ACL Deployment.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSBits\AclDeployment\Tests\Functional\Service;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Test;
use PSBits\AclDeployment\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PermissionServiceTest
 *
 * Applies a page tree access mapping against the real pages table of the
 * functional instance and verifies the permission inheritance and the
 * TCEMAIN.permissions.groupid synchronization in the page TSconfig.
 *
 * @package PSBits\AclDeployment\Tests\Functional\Service
 */
class PermissionServiceTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function aMappedPageReceivesTheGroupAndItsSubpagesInheritIt(): void
    {
        $this->permissionService()
            ->setPermissionsForAllPages([2 => 42]);

        $childOne   = $this->fetchPage(2);
        $grandChild = $this->fetchPage(3);
        $root       = $this->fetchPage(1);

        self::assertSame(42, (int)$childOne['perms_groupid'], 'The mapped page receives the group.');
        self::assertSame(
            42,
            (int)$grandChild['perms_groupid'],
            'The subpage of the mapped page inherits the group.',
        );
        self::assertSame(0, (int)$root['perms_groupid'], 'The root page has no mapping and stays unchanged.');
        self::assertSame('', (string)$root['TSconfig'], 'No TSconfig is written without a mapping.');

        self::assertStringContainsString('TCEMAIN', (string)$childOne['TSconfig']);
        self::assertStringContainsString('permissions', (string)$childOne['TSconfig']);
        self::assertStringContainsString('groupid = 42', (string)$childOne['TSconfig']);
        self::assertStringContainsString('added by acl_deployment', (string)$childOne['TSconfig']);
        self::assertSame(
            '',
            (string)$grandChild['TSconfig'],
            'The TSconfig is only written for explicitly mapped pages.',
        );
    }

    #[Test]
    public function aSubpageWithADifferentMappingKeepsItsOwnGroupAndMakesItsParentTreeVisible(): void
    {
        $this->permissionService()
            ->setPermissionsForAllPages([2 => 42, 3 => 77]);

        $root     = $this->fetchPage(1);
        $childOne = $this->fetchPage(2);
        $childTwo = $this->fetchPage(4);

        self::assertSame(42, (int)$childOne['perms_groupid']);
        self::assertSame(77, (int)$this->fetchPage(3)['perms_groupid']);
        self::assertSame(
            0,
            (int)$childTwo['perms_groupid'],
            'The other child of the root page has no mapping and no parent group.',
        );

        /*
         * Page 2 and page 4 belong to different groups, so the root page must be visible for
         * everybody to keep the rootline of both branches visible.
         */
        self::assertNotSame(0, (int)$root['perms_everybody'] & Permission::PAGE_SHOW);
    }

    #[Test]
    public function anExistingTSconfigGroupidIsRemovedWhenTheMappingIsGone(): void
    {
        $this->permissionService()
            ->setPermissionsForAllPages([2 => 42]);

        $this->permissionService()
            ->setPermissionsForAllPages([]);

        $childOne = $this->fetchPage(2);

        /*
         * With an empty mapping the root page's (unset) group propagates down the tree, so the
         * previously set group id is cleared. The TSconfig may keep an empty permissions block,
         * but the groupid itself must be gone.
         */
        self::assertSame(0, (int)$childOne['perms_groupid']);
        self::assertStringNotContainsString('groupid', (string)$childOne['TSconfig'], 'The groupid was removed.');
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function fetchPage(int $uid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll();

        $page = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()
                    ->eq('uid', $queryBuilder->createNamedParameter($uid)),
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($page, 'The page with uid ' . $uid . ' exists.');

        return $page;
    }
}
