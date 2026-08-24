---
name: Bug report
about: The SDK behaves incorrectly
labels: bug
---

### Describe the bug

A clear and concise description of what goes wrong.

### To reproduce

Steps, or a minimal code sample. A few lines that construct the client and issue the
command are worth more than a description of them:

```php
// minimal reproduction
```

### Expected behaviour

What you expected to happen instead.

### Actual behaviour

What actually happened. Include the error message or unexpected output verbatim —
paraphrased errors are usually the wrong error.

### Environment

- SDK version: <!-- composer show centralnic-reseller/php-sdk -->
- PHP version: <!-- php -v -->
- Backend: <!-- CentralNic Reseller (CNR) / Internet.bs (IBS) / Moniker -->
- System: <!-- OT&E or LIVE -->
- Used from: <!-- WHMCS / Blesta / standalone -->
- Operating system:

### Logs

<!-- markdownlint-disable-next-line MD033 -->
<details><summary>Relevant output</summary>

```text
paste here
```

<!-- markdownlint-disable-next-line MD033 -->
</details>

### Additional context

Anything else worth knowing — a workaround you found, when it started, whether it is
intermittent, whether it reproduces on both OT&E and LIVE.

> Please redact credentials, API keys, customer data and internal hostnames before
> posting. API responses often carry all four. For a security vulnerability, do
> **not** open an issue — see [SECURITY.md](../SECURITY.md).
