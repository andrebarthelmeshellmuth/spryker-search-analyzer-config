## What & why



## Checklist

- [ ] `composer validate --no-check-publish` passes
- [ ] `vendor/bin/phpcs` passes
- [ ] `vendor/bin/phpmd src text phpmd.xml` / `phpmd-public-methods.xml` pass
- [ ] `composer rector-dry-run` passes
- [ ] `composer check-floors` passes
- [ ] `composer phpstan` passes (run from a host shop)
- [ ] Tests added/updated, or explained why not needed
- [ ] `README.md` updated if behavior changed
