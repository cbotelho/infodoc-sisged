<?php

declare(strict_types=1);

namespace RelatorioAmostragem;

use InvalidArgumentException;
use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array{id:string,nome:string}>
     */
    public function getSetores(): array
    {
        $sql = <<<SQL
            SELECT id, TRIM(field_249) AS nome
            FROM app_entity_27
            WHERE TRIM(COALESCE(field_249, '')) <> ''
            ORDER BY field_249 ASC
        SQL;

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $setores = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            $nome = trim((string) ($row['nome'] ?? ''));

            if ($id === '' || $nome === '') {
                continue;
            }

            $setores[] = ['id' => $id, 'nome' => $nome];
        }

        if ($setores === []) {
            throw new InvalidArgumentException('Nenhum setor encontrado na tabela app_entity_27.');
        }

        return $setores;
    }

    /**
     * @return array<int, array<string, string|int|float>>
     */
    public function search(string $tenant, ReportFilter $filter): array
    {
        $sector = $this->resolveSectorById($filter->setor);

        if ($sector === null) {
            throw new InvalidArgumentException('Setor nao encontrado na tabela app_entity_27.');
        }

        $sectorName = (string) ($sector['nome'] ?? '');
        if ($this->isRhSectorName($sectorName)) {
            return $this->queryRhRows($filter, $tenant, (string) $sector['id'], $sectorName);
        }

        return $this->queryDefaultRows($filter, $tenant, (string) $sector['id'], $sectorName);
    }

    /**
     * @return array<int, array<string, string|int|float>>
     */
    private function queryDefaultRows(ReportFilter $filter, string $tenant, string $sectorId, string $sectorName): array
    {
        $from = $this->toTimestamp($filter->dataInicio . ' 00:00:00');
        $to = $this->toTimestamp($filter->dataFim . ' 23:59:59');

        $countSql = <<<SQL
            SELECT COUNT(*)
            FROM app_entity_43 d
            LEFT JOIN app_entity_41 c ON c.id = d.parent_item_id
            WHERE c.field_434 = :sector_id
              AND d.date_added BETWEEN :ts_inicio AND :ts_fim
        SQL;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->bindValue(':sector_id', $sectorId, PDO::PARAM_STR);
        $countStmt->bindValue(':ts_inicio', $from, PDO::PARAM_INT);
        $countStmt->bindValue(':ts_fim', $to, PDO::PARAM_INT);
        $countStmt->execute();

        $total = (int) $countStmt->fetchColumn();
        $sampleSize = $this->calculateSampleSize($total, $filter->percentual);

        if ($total === 0 || $sampleSize === 0) {
            return [];
        }

        $sql = <<<SQL
            SELECT
                d.id,
                TRIM(COALESCE(c.field_437, '')) AS numero_caixa,
                TRIM(COALESCE(d.field_446, '')) AS numero_documento,
                TRIM(COALESCE(d.field_447, '')) AS assunto,
                TRIM(COALESCE(d.field_448, '')) AS tipo_assunto,
                TRIM(COALESCE(d.field_445, '')) AS documento,
                CAST(COALESCE(d.field_554, 0) AS UNSIGNED) AS paginas,
                FROM_UNIXTIME(d.date_added) AS data_envio
            FROM app_entity_43 d
            LEFT JOIN app_entity_41 c ON c.id = d.parent_item_id
            WHERE c.field_434 = :sector_id
              AND d.date_added BETWEEN :ts_inicio AND :ts_fim
            ORDER BY RAND()
            LIMIT {$sampleSize}
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sector_id', $sectorId, PDO::PARAM_STR);
        $stmt->bindValue(':ts_inicio', $from, PDO::PARAM_INT);
        $stmt->bindValue(':ts_fim', $to, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, string|int|float>> $rows */
        $rows = $stmt->fetchAll();

        return $this->decorateRows($rows, $tenant, $filter, $sectorName, $total, $sampleSize);
    }

    /**
     * @return array<int, array<string, string|int|float>>
     */
    private function queryRhRows(ReportFilter $filter, string $tenant, string $sectorId, string $sectorName): array
    {
        $from = $this->toTimestamp($filter->dataInicio . ' 00:00:00');
        $to = $this->toTimestamp($filter->dataFim . ' 23:59:59');

        $countSql = <<<SQL
            SELECT COUNT(*)
            FROM app_entity_49 d
            LEFT JOIN app_entity_48 c ON c.id = d.parent_item_id
            WHERE c.field_525 = :sector_id
              AND d.date_added BETWEEN :ts_inicio AND :ts_fim
        SQL;

        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->bindValue(':sector_id', $sectorId, PDO::PARAM_STR);
        $countStmt->bindValue(':ts_inicio', $from, PDO::PARAM_INT);
        $countStmt->bindValue(':ts_fim', $to, PDO::PARAM_INT);
        $countStmt->execute();

        $total = (int) $countStmt->fetchColumn();
        $sampleSize = $this->calculateSampleSize($total, $filter->percentual);

        if ($total === 0 || $sampleSize === 0) {
            return [];
        }

        $sql = <<<SQL
            SELECT
                d.id,
                TRIM(COALESCE(c.field_527, d.field_553, '')) AS numero_caixa,
                TRIM(COALESCE(d.field_543, '')) AS numero_documento,
                TRIM(COALESCE(d.field_544, '')) AS assunto,
                TRIM(COALESCE(d.field_545, '')) AS tipo_assunto,
                TRIM(COALESCE(d.field_542, '')) AS documento,
                CAST(COALESCE(d.field_552, 0) AS UNSIGNED) AS paginas,
                FROM_UNIXTIME(d.date_added) AS data_envio
            FROM app_entity_49 d
            LEFT JOIN app_entity_48 c ON c.id = d.parent_item_id
            WHERE c.field_525 = :sector_id
              AND d.date_added BETWEEN :ts_inicio AND :ts_fim
            ORDER BY RAND()
            LIMIT {$sampleSize}
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sector_id', $sectorId, PDO::PARAM_STR);
        $stmt->bindValue(':ts_inicio', $from, PDO::PARAM_INT);
        $stmt->bindValue(':ts_fim', $to, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, string|int|float>> $rows */
        $rows = $stmt->fetchAll();

        return $this->decorateRows($rows, $tenant, $filter, $sectorName, $total, $sampleSize);
    }

    /**
     * @param array<int, array<string, string|int|float>> $rows
     * @return array<int, array<string, string|int|float>>
     */
    private function decorateRows(array $rows, string $tenant, ReportFilter $filter, string $sectorName, int $total, int $sampleSize): array
    {
        foreach ($rows as &$row) {
            $documento = (string) ($row['documento'] ?? '');
            $safeDocumento = ltrim(str_replace('\\', '/', $documento), '/');
            $dataEnvio = (string) ($row['data_envio'] ?? '');

            $row['tenant'] = $tenant;
            $row['setor'] = $sectorName;
            $row['percentual'] = $filter->percentual;
            $row['link_documento'] = $safeDocumento === '' ? '' : $this->buildDocumentUrl($tenant, $safeDocumento);
            $row['data_registro'] = $this->formatDate($dataEnvio);
            $row['total_itens'] = $total;
            $row['itens_amostrados'] = $sampleSize;
        }
        unset($row);

        return $rows;
    }

    private function resolveSectorById(string $sectorId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, TRIM(field_249) AS nome FROM app_entity_27 WHERE id = ? LIMIT 1');
        $stmt->execute([$sectorId]);

        $sector = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($sector) ? $sector : null;
    }

    private function isRhSectorName(string $setor): bool
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $setor));

        return in_array($normalized, ['SEFAZRH', 'CASACIVILRH'], true);
    }

    private function calculateSampleSize(int $total, string $percentual): int
    {
        if ($total <= 0) {
            return 0;
        }

        $percentualFloat = (float) str_replace(',', '.', $percentual);
        $sampleSize = (int) ceil($total * ($percentualFloat / 100));

        return max(1, min($total, $sampleSize));
    }

    private function toTimestamp(string $datetime): int
    {
        $value = strtotime($datetime);

        return $value === false ? 0 : $value;
    }

    private function formatDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function buildDocumentUrl(string $tenant, string $relativePath): string
    {
        $baseUrl = rtrim($this->resolveBaseUrl($tenant), '/');
        $normalizedPath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');

        $segments = array_values(array_filter(explode('/', $normalizedPath), static fn(string $segment): bool => $segment !== ''));
        $encodedPath = implode('/', array_map('rawurlencode', $segments));

        if ($encodedPath === '') {
            return '';
        }

        if (str_starts_with($encodedPath, 'upload/')) {
            return $baseUrl . '/' . $encodedPath;
        }

        return $baseUrl . '/upload/' . $encodedPath;
    }

    private function resolveBaseUrl(string $tenant): string
    {
        $appBaseUrl = trim((string) (getenv('APP_BASE_URL') ?: ''));
        if ($appBaseUrl !== '') {
            return $appBaseUrl;
        }

        $tenantNormalized = strtolower(trim($tenant));
        if ($tenantNormalized === 'cipemac') {
            return 'https://cipemac.infodocsisged.com.br';
        }

        return 'https://gea.infodocsisged.com.br';
    }
}
