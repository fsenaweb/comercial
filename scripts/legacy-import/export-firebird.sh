#!/usr/bin/env bash
# Extrai produtos, clientes e os cadastros de apoio (categoria/marca/unidade)
# de um backup Firebird (.fbk) do ERP legado, gerando CSVs prontos para o
# `php artisan legacy:import` (backend/app/Console/Commands/ImportLegacyDataCommand.php).
#
# Não roda no PC da loja — roda aqui (ou em qualquer máquina com Docker),
# uma única vez por backup, e só os CSVs resultantes são levados para lá.
# Ver docs/11-migracao-sistema-legado.md para o contexto completo.
#
# Uso: scripts/legacy-import/export-firebird.sh <caminho-do-arquivo.fbk> [pasta-de-saida]
set -euo pipefail

FBK_PATH="${1:?Uso: export-firebird.sh <caminho-do-arquivo.fbk> [pasta-de-saida]}"
OUT_DIR="${2:-$(pwd)/storage/legacy-import}"
CONTAINER_NAME="legacy-firebird-export"
IMAGE="jacobalberty/firebird:2.5-sc"

if [ ! -f "$FBK_PATH" ]; then
  echo "Arquivo não encontrado: $FBK_PATH" >&2
  exit 1
fi

IN_DIR="$(mktemp -d)"
FBK_FILE="$(basename "$FBK_PATH")"
cp "$FBK_PATH" "$IN_DIR/$FBK_FILE"
mkdir -p "$OUT_DIR"

cleanup() {
  docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1 || true
  rm -rf "$IN_DIR"
}
trap cleanup EXIT

echo "Subindo Firebird temporário..."
docker rm -f "$CONTAINER_NAME" >/dev/null 2>&1 || true
docker run -d --name "$CONTAINER_NAME" \
  -e ISC_PASSWORD=masterkey \
  -e EnableLegacyClientAuth=true \
  -v "$IN_DIR":/backup \
  -v "$OUT_DIR":/output \
  "$IMAGE" >/dev/null

# Espera o Firebird aceitar conexões (o healthcheck da imagem demora alguns
# segundos para ficar "healthy").
for _ in $(seq 1 30); do
  if docker exec "$CONTAINER_NAME" test -x /usr/local/firebird/bin/gbak 2>/dev/null; then
    break
  fi
  sleep 1
done
sleep 3

echo "Restaurando backup ($FBK_FILE)..."
docker exec "$CONTAINER_NAME" /usr/local/firebird/bin/gbak -c \
  "/backup/$FBK_FILE" /firebird/data/legacy.fdb \
  -user SYSDBA -password masterkey >/dev/null

run_isql() {
  local sql_file="$1"
  docker cp "$sql_file" "$CONTAINER_NAME:/tmp/export.sql"
  docker exec "$CONTAINER_NAME" /usr/local/firebird/bin/isql \
    -user SYSDBA -password masterkey /firebird/data/legacy.fdb -i /tmp/export.sql
}

# O charset da base é NONE (Firebird não translitera nada — devolve os bytes
# crus). O texto real está em Windows-1252 (confirmado manualmente em
# 2026-07-18, ver docs/11-migracao-sistema-legado.md); por isso cada arquivo
# gerado pelo isql é convertido para UTF-8 com iconv logo em seguida.
export_table() {
  local name="$1"
  local select_sql="$2"
  local tmp_sql
  tmp_sql="$(mktemp)"
  cat > "$tmp_sql" <<SQL
SET HEADING OFF;
OUTPUT '/output/${name}.raw';
${select_sql}
OUTPUT;
SQL
  run_isql "$tmp_sql"
  rm -f "$tmp_sql"
  iconv -f WINDOWS-1252 -t UTF-8 "$OUT_DIR/${name}.raw" > "$OUT_DIR/${name}.csv"
  rm -f "$OUT_DIR/${name}.raw"
  echo "  -> $OUT_DIR/${name}.csv"
}

# Delimitador '|' (pipe): nenhum dos campos exportados costuma conter esse
# caractere; texto é sanitizado trocando '|' por espaço, por garantia.
# Colunas de cada arquivo documentadas nos comentários — precisam bater
# exatamente com a ordem esperada em ImportLegacyDataCommand::COLUMNS.
echo "Exportando cadastros..."

# grupo.csv: codigo|descricao
export_table "grupo" "
SELECT TRIM(CAST(CODIGO AS VARCHAR(20))) || '|' ||
       REPLACE(COALESCE(TRIM(DESCRICAO), ''), '|', ' ')
FROM GRUPO;"

# marca.csv: codigo|descricao
export_table "marca" "
SELECT TRIM(CAST(CODIGO AS VARCHAR(20))) || '|' ||
       REPLACE(COALESCE(TRIM(DESCRICAO), ''), '|', ' ')
FROM MARCA;"

# unidade.csv: codigo|descricao
export_table "unidade" "
SELECT TRIM(COALESCE(CODIGO, '')) || '|' ||
       REPLACE(COALESCE(TRIM(DESCRICAO), ''), '|', ' ')
FROM UNIDADE;"

# produto.csv: codigo|codbarra|descricao|referencia|grupo|unidade|fk_marca|
#              pr_custo|margem|pr_venda|qtd_atual|qtd_min|preco_atacado|
#              qtd_atacado|ativo|servico
export_table "produto" "
SELECT TRIM(CAST(CODIGO AS VARCHAR(20))) || '|' ||
       REPLACE(COALESCE(TRIM(CODBARRA), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(DESCRICAO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(REFERENCIA), ''), '|', ' ') || '|' ||
       TRIM(CAST(COALESCE(GRUPO, 0) AS VARCHAR(20))) || '|' ||
       TRIM(COALESCE(UNIDADE, '')) || '|' ||
       TRIM(CAST(COALESCE(FK_MARCA, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(PR_CUSTO, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(MARGEM, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(PR_VENDA, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(QTD_ATUAL, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(QTD_MIN, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(PRECO_ATACADO, 0) AS VARCHAR(20))) || '|' ||
       TRIM(CAST(COALESCE(QTD_ATACADO, 0) AS VARCHAR(20))) || '|' ||
       COALESCE(ATIVO, 'S') || '|' ||
       COALESCE(SERVICO, 'N')
FROM PRODUTO;"

# pessoa.csv (só clientes, CLI='S'): codigo|razao|fantasia|cnpj|tipo|endereco|
#             numero|complemento|bairro|municipio|uf|cep|fone1|celular1|email1|ativo
export_table "pessoa" "
SELECT TRIM(CAST(CODIGO AS VARCHAR(20))) || '|' ||
       REPLACE(COALESCE(TRIM(RAZAO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(FANTASIA), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(CNPJ), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(TIPO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(ENDERECO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(NUMERO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(COMPLEMENTO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(BAIRRO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(MUNICIPIO), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(UF), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(CEP), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(FONE1), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(CELULAR1), ''), '|', ' ') || '|' ||
       REPLACE(COALESCE(TRIM(EMAIL1), ''), '|', ' ') || '|' ||
       COALESCE(ATIVO, 'S')
FROM PESSOA WHERE CLI = 'S';"

echo "Extração concluída em $OUT_DIR"
