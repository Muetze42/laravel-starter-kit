## Code Quality

- Before finalizing PHP changes, run code quality tools in this order:
    1. `vendor/bin/rector` - Automated refactoring and code upgrades
    2. `vendor/bin/phpstan analyse` - Static analysis to catch type errors and bugs
    3. `vendor/bin/pint --dirty` - Final code formatting (Rector changes need reformatting)
- Rector automatically applies modern PHP patterns and Laravel best practices.

### PHPStan / Larastan

- This project uses Larastan (PHPStan for Laravel) at **level 8** — the strictest level.
- Configuration is in `phpstan.neon` at the project root.
- Run with: `vendor/bin/phpstan analyse`
- All PHPStan errors MUST be fixed properly — NEVER suppress them with annotations.
- NEVER use `@phpstan-ignore`, `@phpstan-ignore-next-line`, `@phpstan-ignore-line` or any other error suppression annotations.
- NEVER add entries to `ignoreErrors` in `phpstan.neon` to bypass errors.
- Ensure all method parameters, return types, and properties have correct type declarations to satisfy level 8.
- Use PHPDoc `@var`, `@param`, `@return` annotations with precise types (array shapes, generics) when PHP's type system is insufficient for PHPStan.
- Prefer union types over `mixed` — PHPStan level 8 flags `mixed` usage.
