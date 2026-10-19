<?php

declare(strict_types=1);

/*
 * This file is part of PSBits ACL Deployment.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSBits\AclDeployment\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PSBits\AclDeployment\Enum\RecordType;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class RecordTypeTest
 *
 * @package PSBits\AclDeployment\Tests\Unit\Enum
 */
class RecordTypeTest extends UnitTestCase
{
    #[Test]
    public function strictlyOrderedCasesReturnsTheDeploymentOrder(): void
    {
        self::assertSame(
            [
                0 => RecordType::FileMount,
                1 => RecordType::BackendGroup,
                2 => RecordType::FrontendGroup,
                3 => RecordType::BackendUser,
                4 => RecordType::FrontendUser,
            ],
            RecordType::strictlyOrderedCases(),
        );
    }

    #[Test]
    #[DataProvider('caseProvider')]
    public function allCasesCarryTheTableNameAsBackingValue(RecordType $recordType, string $expectedTable): void
    {
        self::assertSame($expectedTable, $recordType->value);
        self::assertSame($expectedTable, $recordType->getTable());
    }

    /**
     * @return iterable<string, array{0: RecordType, 1: string}>
     */
    public static function caseProvider(): iterable
    {
        yield 'FileMount' => [RecordType::FileMount, 'sys_filemounts'];
        yield 'BackendGroup' => [RecordType::BackendGroup, 'be_groups'];
        yield 'BackendUser' => [RecordType::BackendUser, 'be_users'];
        yield 'FrontendGroup' => [RecordType::FrontendGroup, 'fe_groups'];
        yield 'FrontendUser' => [RecordType::FrontendUser, 'fe_users'];
    }

    #[Test]
    public function onlyUsersCarryAGroupField(): void
    {
        self::assertSame('subgroup', RecordType::BackendGroup->getGroupField());
        self::assertSame('subgroup', RecordType::FrontendGroup->getGroupField());
        self::assertSame('usergroup', RecordType::BackendUser->getGroupField());
        self::assertSame('usergroup', RecordType::FrontendUser->getGroupField());
        self::assertNull(RecordType::FileMount->getGroupField());
    }

    #[Test]
    public function theGroupTableMatchesTheDomain(): void
    {
        self::assertSame('be_groups', RecordType::BackendGroup->getGroupTable());
        self::assertSame('be_groups', RecordType::BackendUser->getGroupTable());
        self::assertSame('fe_groups', RecordType::FrontendGroup->getGroupTable());
        self::assertSame('fe_groups', RecordType::FrontendUser->getGroupTable());
        self::assertNull(RecordType::FileMount->getGroupTable());
    }

    #[Test]
    public function theIdentifierFieldDependsOnTheRecordType(): void
    {
        self::assertSame('title', RecordType::BackendGroup->getIdentifierField());
        self::assertSame('title', RecordType::FileMount->getIdentifierField());
        self::assertSame('title', RecordType::FrontendGroup->getIdentifierField());
        self::assertSame('username', RecordType::BackendUser->getIdentifierField());
        self::assertSame('username', RecordType::FrontendUser->getIdentifierField());
    }
}
