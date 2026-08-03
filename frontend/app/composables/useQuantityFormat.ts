/**
 * Formata quantidade pra exibição: produto de unidade inteira mostra "3",
 * não "3,000"; produto fracionado mostra só as casas decimais necessárias
 * (ex. "0,5", "2,75"), até 3 casas (escala da coluna quantity no banco).
 */
export function useQuantityFormat() {
  function formatQuantity(value: number | string): string {
    const n = Number(value)

    return n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 })
  }

  return { formatQuantity }
}
