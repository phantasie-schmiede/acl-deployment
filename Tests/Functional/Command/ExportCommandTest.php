<?php

declare(strict_types=1);

/*
 * This file is part of PSBits ACL Deployment.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSBits\AclDeployment\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use PSBits\AclDeployment\Command\DeployCommand;
use PSBits\AclDeployment\Command\ExportCommand;
use PSBits\AclDeployment\Service\PermissionService;
use PSBits\AclDeployment\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function file_get_contents;
use function json_decode;

/**
 * Class ExportCommandTest
 *
 * Deploys a fixture configuration, exports the database back to JSON and
 * verifies that the export is a faithful, re-deployable representation of the
 * database content.
 *
 * @package PSBits\AclDeployment\Tests\Functional\Command
 */
class ExportCommandTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function anExportWritesAllRecordsToTheGivenFile(): void
    {
        $this->deployFixtureConfiguration();
        $target = $this->instancePath . '/typo3temp/var/acl-export.json';

        $command = GeneralUtility::makeInstance(ExportCommand::class);
        $tester  = new CommandTester($command);
        $tester->execute(['file' => $target]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($target);

        /** @var array<string, mixed> $configuration */
        $configuration = json_decode((string)file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('_default', $configuration['be_groups']);
        self::assertSame('pages,tt_content', $configuration['be_groups']['Editors']['tables_modify']);
        self::assertSame(['Basic editors'], $configuration['be_groups']['Editors']['subgroup']);
        self::assertSame('pages,tt_content', $configuration['be_groups']['Basic editors']['tables_select']);
        self::assertSame([2], $configuration['be_groups']['Basic editors'][PermissionService::PERMISSION_KEY]);
        self::assertSame([4], $configuration['be_groups']['News access'][PermissionService::PERMISSION_KEY]);
        self::assertSame('Jane Doe', $configuration['be_users']['jadoe']['realName']);
        self::assertSame(['Editors'], $configuration['be_users']['jadoe']['usergroup']);
        self::assertSame('Test file mount', $configuration['sys_filemounts']['Media mount']['description']);
        self::assertSame('Customer One', $configuration['fe_users']['customer1']['name']);
        self::assertSame(['Customers'], $configuration['fe_users']['customer1']['usergroup']);

        /*
         * The identifiers of relations are exported as arrays of titles, not as uid lists.
         */
        self::assertIsArray($configuration['be_users']['jadoe']['usergroup']);
        self::assertIsArray($configuration['be_groups']['Editors']['subgroup']);

        /*
         * Sensitive and bookkeeping fields are not exported.
         */
        self::assertArrayNotHasKey('password', $configuration['be_users']['jadoe']);
        self::assertArrayNotHasKey('uid', $configuration['be_groups']['Editors']);
        self::assertArrayNotHasKey('tstamp', $configuration['be_users']['jadoe']);

        /*
         * The export is re-deployable: every group referenced by a record exists as a record in the export.
         */
        foreach ($configuration['be_users']['jadoe']['usergroup'] as $referencedGroup) {
            self::assertArrayHasKey($referencedGroup, $configuration['be_groups']);
        }
    }

    private function deployFixtureConfiguration(): void
    {
        $command = GeneralUtility::makeInstance(DeployCommand::class, $this->permissionService());
        $tester  = new CommandTester($command);
        $tester->execute(['file' => 'EXT:acl_deployment/Tests/Functional/Fixtures/Configuration/deploy.json']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
