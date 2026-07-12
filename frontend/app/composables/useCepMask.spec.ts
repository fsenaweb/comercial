import { describe, expect, it } from 'vitest'
import { useCepMask } from './useCepMask'

describe('useCepMask', () => {
  const { maskInput } = useCepMask()

  it('masks a full CEP', () => {
    expect(maskInput('01310100')).toBe('01310-100')
  })

  it('masks a partial CEP without inserting the dash early', () => {
    expect(maskInput('01310')).toBe('01310')
  })

  it('ignores non-digit characters and caps at 8 digits', () => {
    expect(maskInput('01310-100extra')).toBe('01310-100')
  })
})
