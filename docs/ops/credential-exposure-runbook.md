# Credential exposure response

## Status after the October 2026 cleanup

The seven repository branches were rewritten and force-pushed on 7 October 2026.
The committed `.env` was removed from their history, exposed Symfony/JWT values
were removed, and development examples were anonymized, including compiled assets.
A fresh clone of those branches was checked against the sanitized objects.

This did **not** rotate deployed credentials. It also did **not** complete GitHub's
server-side purge: 101 pull request refs were still advertised, and an original
sensitive commit was still accessible by SHA after the rewrite. GitHub Support
must finish that purge. Do not mark the incident closed until rotation and purge
have been confirmed. Never put the original values in an issue, PR, or this document.

## Rotate credentials that were actually deployed

1. Identify environments that used the exposed `APP_SECRET` or `JWT_PASSPHRASE`.
   Review local ignored configuration, hosting variables, and the secret manager
   without copying values into logs or Git.
2. Generate replacements in the secret manager and coordinate deployment. Changing
   `APP_SECRET` can invalidate application signatures or sessions that depend on it.
3. Check whether the JWT private key was exposed or reused with the exposed
   passphrase. If so, generate a new keypair and passphrase, deploy them together,
   and invalidate tokens issued with the old key. A passphrase change alone does
   not revoke an exposed signing key.
4. Replace any Mercure, database, or demo credential that was reused outside local
   development. Deploy matching Mercure keys to the publisher and hub together.
5. Verify login, refresh tokens, and authorized event subscriptions after deployment.

`TEST_ONLY_*`, `REMOVED_*`, and `CONFIGURE_*` values are public placeholders. They
must not be used as production secrets. Existing test fixtures are not secret stores.

## Finish the GitHub purge

Contact GitHub Support privately with the repository URL, the first changed commits
reported by `git-filter-repo`, and the affected pull request refs. Request removal
of cached views and dereferencing of the affected PRs so the old objects can be
garbage-collected. The cleanup operator retained these identifiers in the local
audit report; do not copy any secret value into the support request.

A force-push changes branch history; it cannot remove GitHub's internal PR refs or
copies in someone else's clone. Follow GitHub's
[sensitive-data removal procedure](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository).

## Use the rewritten history

Freshly clone the repository when possible. Before replacing another checkout,
preserve its uncommitted work and stashes. Reapply only reviewed changes to the new
clone. Do not merge or push an old branch, because that can restore the old history.

The two worktrees and six stashes handled during this cleanup were already migrated.
Other clones and forks require their own cleanup.

## Prevent another exposure

The `Secret scan / Scan Git history` workflow scans all commits reachable from the
checked out revision on PRs and main pushes. It uses Gitleaks' default rules plus
checks for local environment files and generated Symfony/JWT secrets. Output is
fully redacted. It does not fetch historical PR refs or scan ignored local files.

Run the same check locally with Gitleaks 8.30.1:

```sh
gitleaks git . --config .gitleaks.toml --log-opts=HEAD --redact=100 --no-banner
```

After the workflow is merged and has passed, a repository administrator should make
`Scan Git history` a required check in the main branch rules. This PR does not change
branch protection settings. CI detection happens after a push, so never rely on it
to keep a committed value private. Use GitHub push protection when available and
review staged changes before committing.

For a scanner finding, first decide whether the value is real. Revoke or rotate real
credentials, remove their history, and rerun the check. For a verified false positive,
allow only the exact synthetic value or fingerprint with a documented explanation;
do not exclude whole source, fixture, or configuration directories.
