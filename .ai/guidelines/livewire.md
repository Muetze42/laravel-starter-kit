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
