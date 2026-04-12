# GitHub Workflow

Workflow conventions for this project.

---

## 1. Branching

- `main` — production, always stable
- Feature branches off `main`:
  - `feature/...` — new features
  - `fix/...` — bug fixes
  - `chore/...` — maintenance / tooling

```bash
feature/dashboard
fix/api-timeout
```

Merge back to `main` via Pull Request when the feature is tested and ready.

---

## 2. Commit Convention (Conventional Commits)

Format:

```
type: message
type(scope): message
```

Scope is optional — use it when it adds clarity.

### Types

- `feat` — new feature
- `fix` — bug fix
- `chore` — maintenance
- `refactor` — code change without behavior change
- `test` — adding or updating tests
- `docs` — documentation

### Examples

```
feat: optimize search with Turbo Frame partial response
fix: separate tracked/filtered counts, improve pages layout (#2, #3)
refactor(controller): simplify search hint logic (#3)
test: add mutation-killing tests for pagination and events
```

### Linking GitHub issues

Use `Fixes #N` to auto-close issues on merge:

```
fix(auth): handle invalid token (Fixes #123)
```

---

## 3. Development Workflow

1. Create a GitHub issue
2. Create a feature branch from `main`
3. Develop with clean, focused commits
4. Open a Pull Request
5. Test from the feature branch
6. Merge into `main`

```bash
git checkout -b fix/login-error
git commit -m "fix(auth): handle null response (Fixes #123)"
```

---

## 4. Pull Request

### Title

Same convention as commits:

```
fix(auth): handle login crash on empty response
```

### Description template

```md
## Summary
- handle null case in auth response
- add fallback behavior

## Test plan
- [ ] tested locally
- [ ] unit tests added

Fixes #123
```

---

## 5. Code Review

- At least 1 reviewer when collaborating
- Check: business logic, readability, tests, side effects
- Keep PRs small and focused

---

## 6. Merge Strategy

- **CLI:** `git checkout main && git pull && git merge <branch> && git push` — fast-forwards when possible, preserving granular commit history
- **GitHub UI:** Use "Rebase and merge" button on the PR page, then "Delete branch"
- Commits should already be clean and focused before merging (no "WIP" or "fix typo" commits)
- `Fixes #N` in the **PR description** auto-closes issues on merge
- Delete the feature branch after merge (locally and on remote)

```bash
git checkout main
git pull
git merge feature/dashboard
git push
git branch -d feature/dashboard
git push origin --delete feature/dashboard
```
