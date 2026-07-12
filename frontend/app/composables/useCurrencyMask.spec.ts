import { describe, expect, it } from 'vitest'
import { useCurrencyMask } from './useCurrencyMask'

describe('useCurrencyMask', () => {
  const { maskInput, toNumber } = useCurrencyMask()

  it('formats digits into BRL currency, pushing cents', () => {
    expect(maskInput('1')).toBe('R$ 0,01')
    expect(maskInput('150')).toBe('R$ 1,50')
    expect(maskInput('150000')).toBe('R$ 1.500,00')
  })

  it('ignores non-digit characters', () => {
    expect(maskInput('R$ 1.234,56')).toBe('R$ 1.234,56')
  })

  it('treats empty input as zero', () => {
    expect(maskInput('')).toBe('R$ 0,00')
  })

  it('converts a masked value back to a number', () => {
    expect(toNumber('R$ 1.234,56')).toBeCloseTo(1234.56)
    expect(toNumber('')).toBe(0)
  })
})
