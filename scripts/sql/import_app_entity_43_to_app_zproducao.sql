-- Importa dados de app_entity_43 para app_zproducao
-- Regras de mapeamento:
-- date_added (bigint) -> data_registro (date)
-- field_448 -> interessado
-- field_554 -> paginas
-- CONCAT('https://gea.infodocsisged.com.br/upload/', field_445) -> arquivo
-- field_565: 11 => SEFAZ - RECEITA, 12 => SEFAZ - TESOURO RH, 14 => SEFAZ - RECEITA

INSERT INTO app_zproducao (
    data_registro,
    interessado,
    paginas,
    arquivo,
    setor
)
SELECT
    DATE(
        FROM_UNIXTIME(
            CASE
                WHEN `date_added` > 9999999999 THEN `date_added` / 1000
                ELSE `date_added`
            END
        )
    ) AS data_registro,
    `field_448` AS interessado,
    `field_554` AS paginas,
    CONCAT('https://gea.infodocsisged.com.br/upload/', COALESCE(`field_445`, '')) AS arquivo,
    CASE
        WHEN CAST(`field_565` AS UNSIGNED) = 11 THEN 'SEFAZ - RECEITA'
        WHEN CAST(`field_565` AS UNSIGNED) = 12 THEN 'SEFAZ - TESOURO RH'
        WHEN CAST(`field_565` AS UNSIGNED) = 14 THEN 'SEFAZ - RECEITA'
    END AS setor
FROM app_entity_43
WHERE CAST(`field_565` AS UNSIGNED) IN (11, 12, 14);
