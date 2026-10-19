<?php

declare(strict_types=1);

/*
 * This file is part of PSBits ACL Deployment.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSBits\AclDeployment\Tests\Functional\Command;

use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Test;
use PSBits\AclDeployment\Command\DeployCommand;
use PSBits\AclDeployment\Tests\Functional\AbstractFunctionalTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function file_put_contents;
use function json_encode;

/**
 * Class DeployCommandTest
 *
 * Deploys a fixture configuration through the real command against the
 * functional instance database and verifies the resulting records, the
 * resolution of defaults, variables and group references and the page
 * permissions derived from _pageTreeAccess.
 *
 * @package PSBits\AclDeployment\Tests\Functional\Command
 */
class DeployCommandTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function aConfigurationDeploysAllRecordTypes(): void
    {
        $this->assertCommandSucceeds($this->runCommand());

        $fileMount = $this->fetchOne('sys_filemounts', 'title', 'Media mount');
        self::assertNotEmpty($fileMount, 'The file mount has been created.');
        self::assertSame('Test file mount', $fileMount['description']);
        self::assertSame(1, (int)$fileMount['pid'], 'The default value for pid has been applied.');
        self::assertSame(0, (int)$fileMount['deleted']);

        $basicEditors = $this->fetchOne('be_groups', 'title', 'Basic editors');
        $editors      = $this->fetchOne('be_groups', 'title', 'Editors');
        self::assertNotEmpty($basicEditors, 'The backend group has been created.');
        self::assertNotEmpty($editors, 'The second backend group has been created.');
        self::assertSame(1, (int)$basicEditors['pid'], 'The default value for pid has been applied.');
        self::assertSame('pages,tt_content', $basicEditors['tables_select'], 'The variable has been replaced.');
        self::assertSame('pages,tt_content', $editors['tables_modify'], 'The variable has been replaced.');
        self::assertSame(
            (string)$basicEditors['uid'],
            (string)$editors['subgroup'],
            'The subgroup reference has been resolved to the uid of the referenced group.',
        );

        $jane = $this->fetchOne('be_users', 'username', 'jadoe');
        self::assertNotEmpty($jane, 'The backend user has been created.');
        self::assertSame('Jane Doe', $jane['realName']);
        self::assertSame(
            (string)$editors['uid'],
            (string)$jane['usergroup'],
            'The group reference has been resolved to the uid of the referenced group.',
        );

        $customers = $this->fetchOne('fe_groups', 'title', 'Customers');
        self::assertNotEmpty($customers, 'The frontend group has been created.');

        $customer = $this->fetchOne('fe_users', 'username', 'customer1');
        self::assertNotEmpty($customer, 'The frontend user has been created.');
        self::assertSame('Customer One', $customer['name']);
        self::assertSame('customer1@example.com', $customer['email']);
        self::assertSame((string)$customers['uid'], (string)$customer['usergroup']);
    }

    #[Test]
    public function deployingTheSameConfigurationAgainUpdatesTheRecords(): void
    {
        $this->assertCommandSucceeds($this->runCommand());
        $this->assertCommandSucceeds($this->runCommand());

        self::assertSame(3, $this->countRecords('be_groups'), 'No duplicate records have been created.');
        self::assertSame(2, $this->countRecords('be_users'));
    }

    #[Test]
    public function aDryRunMakesNoChanges(): void
    {
        $tester = $this->runCommand(options: ['--dry-run' => true]);
        $this->assertCommandSucceeds($tester);

        self::assertStringContainsString('3 be_groups records would be created.', $tester->getDisplay());
        self::assertSame(0, $this->countRecords('be_groups'), 'No records have been created.');
        self::assertSame(0, $this->countRecords('be_users'));
        self::assertSame(0, $this->countRecords('sys_filemounts'));
    }

    #[Test]
    public function removeSoftDeletesRecordsThatAreNotInTheConfiguration(): void
    {
        $this->insertRecord('be_groups', ['uid' => 900, 'pid' => 1, 'title' => 'Abandoned', 'deleted' => 0]);

        $tester = $this->runCommand(options: ['--remove' => true]);
        $this->assertCommandSucceeds($tester);

        self::assertStringContainsString('1 be_groups records have been removed.', $tester->getDisplay());
        self::assertSame(1, (int)$this->fetchOne('be_groups', 'title', 'Abandoned')['deleted']);
        self::assertSame(0, (int)$this->fetchOne('be_groups', 'title', 'Editors')['deleted']);
    }

    #[Test]
    public function referencedFilesAreMergedIntoTheConfiguration(): void
    {
        $fileName = $this->instancePath . '/typo3temp/var/acl-deployment-files.json';
        file_put_contents(
            $fileName,
            json_encode(
                [
                    'files' => [
                        'EXT:acl_deployment/Tests/Functional/Fixtures/Configuration/files-part1.json',
                        'EXT:acl_deployment/Tests/Functional/Fixtures/Configuration/files-part2.json',
                    ],
                ],
            ),
        );

        $this->assertCommandSucceeds($this->runCommand($fileName));

        self::assertNotEmpty($this->fetchOne('be_groups', 'title', 'Split group one'));
        self::assertNotEmpty($this->fetchOne('be_groups', 'title', 'Split group two'));
        self::assertNotEmpty($this->fetchOne('fe_groups', 'title', 'Split fe group'));
    }

    #[Test]
    public function pageTreeAccessSetsPagePermissionsAndTSconfig(): void
    {
        $this->assertCommandSucceeds($this->runCommand());

        $basicEditors = $this->fetchOne('be_groups', 'title', 'Basic editors');
        $newsAccess   = $this->fetchOne('be_groups', 'title', 'News access');

        $childOne = $this->fetchOne('pages', 'uid', 2);
        self::assertSame((string)$basicEditors['uid'], (string)$childOne['perms_groupid']);
        self::assertStringContainsString('TCEMAIN', (string)$childOne['TSconfig']);
        self::assertStringContainsString('permissions', (string)$childOne['TSconfig']);
        self::assertStringContainsString('groupid = ' . $basicEditors['uid'], (string)$childOne['TSconfig']);
        self::assertStringContainsString('added by acl_deployment', (string)$childOne['TSconfig']);

        $grandChild = $this->fetchOne('pages', 'uid', 3);
        self::assertSame(
            (string)$basicEditors['uid'],
            (string)$grandChild['perms_groupid'],
            'The page below an explicitly mapped page inherits the group.',
        );
        self::assertSame('', (string)$grandChild['TSconfig'], 'No TSconfig has been added without a mapping.');

        $childTwo = $this->fetchOne('pages', 'uid', 4);
        self::assertSame((string)$newsAccess['uid'], (string)$childTwo['perms_groupid']);

        $root = $this->fetchOne('pages', 'uid', 1);
        self::assertSame('0', (string)$root['perms_groupid']);
        self::assertSame('', (string)$root['TSconfig']);
        self::assertNotSame(
            0,
            (int)$root['perms_everybody'] & Permission::PAGE_SHOW,
            'The root page is visible for everybody so that the rootline of the mapped pages stays visible.',
        );
    }

    private function runCommand(?string $configurationFile = null, array $options = []): CommandTester
    {
        $command = GeneralUtility::makeInstance(DeployCommand::class, $this->permissionService());
        $tester  = new CommandTester($command);
        $tester->execute(
            ['file' => $configurationFile ?? 'EXT:acl_deployment/Tests/Functional/Fixtures/Configuration/deploy.json'] + $options,
        );

        return $tester;
    }

    private function assertCommandSucceeds(CommandTester $tester): void
    {
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    private function fetchOne(string $table, string $field, mixed $value): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll();

        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()
                    ->eq($field, $queryBuilder->createNamedParameter($value)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return false === $row ? [] : $row;
    }

    private function countRecords(string $table): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll();

        return (int)$queryBuilder->count('*')->from($table)->executeQuery()->fetchOne();
    }

    private function insertRecord(string $table, array $values): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->insert($table, $values);
    }
}
