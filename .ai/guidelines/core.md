# Custom Project Guidelines

These guidelines are maintained separately from Laravel Boost and will persist across updates.

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
