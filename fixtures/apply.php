<?php

/**
 * This file is part of the spryker-community/search-analyzer-config package.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

/**
 * Applies this package's demo-fixture claim (see the toolkit repo's FIXTURE_CLAIMS.md) to a real
 * b2b-demo-marketplace checkout's shared import CSVs. Idempotent — safe to re-run; each change is
 * applied only if not already present.
 *
 * This package ships no Yves widget and no permission-gated feature, so unlike its sibling packages
 * there is no test-customer/permission fixture to apply here — just the shared "Feldwerk" demo catalog
 * (fixtures/demo-catalog/*.csv): 12 entirely fictional products (10 chairs + 1 hand trolley + 1 paper
 * shredder), own SVG-data-URI images, own DE pricing — used by this package's own README screenshots
 * instead of this demoshop's real, licensed supplier catalog (real brand photography/copy that can't be
 * redistributed publicly). SHARED with the sibling search-debug/search-feedback/search-ranking/
 * search-ranking-optimizer packages, same "first one creates it, rest skip" idempotency, keyed by
 * abstract_sku/concrete_sku. See search-toolkit's FIXTURE_CLAIMS.md.
 *
 * Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace
 *
 * Then, from that demoshop checkout:
 *   ./docker/sdk console data:import product-abstract
 *   ./docker/sdk console data:import product-abstract-store
 *   ./docker/sdk console data:import product-approval-status
 *   ./docker/sdk console data:import product-concrete
 *   ./docker/sdk console data:import product-stock
 *   ./docker/sdk console data:import product-image
 *   ./docker/sdk console data:import product-price
 */

$demoshopRoot = $argv[1] ?? null;

if ($demoshopRoot === null || !is_dir($demoshopRoot)) {
    fwrite(STDERR, "Usage: php fixtures/apply.php /path/to/b2b-demo-marketplace\n");

    exit(1);
}

$dataDir = rtrim($demoshopRoot, '/') . '/data/import/common/common';

if (!is_dir($dataDir)) {
    fwrite(STDERR, "Not a b2b-demo-marketplace checkout (missing $dataDir)\n");

    exit(1);
}

/**
 * @param string $path
 *
 * @return array{header: array<int, string>, rows: array<int, array<string, string>>}
 */
function readCsv(string $path): array
{
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);
    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== count($header)) {
            continue;
        }

        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    return ['header' => $header, 'rows' => $rows];
}

/**
 * @param string $path
 * @param array<int, string> $header
 * @param array<int, array<string, string>> $rows
 */
function writeCsv(string $path, array $header, array $rows): void
{
    $handle = fopen($path, 'w');
    fputcsv($handle, $header);

    foreach ($rows as $row) {
        fputcsv($handle, array_map(fn (string $key): string => $row[$key] ?? '', $header));
    }

    fclose($handle);
}

/**
 * Idempotently appends every row from $ownCsvPath into $targetPath whose $dedupColumns values aren't
 * already present in the target.
 *
 * @param string $targetPath
 * @param string $ownCsvPath
 * @param array<int, string> $dedupColumns
 *
 * @return int Number of rows added.
 */
function mergeCsvRows(string $targetPath, string $ownCsvPath, array $dedupColumns): int
{
    $target = readCsv($targetPath);
    $existingKeys = [];

    foreach ($target['rows'] as $row) {
        $existingKeys[dedupKey($row, $dedupColumns)] = true;
    }

    $own = readCsv($ownCsvPath);
    $added = 0;

    foreach ($own['rows'] as $row) {
        $key = dedupKey($row, $dedupColumns);

        if (isset($existingKeys[$key])) {
            continue;
        }

        $target['rows'][] = $row;
        $existingKeys[$key] = true;
        $added++;
    }

    if ($added > 0) {
        writeCsv($targetPath, $target['header'], $target['rows']);
    }

    return $added;
}

/**
 * @param array<string, string> $row
 * @param array<int, string> $columns
 */
function dedupKey(array $row, array $columns): string
{
    return implode("\0", array_map(fn (string $column): string => $row[$column] ?? '', $columns));
}

/**
 * @param string $dataDir
 * @param string $demoshopRoot
 * @param string $demoCatalogDir
 *
 * @return int Total rows added across all 7 demo-catalog CSVs.
 */
function applyDemoCatalog(string $dataDir, string $demoshopRoot, string $demoCatalogDir): int
{
    $added = 0;
    $added += mergeCsvRows($dataDir . '/product_abstract.csv', $demoCatalogDir . '/product_abstract.csv', ['abstract_sku']);
    $added += mergeCsvRows($demoshopRoot . '/data/import/common/DE/product_abstract_store.csv', $demoCatalogDir . '/product_abstract_store_DE.csv', ['abstract_sku', 'store_name']);
    $added += mergeCsvRows($dataDir . '/product_abstract_approval_status.csv', $demoCatalogDir . '/product_abstract_approval_status.csv', ['sku']);
    $added += mergeCsvRows($dataDir . '/product_concrete.csv', $demoCatalogDir . '/product_concrete.csv', ['concrete_sku']);
    $added += mergeCsvRows($dataDir . '/product_stock.csv', $demoCatalogDir . '/product_stock.csv', ['concrete_sku']);
    $added += mergeCsvRows($dataDir . '/product_image.csv', $demoCatalogDir . '/product_image.csv', ['abstract_sku', 'locale']);
    $added += mergeCsvRows($demoshopRoot . '/data/import/common/DE/product_price.csv', $demoCatalogDir . '/product_price_DE.csv', ['abstract_sku', 'store']);

    return $added;
}

$demoCatalogRowsAdded = applyDemoCatalog($dataDir, $demoshopRoot, __DIR__ . '/demo-catalog');
echo "Feldwerk demo catalog: $demoCatalogRowsAdded row(s) added\n";

echo "\nDone. Now run (from the demoshop root):\n";
echo "  ./docker/sdk console data:import product-abstract\n";
echo "  ./docker/sdk console data:import product-abstract-store\n";
echo "  ./docker/sdk console data:import product-approval-status\n";
echo "  ./docker/sdk console data:import product-concrete\n";
echo "  ./docker/sdk console data:import product-stock\n";
echo "  ./docker/sdk console data:import product-image\n";
echo "  ./docker/sdk console data:import product-price\n";
