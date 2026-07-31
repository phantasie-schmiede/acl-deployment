# Copilot Instructions for `psbits/acl-deployment`

If you haven't already, please read the general agent instructions in `../AGENTS.md` before proceeding with this file.

## Repository purpose

- TYPO3 v13 extension (`type: typo3-cms-extension`) that provides versioning and deployment of users, user groups and their privileges.
- PHP namespace: `PSBits\\AclDeployment\\`.

## Key locations

- `Classes/` — PHP code (commands, services)

## Architecture and conventions

- Strongly typed PHP (`declare(strict_types=1);`) is standard for classes.
- Service wiring uses Symfony DI autowiring in `Configuration/Services.yaml`.
- Use yoda conditions for better readability and to avoid accidental assignment.
- Use alphabetical ordering where possible (e.g., in arrays, attributes, imports, properties, variable declarations)
  for consistency;
- Include punctuation in language files for complete localization, e.g. colons after labels should not be placed in the
  template, but in the language file itself.

If you have questions about architecture, conventions, or best practices, ask before implementing. If you notice any
inconsistencies in the codebase or differences between codebase and these instructions, mention them - don't fix them
unless asked.

## Safe change workflow

1. Identify the layer to change first.
2. Keep edits surgical and consistent with existing code style.

## Validation in this repository

- Repository quality checks are defined in `composer.json` and `.github/workflows/quality.yml`.
- Quality configuration files are centralized in `Build/Quality/` and referenced by Composer scripts and CI.
- Primary local QA command:
    - `composer qa`
    - Runs: `audit:composer`, `validate:composer`, `lint:php`, `analyze:php`
- Additional lint command used in CI:
    - `composer lint:yaml`

## Onboarding run notes (errors/workarounds)

- Running QA locally requires installed dependencies (`composer install` / `composer update`.
- In restricted environments, Composer dependency installation may fail if GitHub authentication for dist downloads is
  unavailable.
