# AI Disclosure

This project uses AI assistance during development. This document explains when and how we tag AI contributions.

## Commit Message Tagging

### [AI-assisted]

This tag indicates AI helped in the development process, but a human reviewed, modified, or significantly guided the
code.

**Examples:**

- `feat: add PDF merge functionality [AI-assisted]`
- `refactor: improve runner health check logic [AI-assisted]`
- `fix: resolve dependency validation issue [AI-assisted]`

### [AI-generated]

This tag indicates AI-generated code with minimal or no human modification beyond acceptance.

**Examples:**

- `chore: scaffold package structure [AI-generated]`
- `test: add unit tests for image service [AI-generated]`

## Exceptions

The following are **NOT tagged** even when AI creates them:

- Code comments and documentation (inline comments, PHPDoc blocks)
- Commit messages

## Rationale

AI tagging provides transparency about the development process while acknowledging that AI is a development tool like
any other. The tags help maintain project history clarity without creating unnecessary overhead for routine development
tasks.
