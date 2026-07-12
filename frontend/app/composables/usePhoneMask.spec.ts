import { describe, expect, it } from 'vitest'
import { usePhoneMask } from './usePhoneMask'

describe('usePhoneMask', () => {
  const { maskInput } = usePhoneMask()

  it('masks mobile as digits are typed (11 digits)', () => {
    expect(maskInput('11988887777', 'mobile')).toBe('(11) 98888-7777')
  })

  it('masks partial mobile input', () => {
    expect(maskInput('11', 'mobile')).toBe('(11')
    expect(maskInput('119', 'mobile')).toBe('(11) 9')
  })

  it('caps mobile at 11 digits — does not keep growing if the user keeps typing', () => {
    expect(maskInput('119888877771234', 'mobile')).toBe('(11) 98888-7777')
  })

  it('masks landline (10 digits)', () => {
    expect(maskInput('1132224444', 'landline')).toBe('(11) 3222-4444')
  })

  it('caps landline at 10 digits', () => {
    expect(maskInput('11322244441234', 'landline')).toBe('(11) 3222-4444')
  })

  it('defaults to mobile when no type is given', () => {
    expect(maskInput('11988887777')).toBe('(11) 98888-7777')
  })

  it('ignores non-digit characters', () => {
    expect(maskInput('(11) 98888-7777extra', 'mobile')).toBe('(11) 98888-7777')
  })
})
