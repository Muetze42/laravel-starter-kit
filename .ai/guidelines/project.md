## Error Handling

- Do NOT wrap code in try-catch blocks unless there is a specific, justified reason to handle the exception.
- Let exceptions bubble up naturally — Laravel's exception handler takes care of reporting and rendering.
- Do NOT clutter the log with unnecessary `Log::` statements, `logger()` calls, or debug output. Only log when explicitly instructed.

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
