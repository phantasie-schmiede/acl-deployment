# PSBits ACL Deployment

## Optimized import from (or export to) JSON of TYPO3 users and user groups

---
The file format used here offers better readability and maintainability than
simple database dumps. You can split user data into multiple files (e.g. one for
backend and one for fronent) and define default values as well es variables for
often used configurations to keep the files small and easy to read.

---

- [What does it do?](#what-does-it-do)
- [Why should you use it?](#why-should-you-use-it)
- [How it works](#how-it-works)
    - [Export](#export)
    - [Default values](#default-values)
    - [Variables](#variables)
    - [Page tree access (be_groups only!)](#page-tree-access-be_groups-only)
    - [File imports](#file-imports)
    - [Deployment](#deployment)

### What does it do?

This package is an extension for TYPO3 CMS that provides a way to manage user
configuration on a file basis instead of using the database as the primary
system. You can edit the JSON files directly or use the TYPO3 backend to edit
the users and groups as usual. The data can be exported to JSON via the command
line.

Tables supported:

- be_groups
- be_users
- fe_groups
- fe_users
- pages (perms_groupid only)
- sys_filemounts

**IMPORTANT!**
<br>
No sensitive data (passwords, mfa configuration, etc.) will be exported.

### Why should you use it?

This package was created to

- deploy user configuration to different environments (e.g. staging, production)
  in a consistent and reliable way
- apply the benefits of version control to non-sensitive user data

### How it works

#### Export

It's a good idea to first export the current user configuration.

```bash
./vendor/bin/typo3 aclDeployment:deploy ./path/to/your/configuration.json
```

| Argument   | Description                                                           | Example                                             |
|------------|-----------------------------------------------------------------------|-----------------------------------------------------|
| `filename` | target path and filename relative to web root or starting with `EXT:` | `EXT:my_extension/Configuration/aclDeployment.json` |

Shortened example output (comments are not part of the JSON, of course, but are
added here for helpful explanations):

```json
{
    "be_groups": { // table name
        "_default": {
            "pid": 0
        },
        "_variables": {
        },
        "Advanced editor": { // The key is used as title and must be unique obviously. Providing a title inside this record would have no effect.
            "non_exclude_fields": "pages:backend_layout_next_level,pages:backend_layout,pages:description,pages:media,",
            "subgroup": [ // Groups are referenced by their title.
                "Basic editor",
                "Root page"
            ]
        },
        "Basic editor": {
            "tables_modify": "pages,tt_content",
            "tables_select": "pages,tt_content",
            "subgroup": [
                "News page"
            ]
        },
        // special groups only for access rights to the page tree
        "News page": {
            "_pageTreeAccess": 5 // This group allows access to the page with UID 5 and all pages below unless another group is specified. 
        },
        "Root page": {
            "_pageTreeAccess": 1,
            "subgroup": [
                "News page"
            ]
        }
    },
    "be_users": { // table name
        "_default": {
            "lang": "default",
            "pid": 0
        },
        "_variables": {
        },
        "jadoe": { // The key is used as username and must be unique obviously. Providing a username inside this record would have no effect.
            "groups": [ // Groups are referenced by their title.
                "Basic editor",
                "Another group"
            ],
            "name": "Jane Doe"
        },
        "jodoe": {
            "groups": [
                "Advanced editor"
            ],
            "name": "John Doe"
        }
    }
}
```

As can be seen in the example, **no UIDs are used in the configuration!**
<br>
The commands will resolve the UIDs of the groups, users and filemounts
automatically. This is done by looking up the title and replacing it with the
UID (and vice versa). This way, you don't have to worry about keeping the UIDs
in sync and searching the JSON file for references is easier.

#### Default values

The ExortCommand defines sets of default values for each table. These defaults
are written to "_default" in the JSON file. Only the values that are different
from that need to be specified in the single records.

#### Variables

The "_variables" section is used to define variables that can be used in the
configuration. This is useful for values that are used multiple times in the
configuration, for example, if you have a set of groups or a description that is
used for multiple users. Variables are referenced by their name, e.g.
`@myVariable`. The value of a variable is used, when the value of a field matches
it **exactly**.

Example:

```json
{
    "be_groups": {
        "_variables": {
            "@myVariable": "This is a description."
        },
        "Content Editor": {
            "description": "@myVariable" // Variable will be replaced.
        }
        "SEO Editor": {
            "description": "@myVariable, some other description" // Variable will not be replaced!
        }
    }
}
```

You do not have to use the "@" sign, but it is recommended to keep to a
naming convention to avoid confusion with other values.

#### Page tree access (be_groups only!)

The "_pageTreeAccess" property is used to define access to the page tree. The
value is the UID of the page that the group should have access to. This will
set the `perms_groupid` field of the page. The value can be a single UID, an
array or a comma-separated list of UIDs. The perms_groupid will be inherited by
all child pages, unless they have a different value set. If a child page has a
different value set, all pages above it in the tree will receive visibility for
everybody, so that the users can see the rootline of the page.

**Important:**
<br>
The deploy command will update the TSconfig field of page records and
synchronize the property `TCEMAIN.permissions.groupid` with the values of
_pageTreeAccess. This is necessary to ensure that newly created pages will
inherit the correct permissions from the parent page. If a page has no
_pageTreeAccess value set, the `TCEMAIN.permissions.groupid` will be removed
from the TSconfig field if it was set before!

#### File imports

There is a special property named *"files"* on the first level that can be used to
split the configuration into multiple files. This might be useful when dealing
with a lot of records. The files will be imported in the given order and merged
into one configuration. If a field value of a record is defined in multiple
files, the last definition will be used.

Example:

```json
{
    "files": [
        "EXT:my_extension/Configuration/UserDeployment/backend.json",
        "EXT:my_extension/Configuration/UserDeployment/frontend.json"
    ],
}
```

#### Deployment

The configuration can be deployed to the database via the command line:

```bash
./vendor/bin/typo3 aclDeployment:deploy --dry-run --remove ./path/to/your/configuration.json
```

| Option      | Short | Description                                                              |
|-------------|-------|--------------------------------------------------------------------------|
| `--dry-run` | `-d`  | Only show what would be done, but do not execute the changes.            |
| `--remove`  | `-x`  | Remove all records (soft delete) that are not in the configuration file. |

**Steps:**

1. Check if "files" is defined. If so, load the additional files and merge them
   into one configuration.
2. Iterate over the given tables and records.
3. Get the default values for the table and merge them with the record values.
   If a field is not defined in the record, the default value will be used.
4. Look for variable references and replace them with the actual values.
5. Resolve group references and replace them with the actual UIDs.
6. Check if the record already exists in the database.
    1. If it does, update the record with the new values.
    2. If it does not, create a new record with the given values.
7. If the `--remove` option is set, remove all records that are not in the
   configuration file. This will only soft delete the records, so they can be
   restored later if needed.

## Development

```bash
composer install
composer qa
```

`composer qa` fixes everything that can be fixed automatically and afterwards runs all linters, the static
analysis and the test suites. The same checks run in the `CI` GitHub Actions workflow and in the `pre-commit`
hook that is installed by `composer install`.

### Quality checks for contributors

- `composer lint:all`: runs all configured lint and validation checks
- `composer test:unit` / `composer test:functional`: run the respective suite against the installed core
- `composer test:local`: runs the local (non-Docker) matrix. Without arguments it runs all local
  legs (13.4 and 14.3, each plus its lowest dependency set); with arguments it runs a single leg, e.g.
  `composer test:local -- 13.4` or `composer test:local -- 14.3 --lowest`. The
  `Build/Scripts/runTests.sh` runner pins the requested core in a generated `composer-matrix.json`
  (tracked files are never modified) and leaves `.Build` on the tested core afterwards
- `composer test:docker`: runs the Docker matrix (see "Testing with Docker" below)
- `composer test:all`: runs the local matrix plus the full Docker matrix
- `composer qa`: runs all fixes, all lint checks and the test suite

#### Code coverage

CI measures the line coverage of the unit and functional suites once per run (job
"Code coverage", TYPO3 13.4 / PHP 8.4 with pcov) and uploads the clover/cobertura/html
reports as the `coverage` artifact. Each suite's coverage must not drop below the
baseline in `Build/Quality/coverage-baseline.json` (tolerance: 0.5 points to absorb
small drift between PHP versions); the job fails when it does.

- `composer test:coverage`: runs both suites with coverage and checks the baseline
  (requires the pcov or xdebug PHP extension; without one the suites run without coverage)
- `composer test:coverage:update-baseline`: runs both suites and rewrites
  `Build/Quality/coverage-baseline.json` with the measured values (requires a coverage driver)

#### Testing with Docker

To run the test matrix on PHP versions that are not installed locally, or against a real database,
use the Docker setup in `Build/testing-docker/`:

1. Build the test images once (one per supported PHP version): `composer docker:images`
2. Run the whole Docker matrix: `composer test:docker` (8 legs: for 13.4 and 14.3 each -
   sqlite, sqlite-lowest, MariaDB and PostgreSQL)
3. Run a single leg: `composer test:docker -- <core-version> [--lowest] [--db sqlite|mysql|postgres]`,
   e.g. `composer test:docker -- 13.4 --db postgres`. The `--db` flag selects the compose service
   that carries the database connection settings.
4. Switch the PHP version with `PHP_VERSION=8.3 composer test:docker -- 13.4` (default is 8.4).

Every matrix run announces each leg and each step (matrix environment, composer update, unit
tests, functional tests) with a prominent banner - colored green/red for pass/fail when run in
a terminal - keeps running after a failed leg, and ends with a summary table of all legs; the
exit code is non-zero if any leg failed.

Without Docker, `runTests.sh` also accepts `--db mysql|postgres` and reads the connection settings
from the `typo3Database*` environment variables (host, port, username, password, name) - see the
script header for the defaults.

The CI workflow also publishes the extension to TER when a semver tag is pushed. Publishing requires
the `TYPO3_API_TOKEN` repository secret.
