<?php

declare(strict_types=1);

namespace RelatorioAmostragem;

final class ReportFilter
{
    public function __construct(
        public readonly string $dataInicio,
        public readonly string $dataFim,
        public readonly string $percentual,
        public readonly string $setor,
        public readonly string $formato,
    ) {
    }
}
