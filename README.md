# Pagenode – No Bullshit Content Management

Please see http://pagenode.org for more info and documentation.

MIT Licensed

## Building the PHAR

```bash
composer install
make build
```

The build step produces `pagenode.phar` at the repository root.

## Coding Standards

PHP-CS-Fixer enforces PSR-12 plus a handful of modernizations. Run the fixer locally before committing:

```bash
composer cs:lint   # dry-run with diff
composer cs:fix    # apply fixes
```

Pull requests are blocked if the coding-standard workflow fails, so keeping your branch clean ensures a smooth merge.

## Automated Refactoring

Rector keeps the codebase aligned with PHP 8.2+ best practices and common refactors. Run it in dry-run mode while iterating and only apply the changes you intend to keep:

```bash
composer rector:check   # inspect Rector suggestions
composer rector:fix     # apply Rector rules
```
