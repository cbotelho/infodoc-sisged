<?php

declare(strict_types=1);

namespace RelatorioAmostragem;

use DateTimeImmutable;
use InvalidArgumentException;

final class InputValidator
{
    /**
     * @param array<int, array{id:string,nome:string}> $setores
     */
    public function validate(array $input, array $setores): ReportFilter
    {
        $dataInicio = $this->sanitize((string) ($input['data_inicio'] ?? ''));
        $dataFim = $this->sanitize((string) ($input['data_fim'] ?? ''));
        $percentual = $this->sanitize((string) ($input['percentual'] ?? ''));
        $setor = $this->sanitize((string) ($input['setor'] ?? ''));
        $formato = strtolower($this->sanitize((string) ($input['formato'] ?? '')));

        $this->validateDate($dataInicio, 'Data inicial invalida.');
        $this->validateDate($dataFim, 'Data final invalida.');
        $this->validateDateRange($dataInicio, $dataFim);
        $this->validatePercentual($percentual);
        $this->validateSetor($setor, $setores);
        $this->validateFormato($formato);

        return new ReportFilter($dataInicio, $dataFim, $percentual, $setor, $formato);
    }

    private function sanitize(string $value): string
    {
        return trim(filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '');
    }

    private function validateDate(string $date, string $message): void
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException($message);
        }
    }

    private function validateDateRange(string $inicio, string $fim): void
    {
        $inicioDate = new DateTimeImmutable($inicio);
        $fimDate = new DateTimeImmutable($fim);

        if ($inicioDate > $fimDate) {
            throw new InvalidArgumentException('Data inicial nao pode ser maior que a data final.');
        }
    }

    private function validatePercentual(string $percentual): void
    {
        if (!is_numeric($percentual)) {
            throw new InvalidArgumentException('Percentual de amostragem invalido.');
        }

        $value = (float) str_replace(',', '.', $percentual);
        if ($value < 0.5 || $value > 100) {
            throw new InvalidArgumentException('Percentual de amostragem deve estar entre 0.5 e 100.');
        }

        $scaled = (int) round($value * 10);
        if ($scaled % 5 !== 0) {
            throw new InvalidArgumentException('Percentual deve usar incrementos de 0.5.');
        }
    }

    /**
     * @param array<int, array{id:string,nome:string}> $setores
     */
    private function validateSetor(string $setor, array $setores): void
    {
        $setoresValidos = array_column($setores, 'id');

        if (!in_array($setor, $setoresValidos, true)) {
            throw new InvalidArgumentException('Setor invalido.');
        }
    }

    private function validateFormato(string $formato): void
    {
        $formatos = ['xlsx', 'docx', 'pdf', 'json', 'xml', 'txt'];

        if (!in_array($formato, $formatos, true)) {
            throw new InvalidArgumentException('Formato de exportacao invalido.');
        }
    }
}
