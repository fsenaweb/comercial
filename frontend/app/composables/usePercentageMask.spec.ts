import { describe, expect, it } from 'vitest'
import { usePercentageMask } from './usePercentageMask'

describe('usePercentageMask', () => {
  const { maskInput, toNumber } = usePercentageMask()

  it('formats digits into a percentage, pushing decimals', () => {
    expect(maskInput('1')).toBe('0,01%')
    expect(maskInput('1000')).toBe('10,00%')
  })

  it('clamps to a maximum of 100%', () => {
    expect(maskInput('99999')).toBe('100,00%')
  })

  it('treats empty input as zero', () => {
    expect(maskInput('')).toBe('0,00%')
  })

  it('converts a masked value back to a number', () => {
    expect(toNumber('10,00%')).toBeCloseTo(10)
    expect(toNumber('')).toBe(0)
  })
})
