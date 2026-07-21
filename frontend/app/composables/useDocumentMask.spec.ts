import { describe, expect, it } from 'vitest'
import { useDocumentMask } from './useDocumentMask'

describe('useDocumentMask', () => {
  const { maskInput } = useDocumentMask()

  it('masks CPF as digits are typed (11 digits)', () => {
    expect(maskInput('12345678900', 'cpf')).toBe('123.456.789-00')
  })

  it('masks partial CPF input', () => {
    expect(maskInput('123', 'cpf')).toBe('123')
    expect(maskInput('1234', 'cpf')).toBe('123.4')
  })

  it('caps CPF at 11 digits - does not turn into CNPJ if the user keeps typing', () => {
    expect(maskInput('123456789001234', 'cpf')).toBe('123.456.789-00')
  })

  it('masks CNPJ (14 digits)', () => {
    expect(maskInput('12345678000199', 'cnpj')).toBe('12.345.678/0001-99')
  })

  it('caps CNPJ at 14 digits', () => {
    expect(maskInput('123456780001991234', 'cnpj')).toBe('12.345.678/0001-99')
  })

  it('defaults to CPF when no type is given', () => {
    expect(maskInput('12345678900')).toBe('123.456.789-00')
  })

  it('ignores non-digit characters', () => {
    expect(maskInput('12.345.678/0001-99extra', 'cnpj')).toBe('12.345.678/0001-99')
  })
})
