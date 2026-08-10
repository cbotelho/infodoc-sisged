-- Importa dados de app_entity_43 para app_zproducao
-- Regras de mapeamento:
-- field_448  -> interessado
-- field_554  -> paginas
-- date_added -> data_registro
-- CONCAT('https://cipemac.infodocsisged.com.br/upload/', field_445) -> arquivo
-- setor fixo -> 'CIPEMAC - GAB/SEIP'

INSERT INTO app_zproducao (
    interessado,
    paginas,
    data_registro,
    arquivo,
    setor
)
SELECT
    TRIM(COALESCE(field_448, '')) AS interessado,
    CAST(COALESCE(field_554, 0) AS UNSIGNED) AS paginas,
    DATE(
        FROM_UNIXTIME(
            CASE
                WHEN date_added > 9999999999 THEN date_added / 1000
                ELSE date_added
            END
        )
    ) AS data_registro,
    CONCAT('https://cipemac.infodocsisged.com.br/upload/', TRIM(COALESCE(field_445, ''))) AS arquivo,
    'CIPEMAC - GAB/SEIP' AS setor
FROM app_entity_43
WHERE TRIM(COALESCE(field_448, '')) <> '';
