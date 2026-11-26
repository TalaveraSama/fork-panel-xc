# Contributing to Fork Panel XC

Thank you for your interest in contributing to Fork Panel XC! This document provides guidelines and instructions for contributing.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Setup](#development-setup)
4. [Contribution Workflow](#contribution-workflow)
5. [Coding Standards](#coding-standards)
6. [Testing](#testing)
7. [Documentation](#documentation)
8. [Pull Request Process](#pull-request-process)
9. [Reporting Issues](#reporting-issues)
10. [License](#license)

## Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md). Please read it before contributing.

## Getting Started

### Prerequisites

- **PHP 8.3+** with extensions: pdo_mysql, redis, curl, mbstring, xml, zip, gd
- **Node.js 18+** and **pnpm** (or npm/yarn)
- **MariaDB 11+** or **MySQL 8+**
- **Redis 7+**
- **Nginx** (for production)
- **Composer 2+**
- **Git**

### Fork & Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/fork-panel-xc.git
   cd fork-panel-xc
   ```
3. Add the upstream remote:
   ```bash
   git remote add upstream https://github.com/fork-panel-xc/fork-panel-xc.git
   ```

## Development Setup

### Backend Setup

```bash
# Install PHP dependencies
cd src
composer install

# Copy environment file
cp ../config/.env.example ../config/.env
# Edit .env with your local database/Redis credentials

# Run migrations
php console.php db:migrate

# Start development server (optional)
php -S localhost:8080 -t ../frontend/public
```

### Frontend Setup

```bash
# Install dependencies
cd frontend
pnpm install

# Start development server
pnpm dev
```

The frontend will be available at `http://localhost:3000` with API proxy to `http://localhost:8080`.

### Full Stack with Docker (Recommended)

```bash
# Start all services
docker-compose up -d

# Run migrations
docker-compose exec php php console.php db:migrate

# Access panel at http://localhost
```

## Contribution Workflow

### 1. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/your-bug-fix
# or
git checkout -b docs/your-documentation-update
```

Use descriptive branch names:
- `feature/add-epg-merge-tool`
- `fix/stream-test-timeout`
- `docs/update-installation-guide`

### 2. Make Changes

- Write clean, well-documented code
- Follow the coding standards (see below)
- Add tests for new functionality
- Update documentation as needed

### 3. Test Your Changes

```bash
# Backend tests
cd src
composer test        # Unit tests
composer test:unit   # Unit tests only
composer test:integration  # Integration tests

# Frontend tests
cd frontend
pnpm test            # Unit tests
pnpm test:e2e        # End-to-end tests

# Linting
composer cs          # PHP code style
pnpm lint            # JavaScript/Vue linting
```

### 4. Commit Changes

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```bash
git add .
git commit -m "feat(streams): add M3U import with category mapping"
```

Commit message format:
```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

Types:
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation only
- `style` - Formatting, no code change
- `refactor` - Code restructuring
- `test` - Adding tests
- `chore` - Maintenance tasks

### 5. Push & Create PR

```bash
git push origin feature/your-feature-name
```

Then create a Pull Request on GitHub with:
- Clear title and description
- Reference any related issues
- Screenshots for UI changes
- Test results

## Coding Standards

### PHP (Backend)

- **PSR-12** coding standard
- **PHPStan Level 5** static analysis
- **Strict types** (`declare(strict_types=1)`)
- **Type hints** everywhere possible
- **DocBlocks** for public methods

```php
<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Stream;

use ForkPanelXc\Core\Database\Database;

class StreamRepository
{
    public function __construct(
        private Database $db
    ) {}

    /**
     * Find a stream by ID.
     */
    public function find(int $id): ?Stream
    {
        // Implementation
    }

    /**
     * Create a new stream.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Stream
    {
        // Implementation
    }
}
```

Run code style checks:
```bash
cd src
composer cs          # Check
composer cs-fix      # Auto-fix
composer phpstan     # Static analysis
```

### JavaScript/Vue (Frontend)

- **ESLint** with Vue plugin
- **Prettier** for formatting
- **Composition API** with `<script setup>`
- **TypeScript** (preferred for new files)
- **Component naming**: PascalCase
- **Props with types** and defaults

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Stream } from '@/types'

interface Props {
  stream: Stream
  editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  editable: false,
})

const isEditing = ref(false)
const viewerCount = computed(() => props.stream.current_viewers ?? 0)
</script>

<template>
  <div class="card">
    <h3>{{ props.stream.name }}</h3>
    <span class="badge" :class="viewerCount > 0 ? 'badge-success' : 'badge-info'">
      {{ viewerCount }} viewers
    </span>
  </div>
</template>
```

Run linting:
```bash
cd frontend
pnpm lint        # Check
pnpm lint:fix    # Auto-fix
pnpm format      # Prettier
```

### Git Commits

- One logical change per commit
- Clear, descriptive messages
- Reference issues: `Fixes #123`, `Relates to #456`
- No merge commits in feature branches (rebase instead)

## Testing

### Backend Tests

```bash
cd src

# Run all tests
composer test

# Run specific test
composer test -- --filter StreamServiceTest

# With coverage
composer test -- --coverage-html coverage/
```

Test structure:
```
tests/
├── Unit/           # Unit tests (fast, isolated)
│   ├── Domain/
│   ├── Core/
│   └── Public/
├── Integration/    # Integration tests (database, API)
│   └── Api/
└── Fixtures/       # Test data
```

### Frontend Tests

```bash
cd frontend

# Unit tests
pnpm test

# E2E tests
pnpm test:e2e

# E2E with UI
pnpm test:e2e:ui
```

Test structure:
```
frontend/tests/
├── unit/           # Vitest unit tests
│   ├── components/
│   ├── stores/
│   └── utils/
└── e2e/            # Playwright E2E tests
    ├── auth.spec.ts
    ├── streams.spec.ts
    └── dashboard.spec.ts
```

## Documentation

- Update `README.md` for significant changes
- Add/update docstrings for new public APIs
- Update guides in `docs/en/` for new features
- Add CHANGELOG entry for notable changes

Documentation structure:
```
docs/
├── en/
│   ├── guides/           # Feature guides
│   ├── administration/   # Admin guides
│   ├── development/      # Developer docs
│   ├── api/              # API reference
│   └── info/             # Project info
└── ru/                   # Russian translations
```

## Pull Request Process

### Before Submitting

- [ ] All tests pass
- [ ] Code style checks pass
- [ ] No console.log/debug statements
- [ ] Documentation updated
- [ ] CHANGELOG.md updated (for features/fixes)
- [ ] Branch rebased on latest main

### PR Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update
- [ ] Refactoring
- [ ] Performance improvement

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] E2E tests added/updated
- [ ] Manual testing performed

## Screenshots (if applicable)
<!-- Add screenshots for UI changes -->

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Comments added for complex logic
- [ ] Documentation updated
- [ ] No breaking changes (or documented)
```

### Review Process

1. **Automated checks** must pass (CI)
2. **Code review** by maintainers
3. **Address feedback** - push new commits
4. **Approval** - at least 1 maintainer approval
5. **Merge** - squash and merge by maintainer

## Reporting Issues

### Bug Reports

Use the bug report template and include:
- Clear, descriptive title
- Steps to reproduce
- Expected vs actual behavior
- Environment details (OS, PHP version, browser)
- Screenshots/logs if applicable
- Minimal code example if possible

### Feature Requests

Use the feature request template and include:
- Problem statement
- Proposed solution
- Alternatives considered
- Use cases
- Mockups/wireframes if UI-related

### Security Issues

**Do not create public issues for security vulnerabilities.**

Email security@fork-panel-xc.org with:
- Description of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## License

By contributing to Fork Panel XC, you agree that your contributions will be licensed under the [AGPL-3.0 License](LICENSE), the same license as the project.

## Questions?

- **Discord**: [Join our community](https://discord.gg/fork-panel-xc)
- **Discussions**: [GitHub Discussions](https://github.com/fork-panel-xc/fork-panel-xc/discussions)
- **Email**: dev@fork-panel-xc.org

---

Thank you for contributing to Fork Panel XC! 🎉