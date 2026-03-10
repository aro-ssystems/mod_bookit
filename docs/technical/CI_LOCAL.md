# Running Moodle Plugin CI Locally

All CI steps run inside the Docker container `moodlestack_bookit-dev-webserver-1`.

## Prerequisites

`moodle-plugin-ci` must be available inside the container. It is **not** mounted by default,
so it must be copied once per container lifecycle:

```bash
docker cp /home/a/Code/repos/moodlestack/repos/moodle-plugin-ci \
    moodlestack_bookit-dev-webserver-1:/moodle-plugin-ci
```

Also create the missing phpcompatibility stub (needed by Moodle's grunt `ignorefiles` task):

```bash
docker exec moodlestack_bookit-dev-webserver-1 bash -c \
  "mkdir -p /var/www/html/local/codechecker/vendor/phpcompatibility/php-compatibility"
```

---

## Variables

```bash
CI=/moodle-plugin-ci/bin/moodle-plugin-ci
MOODLE=/var/www/html
PLUGIN=/var/www/html/mod/bookit
```

---

## CI Steps

Run each step inside Docker:

```bash
docker exec moodlestack_bookit-dev-webserver-1 bash -c "<command>"
```

### 1. PHP Lint

```bash
$CI phplint $PLUGIN
```

### 2. PHP Code Sniffer

Run with the bundled phpcs binary (the top-level `$CI phpcs` command fails locally due to missing
moodle standard setup in PATH; use the vendor binary instead):

```bash
/moodle-plugin-ci/vendor/bin/phpcs \
  --config-set installed_paths \
  /moodle-plugin-ci/vendor/moodlehq/moodle-cs,/moodle-plugin-ci/vendor/phpcompatibility/php-compatibility,/moodle-plugin-ci/vendor/phpcsstandards/phpcsextra,/moodle-plugin-ci/vendor/phpcsstandards/phpcsutils

$CI phpcs $PLUGIN
```

### 3. PHP Mess Detector

```bash
$CI phpmd $PLUGIN
```

Exit 0 — violations are warnings only, do not fail CI.

### 4. PHP Doc Checker

```bash
$CI phpdoc $PLUGIN
```

### 5. Validate

```bash
$CI validate --moodle $MOODLE $PLUGIN
```

### 6. Upgrade Savepoints

```bash
$CI savepoints --moodle $MOODLE $PLUGIN
```

### 7. Mustache Lint

```bash
IGNORE_NAMES="internal_name_field.mustache" $CI mustache --moodle $MOODLE $PLUGIN
```

**Known limitation:** The HTML validator (`html5.validator.nu`) is not reachable locally.
This causes exit 1 with "Problem calling HTML validator" but does **not** indicate real mustache
errors. In GitHub Actions the validator is accessible and this step passes.
Locally this failure is expected and acceptable.

### 8. Grunt (AMD / ESLint)

```bash
$CI grunt --moodle $MOODLE --tasks amd --max-lint-warnings 0 $PLUGIN
```

### 9. PHPUnit

```bash
cd $MOODLE && vendor/bin/phpunit --configuration phpunit.xml --testsuite mod_bookit_testsuite
```

---

## Running All Steps (Script)

```bash
docker exec moodlestack_bookit-dev-webserver-1 bash -c "
CI=/moodle-plugin-ci/bin/moodle-plugin-ci
MOODLE=/var/www/html
PLUGIN=/var/www/html/mod/bookit

echo '--- phplint ---'
\$CI phplint \$PLUGIN

echo '--- phpcs ---'
/moodle-plugin-ci/vendor/bin/phpcs --config-set installed_paths \
  /moodle-plugin-ci/vendor/moodlehq/moodle-cs,/moodle-plugin-ci/vendor/phpcompatibility/php-compatibility,/moodle-plugin-ci/vendor/phpcsstandards/phpcsextra,/moodle-plugin-ci/vendor/phpcsstandards/phpcsutils
\$CI phpcs \$PLUGIN

echo '--- phpmd ---'
\$CI phpmd \$PLUGIN

echo '--- phpdoc ---'
\$CI phpdoc \$PLUGIN

echo '--- validate ---'
\$CI validate --moodle \$MOODLE \$PLUGIN

echo '--- savepoints ---'
\$CI savepoints --moodle \$MOODLE \$PLUGIN

echo '--- mustache (expected to fail locally - HTML validator unavailable) ---'
IGNORE_NAMES='internal_name_field.mustache' \$CI mustache --moodle \$MOODLE \$PLUGIN || true

echo '--- grunt amd ---'
\$CI grunt --moodle \$MOODLE --tasks amd --max-lint-warnings 0 \$PLUGIN

echo '--- phpunit ---'
cd \$MOODLE && vendor/bin/phpunit --configuration phpunit.xml --testsuite mod_bookit_testsuite
"
```

---

## Expected Results

| Step        | Expected  | Notes                                          |
|-------------|-----------|------------------------------------------------|
| phplint     | ✅ exit 0 |                                                |
| phpcs       | ✅ exit 0 |                                                |
| phpmd       | ✅ exit 0 | Violations are warnings, not errors            |
| phpdoc      | ✅ exit 0 |                                                |
| validate    | ✅ exit 0 |                                                |
| savepoints  | ✅ exit 0 |                                                |
| mustache    | ⚠️ exit 1 | HTML validator unreachable locally — OK        |
| grunt amd   | ✅ exit 0 |                                                |
| phpunit     | ✅ exit 0 | 68 tests, 305 assertions                       |
