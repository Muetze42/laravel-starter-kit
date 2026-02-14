<laravel-boost-guidelines>
=== .ai/core rules ===

## PHP

### Enums Location

- Generate Enums always in the folder `app/Enums`, not in the main `app/` folder, unless instructed differently.

### Imports

- Import all classes with `use` and reference only their short names; no fully-qualified class names in code.

### Visibility & Extensibility

- **Default to `protected`** over `private` for extensibility.
- **Default to extensible classes** over `final`.
- In factory methods (`make()`, `fromX()`) always return `static` instead of `self` so child classes are instantiated correctly.
- Design classes to be extensible (interfaces, traits, overridable methods).

### Type Declarations

- Type hints are MANDATORY for all method parameters, return types, and class properties.
- Never use `mixed` unless absolutely necessary - prefer union types or specific types.
- Use `void` return type for methods that do not return a value.

### Enums Usage

- If a PHP Enum exists for a domain concept, always use its cases (or their `->value`) instead of raw strings everywhere — routes, middleware, migrations, seeds, configs, and UI defaults.

### Constants

- NEVER use class constants (`const`) in Laravel projects
- Use `config()` for configuration values
- Use Enums for fixed sets of values
- Use database settings for user-configurable values
- Class constants make values hard to override and test

### Match Operator

- In PHP, use `match` operator over `switch` whenever possible.

### PSR Naming Conventions

- Interfaces MUST be suffixed by `Interface`: e.g. `Psr\Foo\BarInterface`.
- Abstract classes MUST be prefixed by `Abstract`: e.g. `Psr\Foo\AbstractBar`.
- Traits MUST be suffixed by `Trait`: e.g. `Psr\Foo\BarTrait`.
- PSR-1, 4, and 12 MUST be followed.
- For code released as part of a PSR, the vendor namespace MUST be `Psr` and the Composer package name MUST be `psr/<package>` (e.g., `psr/log`).
- For code released as part of a PER or any other Auxiliary Resources, the vendor namespace MUST be `Fig` and the Composer package name MUST be `fig/<package>` (e.g., `fig/cache-util`).
- There MUST be a package/second-level namespace in relation with the PSR or PER that covers the code.
- Implementations of a given PSR or PER SHOULD declare a `provides` key in their `composer.json` file in the form `psr/<package>-implementation` with a version number that matches the PSR being implemented. For example, `"psr/<package>-implementation": "1.0.0"`.

### Static Analysis

- NEVER use `@phpstan-ignore`, `@phpstan-ignore-next-line`, `@phpstan-ignore-line` or any other PHPStan/Larastan error suppression annotations. All errors must be fixed properly.

### Control Flow

- NEVER use `else` or `elseif` statements. Use early returns, guard clauses, or ternary operators instead.

### Variable Naming

- All variable names MUST be at least 3 characters long, with the following exceptions:
    - `$id`, `$fp` are allowed for identifiers and file pointers
    - `$i`, `$j` are allowed as loop counters
    - `$x`, `$y`, `$z` are allowed for coordinates or mathematical calculations
    - `$io` is allowed for Input/Output streams
    - `$to`, `$cc`, `$bcc` are allowed in email contexts

## Laravel

### Artisan Commands

- The `handle()` method in Laravel Console Commands MUST have a `void` return type, NOT `int`.
- Example: `public function handle(): void` (CORRECT)
- Example: `public function handle(): int` (WRONG)
- This is the standard in Laravel 12 stubs and documentation.

### Database

- For DB pivot tables, use correct alphabetical order, like `project_role` instead of `role_project`.

### Database Migrations - Column Naming

- Boolean columns MUST follow Laravel naming conventions WITHOUT the `is_` prefix.
- Examples: `active`, `verified`, `published`, `enabled` (CORRECT)
- Examples: `is_active`, `is_verified`, `is_published`, `is_enabled` (WRONG)
- The `is_` prefix is NOT Laravel style. Use simple adjectives.
- Only exception: If existing table already uses `is_` prefix for consistency.

### Eloquent Model Operations

- Don't add `::query()` when running Eloquent `create()` statements. Use `User::create()` instead of `User::query()->create()`.
- NEVER use `where()` with `like` or `ilike` operators. Always use the `whereLike()` method instead. Use `User::whereLike('name', '%norman%')` instead of `User::where('name', 'like', '%norman%')` or `User::where('name', 'ilike', '%norman%')`.

### Eloquent Observers

- Eloquent Observers should be registered in Eloquent Models with PHP Attributes, and not in AppServiceProvider. Example: `#[ObservedBy([UserObserver::class])]` with `use Illuminate\Database\Eloquent\Attributes\ObservedBy;` on top.

### Eloquent Models - Fillable Properties

- NEVER add foreign key columns to the `$fillable` array. Foreign keys should only be set through relationships or explicit assignment, not mass assignment.
- Example: For a `user_id` foreign key, do NOT include it in `$fillable`. Instead, use `$model->user()->associate($user)` or `$model->user_id = $user->id`.

### Laravel Helpers

