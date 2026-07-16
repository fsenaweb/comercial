import { describe, expect, it } from 'vitest'
import { nextFontScale, toggleTheme } from './appearance'

describe('nextFontScale', () => {
  it('advances one step at a time', () => {
    expect(nextFontScale('small', 1)).toBe('medium')
    expect(nextFontScale('medium', 1)).toBe('large')
  })

  it('goes back one step at a time', () => {
    expect(nextFontScale('large', -1)).toBe('medium')
    expect(nextFontScale('medium', -1)).toBe('small')
  })

  it('never goes past the smallest step', () => {
    expect(nextFontScale('small', -1)).toBe('small')
  })

  it('never goes past the largest step', () => {
    expect(nextFontScale('large', 1)).toBe('large')
  })
})

describe('toggleTheme', () => {
  it('flips light to dark and back', () => {
    expect(toggleTheme('light')).toBe('dark')
    expect(toggleTheme('dark')).toBe('light')
  })
})
