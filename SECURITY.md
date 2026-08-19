# Security Policy

## Supported versions

Only the latest released version is supported with security fixes. There's no backport/LTS policy
— please upgrade to the latest release before reporting.

## Reporting a vulnerability

Please do not open a public GitHub issue for security vulnerabilities.

Report privately instead, either via
[GitHub Security Advisories](https://github.com/andrebarthelmeshellmuth/spryker-search-analyzer-config/security/advisories/new)
or by emailing mail@bathi.de.

Include the affected version, a description of the issue, and reproduction steps if you have them.

This package renders persisted term lists directly into OpenSearch/Elasticsearch `analysis` filter
definitions, and the do-not-decompound list is inlined as a Painless script literal (see README,
"Why a validator, not just escaping") — a bypass of `SearchAnalyzerConfigValidator`'s term pattern
that reaches a live cluster is treated as a security issue, not a bug report.

This is a single-maintainer open-source project with no formal SLA, but reports will be
acknowledged and triaged as soon as possible.