- Use Laravel helpers instead of `use` section classes whenever possible. Examples: use `auth()->id()` instead of `Auth::id()` and adding `Auth` in the `use` section. Another example: use `redirect()->route()` instead of `Redirect::route()`.

### Routing

- Always constrain numeric route parameters with `->where('parameter', '[0-9]+')` to prevent conflicts with other routes and avoid unexpected behavior.

<code-snippet name="Numeric Route Parameter Constraint" lang="php">
Route::get('/shop/categories/{shopCategory}', [ShopCategoryController::class, 'show'])
    ->where('shopCategory', '[0-9]+');
</code-snippet>

### Request Validation (CRITICAL)

- **ALL incoming requests MUST be fully validated.** Server-side validation is MANDATORY - never trust client input.
- In Controllers: ALWAYS use FormRequest classes for validation. NEVER access request parameters directly without validation.
- In Livewire components: ALWAYS use `$this->validate()` or Livewire's `#[Validate]` attributes. Process ONLY validated data - never use `$this->property` directly for database operations without prior validation.
- NEVER silently ignore or convert invalid input (e.g., using `$request->integer()` to convert invalid strings to 0). Invalid input MUST return proper validation errors (HTTP 422).

### Code Quality

- Before finalizing PHP changes, run code quality tools in this order:
    1. `vendor/bin/rector` - Automated refactoring and code upgrades
    2. `vendor/bin/pint --dirty` - Final code formatting (Rector changes need reformatting)
- Rector automatically applies modern PHP patterns and Laravel best practices.

## Blade

### Component Attributes

- For dynamic/boolean attributes in Blade components, use `:attribute="$value"` syntax (short for `v-bind:attribute`).
- NEVER use `@if` directly within component attributes - this does not work.
- Example: Use `:clearable="$nullable"` instead of `@if($nullable) clearable @endif`.

=== .ai/flux-ui rules ===

## Flux UI Component Library

### Documentation

- Full Flux Pro documentation is located in `/docs/flux-pro/`
- Component references: `/docs/flux-pro/components/`
- Layout documentation: `/docs/flux-pro/layouts/`
- Additional guides: `/docs/flux-pro/guides/`
- **Always check the documentation before implementing** - never guess attributes or usage
- Read the component's full documentation file, not just the first example

### Styling Guidelines

- Flux UI provides pre-styled components with built-in colors, backgrounds, and styles
- **Do not set custom colors or text colors** unless explicitly required to stand out
- **Text elements (headings, text, labels) have no color variants** - use them as-is
- Interactive components (buttons, badges, etc.) use semantic variants when documented (e.g., `variant="primary"`, `variant="danger"`)
- Trust the component defaults - they're designed to work together
- Avoid custom CSS classes on Flux components unless absolutely necessary

### Layout Structure

- **Never wrap page content in `<div>` containers**
- Use `<flux:main>` for full-width layouts
- Use `<flux:main container>` for centered, max-width layouts
- Follow Flux's layout patterns from `/docs/flux-pro/layouts/`

### Component Usage

- Check `/docs/flux-pro/components/[component-name]` for complete component documentation
- **Each component has a reference table listing all available attributes** - use it
- Only use attributes that are listed in the reference table
- Follow the documented examples exactly - syntax matters
- If a feature isn't in the reference table, it doesn't exist - ask before assuming

### Livewire Integration

- Flux components work seamlessly with Livewire
- Use `wire:model`, `wire:click` etc. as shown in component examples

=== .ai/livewire rules ===

### Livewire

- In Livewire projects, don't use Livewire Volt. Only Livewire class components.

### Livewire Loops

- **ALWAYS** add `wire:key` to the first element inside `@foreach` loops in Livewire components.
- The key must be unique for each iteration.
- Use the item's ID if available: `wire:key="{{ $post->id }}"`.
- If no ID exists, use the loop index: `@foreach($items as $key => $item)` then `wire:key="{{ $key }}"`.
- Example:
```blade
@foreach($posts as $post)
    <div wire:key="{{ $post->id }}">
        ...
    </div>
@endforeach
```

=== .ai/project rules ===

## Code Formatting

- Follow all formatting rules defined in `.editorconfig`
- Use the configured tab size for indentation
- Never assume or override indentation settings

## Project Documentation

### Location

- All project documentation belongs in `/docs` as Markdown files

### What to Document

- Significant changes and feature implementations
- Architectural decisions and rationale
- API integrations and third-party service configurations
- Complex business logic or workflows
- Development guidelines and conventions
- Troubleshooting guides and common issues
- Any information that would help developers or Claude Code understand the project

### Documentation Standards

- Write clear, concise Markdown
- Keep documentation up-to-date when making related code changes
- Documentation should be useful for both human developers and AI assistants

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.18
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v11
- rector/rector (RECTOR) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `debugging-output-and-previewing-html-using-ray` — Use when user says &quot;send to Ray,&quot; &quot;show in Ray,&quot; &quot;debug in Ray,&quot; &quot;log to Ray,&quot; &quot;display in Ray,&quot; or wants to visualize data, debug output, or show diagrams in the Ray desktop application.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
</laravel-boost-guidelines>
