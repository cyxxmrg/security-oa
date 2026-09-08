# OA E2E Tests

These tests call the deployed API at `http://127.0.0.1:8081/api`. They use
fixed test accounts, modify OA records, and some tests clear Redis databases 0
and 1 on `127.0.0.1:6379`.

Run them only on an isolated test deployment:

```bash
OA_E2E_ALLOW_MUTATION=1 vendor/bin/phpunit -c phpunit.e2e.xml
```

The default `phpunit.xml` excludes this directory. The E2E base test also
refuses to run unless `OA_E2E_ALLOW_MUTATION=1` is explicitly set.
