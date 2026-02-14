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
