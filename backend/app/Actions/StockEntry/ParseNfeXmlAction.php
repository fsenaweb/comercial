<?php

namespace App\Actions\StockEntry;

use App\Exceptions\InvalidNfeXmlException;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;

class ParseNfeXmlAction
{
    /**
     * Parser "na mão" com SimpleXML, sem pacote de NF-e — só os campos que
     * este sistema realmente precisa (fornecedor, itens, totais, duplicatas).
     * O `xmlns` default é removido do conteúdo antes do parse: sem isso,
     * cada tag exigiria navegação via `children($namespace)`, e o layout da
     * NF-e é praticamente todo nesse único namespace.
     */
    public function execute(UploadedFile $file): array
    {
        libxml_use_internal_errors(true);

        $content = preg_replace('/xmlns="[^"]*"/', '', $file->get());
        $xml = simplexml_load_string($content ?? '');

        if ($xml === false || $content === null) {
            libxml_clear_errors();

            throw new InvalidNfeXmlException();
        }

        $infNFe = $xml->getName() === 'nfeProc' ? ($xml->NFe->infNFe ?? null) : ($xml->infNFe ?? null);

        if ($infNFe === null || ! isset($infNFe->ide, $infNFe->emit, $infNFe->det)) {
            throw new InvalidNfeXmlException();
        }

        $nfeKey = null;
        $id = (string) $infNFe->attributes()->Id;
        if ($id !== '' && str_starts_with($id, 'NFe')) {
            $nfeKey = substr($id, 3);
        }

        $items = [];
        foreach ($infNFe->det as $det) {
            $prod = $det->prod;
            $ean = (string) $prod->cEAN;
            $items[] = [
                'product_code' => (string) $prod->cProd,
                'ean' => in_array($ean, ['', 'SEM GTIN'], true) ? null : $ean,
                'description' => (string) $prod->xProd,
                'quantity' => (float) $prod->qCom,
                'unit_cost' => (float) $prod->vUnCom,
                'total' => (float) $prod->vProd,
            ];
        }

        $duplicatas = [];
        if (isset($infNFe->cobr->dup)) {
            foreach ($infNFe->cobr->dup as $dup) {
                $duplicatas[] = [
                    'number' => (string) $dup->nDup,
                    'due_date' => (string) $dup->dVenc,
                    'amount' => (float) $dup->vDup,
                ];
            }
        }

        return [
            'nfe_key' => $nfeKey,
            'nfe_number' => (string) $infNFe->ide->nNF,
            'nfe_series' => (string) $infNFe->ide->serie,
            'issue_date' => substr((string) $infNFe->ide->dhEmi, 0, 10) ?: null,
            'emit' => [
                'cnpj' => (string) $infNFe->emit->CNPJ,
                'name' => (string) $infNFe->emit->xNome,
            ],
            'items' => $items,
            'freight_value' => (float) ($infNFe->total->ICMSTot->vFrete ?? 0),
            'products_total' => (float) ($infNFe->total->ICMSTot->vProd ?? 0),
            'total_value' => (float) ($infNFe->total->ICMSTot->vNF ?? 0),
            'duplicatas' => $duplicatas,
        ];
    }
}
