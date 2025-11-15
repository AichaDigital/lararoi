# Contributing to Lararoi

Thank you for your interest in contributing to Lararoi! This document provides guidelines for contributing to the project.

## Branch Conventions

We use a naming convention for branches that helps organize work:

### Branch Types

- `feature/` - For new features
  - Example: `feature/add-new-provider`
  - Example: `feature/improve-caching`

- `fix/` or `bugfix/` - For bug fixes
  - Example: `fix/vies-timeout-issue`
  - Example: `bugfix/cache-expiration`

- `hotfix/` - For urgent production fixes
  - Example: `hotfix/security-vulnerability`

- `refactor/` - For code refactoring
  - Example: `refactor/provider-manager`

- `docs/` - For documentation updates
  - Example: `docs/update-readme`

- `test/` - For adding or improving tests
  - Example: `test/add-integration-tests`

- `chore/` - For maintenance tasks
  - Example: `chore/update-dependencies`

### Protected Branch

- `main` - Main protected branch, can only be updated via Pull Requests

## Contribution Process

1. **Fork the repository**
2. **Create a branch** following the naming conventions
   ```bash
   git checkout -b feature/my-new-feature
   ```

3. **Make commits** with descriptive messages in English
   ```bash
   git commit -m "Add new VAT provider for Italy"
   ```

4. **Run tests and validations locally**
   ```bash
   composer test
   composer analyse
   composer format
   ```

5. **Push the branch**
   ```bash
   git push origin feature/my-new-feature
   ```

6. **Create Pull Request** to `main`

## Code Standards

### Tests
- All new features must include tests
- Existing tests must continue to pass
- Minimum coverage: 20%
- Use Pest PHP to write tests

### Static Analysis
- PHPStan level 5 must pass without errors
- Run `composer analyse` before committing

### Code Style
- Follow Laravel Pint
- Run `composer format` before committing
- Style commits will be made automatically in CI/CD

### Code Conventions
- Use PHP 8.4+ features
- Typed properties mandatory
- DocBlocks in English
- User documentation in English

## Pull Request Checklist

Before submitting a PR, make sure:

- [ ] Tests pass (`composer test`)
- [ ] PHPStan reports no errors (`composer analyse`)
- [ ] Code is formatted (`composer format`)
- [ ] Tests added for new features
- [ ] Documentation is updated
- [ ] Commit message is descriptive
- [ ] No unnecessary changes or temporary files

## GitHub Actions / CI/CD

The project automatically runs:

1. **Tests** - On multiple PHP and Laravel versions
2. **PHPStan** - Static analysis level 5
3. **Code Style** - Auto-fix with Laravel Pint
4. **Code Coverage** - Coverage report with Codecov

All checks must pass before a PR can be merged.

## Questions or Issues

If you have questions or encounter issues, please:
- Open an Issue on GitHub
- Provide complete details of the problem
- Include code examples if relevant

## License

By contributing, you agree that your contributions will be licensed under the project's MIT license.
